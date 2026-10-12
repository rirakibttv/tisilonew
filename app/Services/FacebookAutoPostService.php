<?php

namespace App\Services;

use App\Jobs\PublishFacebookProductPost;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Support\Storefront\MarketplaceProductPresenter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class FacebookAutoPostService
{
    /** @return array{count:int,pages:array<string, string>,selected_page_id:string} */
    public function discoverPagesFromCapi(): array
    {
        $capiValues = SiteSetting::valuesFor('facebook_capi');
        $capiToken = trim((string) (SiteSetting::secretsFor('facebook_capi')['access_token'] ?? ''));
        $version = $this->apiVersion($capiValues['api_version'] ?? null);

        if ($capiToken === '') {
            throw new RuntimeException('Save the Facebook CAPI access token first.');
        }

        $response = $this->client($capiToken)->get($this->graphUrl($version, 'me/accounts'), [
            'fields' => 'id,name,access_token,tasks',
            'limit' => 100,
        ]);
        $result = $this->result($response, 'Facebook Page discovery');

        $pages = collect($result['data'] ?? [])
            ->filter(fn (mixed $page): bool => is_array($page)
                && preg_match('/^[0-9]{5,32}$/', (string) ($page['id'] ?? '')) === 1
                && filled($page['name'] ?? null)
                && filled($page['access_token'] ?? null))
            ->mapWithKeys(fn (array $page): array => [(string) $page['id'] => [
                'name' => trim((string) $page['name']),
                'access_token' => trim((string) $page['access_token']),
            ]]);

        if ($pages->isEmpty()) {
            throw new RuntimeException('No manageable Facebook Page was returned. The CAPI token must be a User Access Token with pages_show_list, pages_read_engagement and pages_manage_posts permissions.');
        }

        $values = SiteSetting::valuesFor('facebook_auto_post');
        $pageOptions = $pages->map(fn (array $page): string => $page['name'])->all();
        $pageTokens = $pages->map(fn (array $page): string => $page['access_token'])->all();
        $selectedPageId = (string) ($values['page_id'] ?? '');
        if (! array_key_exists($selectedPageId, $pageOptions)) {
            $selectedPageId = (string) array_key_first($pageOptions);
        }

        SiteSetting::put('facebook_auto_post', [
            ...$values,
            'api_version' => $this->apiVersion($values['api_version'] ?? $version),
            'available_pages' => $pageOptions,
            'page_id' => $selectedPageId,
            'page_name' => $pageOptions[$selectedPageId],
            'page_discovery_status' => 'Connected',
            'pages_last_synced_at' => now()->toIso8601String(),
        ], [
            'page_tokens' => $pageTokens,
            'page_access_token' => $pageTokens[$selectedPageId],
        ]);

        return [
            'count' => count($pageOptions),
            'pages' => $pageOptions,
            'selected_page_id' => $selectedPageId,
        ];
    }

    /** @return array{name:string,token:string}|null */
    public function discoveredPage(string $pageId): ?array
    {
        $name = SiteSetting::valuesFor('facebook_auto_post')['available_pages'][$pageId] ?? null;
        $token = SiteSetting::secretsFor('facebook_auto_post')['page_tokens'][$pageId] ?? null;

        if (! is_string($name) || ! is_string($token) || trim($name) === '' || trim($token) === '') {
            return null;
        }

        return ['name' => trim($name), 'token' => trim($token)];
    }

    /** @return array<string, string> */
    public function pageOptions(): array
    {
        $values = SiteSetting::valuesFor('facebook_auto_post');
        $options = is_array($values['available_pages'] ?? null) ? $values['available_pages'] : [];
        $currentId = trim((string) ($values['page_id'] ?? ''));

        if ($currentId !== '' && ! array_key_exists($currentId, $options)) {
            $options[$currentId] = trim((string) ($values['page_name'] ?? '')) ?: $currentId;
        }

        return $options;
    }

    /** @return array<string, mixed> */
    public function verifyPage(): array
    {
        $configuration = $this->configuration(requireEnabled: false, requireAutoPost: false);
        $result = $this->result(
            $this->client($configuration['page_access_token'])->get(
                $this->graphUrl($configuration['api_version'], $configuration['page_id']),
                ['fields' => 'id,name,link'],
            ),
            'Facebook Page verification',
        );

        SiteSetting::put('facebook_auto_post', [
            ...SiteSetting::valuesFor('facebook_auto_post'),
            'page_name' => $result['name'] ?? $configuration['page_name'],
            'page_link' => $result['link'] ?? null,
            'page_verification_status' => 'Verified',
            'page_last_verified_at' => now()->toIso8601String(),
        ]);

        return $result;
    }

    public function queueWhenPublished(Product $product): bool
    {
        if ($product->status !== 'published' || ! $this->isAutoPostReady()) {
            return false;
        }

        PublishFacebookProductPost::dispatch((int) $product->getKey())->afterCommit();

        return true;
    }

    /** @return array<string, mixed> */
    public function publishProduct(Product $product): array
    {
        $configuration = $this->configuration(requireEnabled: true, requireAutoPost: true);
        if ($product->status !== 'published') {
            throw new RuntimeException('Only published products can be posted to Facebook.');
        }

        $product->loadMissing([
            'variations',
            'vendorListings.items.stocks',
        ]);
        $summary = MarketplaceProductPresenter::summarize($product);
        $price = (float) ($summary['price'] ?? 0);
        $url = route('store.products.show', $product);
        $template = trim((string) ($configuration['message_template'] ?? ''))
            ?: "✨ {product_name}\n\nPrice: ৳{price}\n\nShop now: {url}";
        $message = Str::limit(strtr($template, [
            '{product_name}' => trim((string) $product->name),
            '{price}' => number_format($price, 2),
            '{url}' => $url,
        ]), 5000, '');

        $result = $this->result(
            $this->client($configuration['page_access_token'])->asForm()->post(
                $this->graphUrl($configuration['api_version'], $configuration['page_id'].'/feed'),
                ['message' => $message, 'link' => $url, 'published' => 'true'],
            ),
            'Facebook product post',
        );

        if (blank($result['id'] ?? null)) {
            throw new RuntimeException('Facebook accepted no post ID.');
        }

        SiteSetting::put('facebook_auto_post', [
            ...SiteSetting::valuesFor('facebook_auto_post'),
            'last_post_status' => 'Published',
            'last_post_id' => (string) $result['id'],
            'last_post_product_id' => (int) $product->getKey(),
            'last_posted_at' => now()->toIso8601String(),
        ]);

        return $result;
    }

    private function isAutoPostReady(): bool
    {
        $values = SiteSetting::valuesFor('facebook_auto_post');
        $secrets = SiteSetting::secretsFor('facebook_auto_post');

        return filter_var($values['enabled'] ?? false, FILTER_VALIDATE_BOOL)
            && filter_var($values['post_on_product_publish'] ?? false, FILTER_VALIDATE_BOOL)
            && preg_match('/^[0-9]{5,32}$/', (string) ($values['page_id'] ?? '')) === 1
            && filled($secrets['page_access_token'] ?? null);
    }

    /** @return array{api_version:string,message_template:?string,page_access_token:string,page_id:string,page_name:string} */
    private function configuration(bool $requireEnabled, bool $requireAutoPost): array
    {
        $values = SiteSetting::valuesFor('facebook_auto_post');
        $secrets = SiteSetting::secretsFor('facebook_auto_post');
        $pageId = trim((string) ($values['page_id'] ?? ''));
        $pageAccessToken = trim((string) ($secrets['page_access_token'] ?? ''));

        if (($requireEnabled && ! filter_var($values['enabled'] ?? false, FILTER_VALIDATE_BOOL))
            || ($requireAutoPost && ! filter_var($values['post_on_product_publish'] ?? false, FILTER_VALIDATE_BOOL))
            || preg_match('/^[0-9]{5,32}$/', $pageId) !== 1
            || $pageAccessToken === '') {
            throw new RuntimeException('Select a Facebook Page, save its Page Access Token, and enable FB Auto Post first.');
        }

        return [
            'api_version' => $this->apiVersion($values['api_version'] ?? null),
            'page_access_token' => $pageAccessToken,
            'page_id' => $pageId,
            'page_name' => trim((string) ($values['page_name'] ?? '')),
            'message_template' => filled($values['default_message_template'] ?? null)
                ? trim((string) $values['default_message_template'])
                : null,
        ];
    }

    private function client(string $accessToken): PendingRequest
    {
        return Http::acceptJson()
            ->withToken($accessToken)
            ->timeout(20)
            ->retry(3, 500, throw: false);
    }

    private function apiVersion(mixed $version): string
    {
        $version = trim((string) $version);

        return preg_match('/^v[0-9]{1,2}\.0$/', $version) === 1 ? $version : 'v23.0';
    }

    private function graphUrl(string $version, string $path): string
    {
        return 'https://graph.facebook.com/'.$version.'/'.ltrim($path, '/');
    }

    /** @return array<string, mixed> */
    private function result(Response $response, string $operation): array
    {
        $result = $response->json();
        if (! $response->successful() || ! is_array($result) || isset($result['error'])) {
            $message = is_array($result) ? Arr::get($result, 'error.message') : null;
            throw new RuntimeException($operation.' failed'.($message ? ': '.Str::limit((string) $message, 300) : " (HTTP {$response->status()})"));
        }

        return $result;
    }
}
