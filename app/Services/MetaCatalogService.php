<?php

namespace App\Services;

use App\Enums\VendorListingItemStatus;
use App\Enums\VendorListingStatus;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Support\Storefront\MarketplaceProductPresenter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MetaCatalogService
{
    /** @var array<int, string> */
    public const FEED_COLUMNS = [
        'id',
        'title',
        'description',
        'availability',
        'condition',
        'price',
        'sale_price',
        'link',
        'image_link',
        'additional_image_link',
        'brand',
        'product_type',
        'custom_label_0',
        'custom_label_1',
        'inventory',
        'gtin',
        'mpn',
        'identifier_exists',
    ];

    /**
     * Meta scheduled-feed rows. One stable catalog item is emitted per Tisilo
     * product so its ID always matches Pixel/CAPI content_ids.
     *
     * @return Collection<int, array<string, string|int>>
     */
    public function items(): Collection
    {
        $settings = SiteSetting::valuesFor('facebook_catalog');
        $currency = strtoupper(trim((string) ($settings['default_currency'] ?? 'BDT'))) ?: 'BDT';
        $defaultBrand = trim((string) ($settings['default_brand'] ?? 'Tisilo')) ?: 'Tisilo';

        return Product::query()
            ->where('status', 'published')
            ->with([
                'brand:id,name',
                'category.parent.parent',
                'variations:id,product_id,regular_price,sale_price,stock_quantity,stock_status,status,sort_order',
                'vendorListings' => fn ($query) => $query->where('status', VendorListingStatus::Approved->value),
                'vendorListings.items' => fn ($query) => $query->where('status', VendorListingItemStatus::Active->value),
                'vendorListings.items.stocks',
            ])
            ->orderBy('id')
            ->get()
            ->map(function (Product $product) use ($currency, $defaultBrand): ?array {
                $summary = MarketplaceProductPresenter::summarize($product);
                $price = (float) ($summary['price'] ?? 0);
                $regularPrice = (float) ($summary['regular_price'] ?? $price);
                $image = trim((string) ($summary['image'] ?? ''));

                if ($price <= 0 || $image === '') {
                    return null;
                }

                $category = $product->category;
                $categoryPath = $category?->hierarchicalName() ?? 'Uncategorized';
                $rootCategory = $category
                    ? collect($category->hierarchyForCatalog())->first()?->name
                    : 'Uncategorized';
                $description = trim(strip_tags((string) ($product->short_description ?: $product->description ?: $product->name)));
                $gallery = collect($product->gallery_images ?? [])
                    ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
                    ->map(fn (string $path): string => asset('storage/'.ltrim($path, '/')))
                    ->take(10)
                    ->implode(',');
                $inventory = max(0, (int) ($summary['available'] ?? 0));
                $canPurchase = (bool) ($summary['can_purchase'] ?? false);
                $isBackorder = $product->stock_status === 'on_backorder'
                    || $product->variations->contains(fn ($variation): bool => $variation->stock_status === 'on_backorder');
                $barcode = trim((string) $product->barcode);
                $sku = trim((string) $product->sku);

                return [
                    'id' => (string) $product->getKey(),
                    'title' => mb_substr(trim((string) $product->name), 0, 200),
                    'description' => mb_substr($description ?: $product->name, 0, 9999),
                    'availability' => $canPurchase ? ($isBackorder && $inventory === 0 ? 'available for order' : 'in stock') : 'out of stock',
                    'condition' => 'new',
                    'price' => number_format(max($regularPrice, $price), 2, '.', '').' '.$currency,
                    'sale_price' => $regularPrice > $price ? number_format($price, 2, '.', '').' '.$currency : '',
                    'link' => route('store.products.show', $product),
                    'image_link' => $image,
                    'additional_image_link' => $gallery,
                    'brand' => trim((string) ($product->brand?->name ?: $defaultBrand)),
                    'product_type' => str_replace(' › ', ' > ', $categoryPath),
                    'custom_label_0' => (string) $rootCategory,
                    'custom_label_1' => str_replace(' › ', ' > ', $categoryPath),
                    'inventory' => $inventory,
                    'gtin' => $barcode,
                    'mpn' => $sku,
                    'identifier_exists' => ($barcode !== '' || $sku !== '') ? 'yes' : 'no',
                ];
            })
            ->filter()
            ->values();
    }

    public function feedUrl(): ?string
    {
        $token = trim((string) (SiteSetting::secretsFor('facebook_catalog')['feed_token'] ?? ''));

        return $token !== '' ? route('integrations.meta.catalog-feed', ['token' => $token]) : null;
    }

    /** @return array<string, mixed> */
    public function verify(): array
    {
        [$values, $accessToken] = $this->configuration();
        $catalogId = (string) $values['catalog_id'];
        $response = $this->client($accessToken)
            ->get($this->graphUrl($values, $catalogId), [
                'fields' => 'id,name,product_count,vertical',
            ]);

        return $this->result($response, 'Catalog verification');
    }

    /** @return array{catalog:array<string,mixed>,feedId:string,upload:array<string,mixed>,itemCount:int} */
    public function sync(): array
    {
        [$values, $accessToken] = $this->configuration();
        $feedUrl = $this->feedUrl();

        if (! $feedUrl) {
            throw new RuntimeException('Save Meta Catalog settings once to generate the secure feed URL.');
        }

        $catalog = $this->verify();
        $feedId = trim((string) ($values['feed_id'] ?? ''));

        if ($feedId === '') {
            $feed = $this->result(
                $this->client($accessToken)->asForm()->post(
                    $this->graphUrl($values, (string) $values['catalog_id']).'/product_feeds',
                    [
                        'name' => 'Tisilo Automatic Product & Category Feed',
                        'default_currency' => strtoupper((string) ($values['default_currency'] ?? 'BDT')),
                        'country' => 'BD',
                        'delimiter' => 'TAB',
                        'encoding' => 'UTF8',
                        'quoted_fields_mode' => 'ON',
                        'deletion_enabled' => 'true',
                        'feed_type' => 'PRODUCTS',
                    ],
                ),
                'Catalog feed creation',
            );
            $feedId = trim((string) ($feed['id'] ?? ''));

            if ($feedId === '') {
                throw new RuntimeException('Meta created no feed ID. Check Catalog Management permission.');
            }
        }

        $upload = $this->result(
            $this->client($accessToken)->asForm()->post(
                $this->graphUrl($values, $feedId).'/uploads',
                ['url' => $feedUrl, 'update_only' => 'false'],
            ),
            'Catalog feed upload',
        );
        $itemCount = $this->items()->count();

        SiteSetting::put('facebook_catalog', [
            ...$values,
            'feed_id' => $feedId,
            'catalog_name' => $catalog['name'] ?? null,
            'remote_product_count' => (int) ($catalog['product_count'] ?? 0),
            'local_feed_item_count' => $itemCount,
            'last_upload_id' => $upload['id'] ?? null,
            'last_sync_status' => 'Queued by Meta',
            'last_synced_at' => now()->toIso8601String(),
        ]);

        return compact('catalog', 'feedId', 'upload', 'itemCount');
    }

    public function syncWhenConfigured(): bool
    {
        $values = SiteSetting::valuesFor('facebook_catalog');

        if (! filter_var($values['enabled'] ?? false, FILTER_VALIDATE_BOOL)
            || blank($values['catalog_id'] ?? null)
            || blank(SiteSetting::secretsFor('facebook_catalog')['access_token'] ?? null)) {
            return false;
        }

        $this->sync();

        return true;
    }

    /** @return array{0:array<string,mixed>,1:string} */
    private function configuration(): array
    {
        $values = SiteSetting::valuesFor('facebook_catalog');
        $accessToken = trim((string) (SiteSetting::secretsFor('facebook_catalog')['access_token'] ?? ''));

        if (! filter_var($values['enabled'] ?? false, FILTER_VALIDATE_BOOL)
            || ! preg_match('/^[0-9]{5,32}$/', (string) ($values['catalog_id'] ?? ''))
            || $accessToken === '') {
            throw new RuntimeException('Enable Meta Catalog and save a valid Catalog ID and Catalog Management access token first.');
        }

        return [$values, $accessToken];
    }

    private function client(string $accessToken): PendingRequest
    {
        return Http::acceptJson()
            ->withToken($accessToken)
            ->timeout(30)
            ->retry(3, 500, throw: false);
    }

    /** @param array<string, mixed> $values */
    private function graphUrl(array $values, string $path): string
    {
        $version = preg_match('/^v[0-9]{1,2}\.0$/', (string) ($values['api_version'] ?? ''))
            ? (string) $values['api_version']
            : 'v23.0';

        return "https://graph.facebook.com/{$version}/{$path}";
    }

    /** @return array<string, mixed> */
    private function result(Response $response, string $operation): array
    {
        $result = $response->json();

        if (! $response->successful() || ! is_array($result) || isset($result['error'])) {
            $message = is_array($result) ? data_get($result, 'error.message') : null;
            throw new RuntimeException($operation.' failed'.($message ? ': '.$message : " (HTTP {$response->status()})"));
        }

        return $result;
    }
}
