<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\VendorListingItemStatus;
use App\Enums\VendorListingStatus;
use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\LandingCheckoutService;
use App\Services\PaymentMethodService;
use App\Services\ShippingRateService;
use App\Services\VisitorAnalyticsService;
use App\Support\Storefront\MarketplaceProductPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class LandingPageController extends Controller
{
    public function show(Request $request, LandingPage $landingPage, VisitorAnalyticsService $analytics): View
    {
        abort_unless(
            LandingPage::query()->published()->whereKey($landingPage->getKey())->exists(),
            404,
        );

        return $this->render($request, $landingPage, $analytics);
    }

    public function preview(Request $request, LandingPage $landingPage, VisitorAnalyticsService $analytics): View
    {
        return $this->render($request, $landingPage, $analytics, true);
    }

    private function render(
        Request $request,
        LandingPage $landingPage,
        VisitorAnalyticsService $analytics,
        bool $preview = false,
    ): View {
        $landingPage->load([
            'products' => fn ($query) => $query->where('status', 'published'),
            'products.variations.attributeValues.attribute:id,name',
        ]);
        abort_if($landingPage->products->isEmpty(), 404, 'This landing page has no published products.');

        $primaryProduct = $landingPage->products->first();
        $relatedProducts = $this->relatedProducts($landingPage, $primaryProduct);
        $checkout = app(LandingCheckoutService::class);
        $checkoutProducts = $checkout->options($landingPage);
        $selectedProduct = collect($checkoutProducts)->firstWhere('id', (int) $request->old('product_id')) ?? $checkoutProducts[0];
        $selectedVariation = collect($selectedProduct['variations'])->firstWhere('id', (int) $request->old('product_variation_id'))
            ?? collect($selectedProduct['variations'])->first(fn (array $option): bool => $option['available'] > 0)
            ?? ($selectedProduct['variations'][0] ?? null);
        if (! $selectedProduct['variable']) {
            $selectedVariation = null;
        }
        $initialSelection = [
            'product_id' => $selectedProduct['id'],
            'product_variation_id' => $selectedProduct['variable'] ? ($selectedVariation['id'] ?? null) : null,
            'quantity' => max(1, min(99, (int) $request->old('quantity', 1))),
        ];
        $initialSubtotal = ($selectedVariation['price'] ?? $selectedProduct['price']) * $initialSelection['quantity'];
        $regions = collect();
        $checkoutError = null;
        try {
            $cart = $checkout->cart($landingPage, $initialSelection);
            $regions = app(ShippingRateService::class)->quotesForCart($cart);
        } catch (ValidationException $exception) {
            $checkoutError = collect($exception->errors())->flatten()->first();
        }

        $sessionKey = 'landing_checkout.'.$landingPage->id;
        if (! $preview && (! $request->session()->has($sessionKey.'.token') || $request->session()->has($sessionKey.'.order_id'))) {
            $request->session()->put($sessionKey, ['token' => (string) Str::uuid()]);
        }

        $metaBrowserEvents = [];
        if (! $preview) {
            $viewProduct = collect($checkoutProducts)->firstWhere('id', $primaryProduct->getKey());
            $viewVariations = collect($viewProduct['variations'] ?? []);
            $viewVariation = $viewVariations->first(fn (array $variation): bool => ($variation['available'] ?? 0) > 0)
                ?? $viewVariations->first();
            $viewPrice = (bool) ($viewProduct['variable'] ?? false)
                ? ($viewVariation['price'] ?? $primaryProduct->sale_price ?? $primaryProduct->regular_price)
                : ($viewProduct['price'] ?? $primaryProduct->sale_price ?? $primaryProduct->regular_price);
            $viewContentEvent = [
                'event_type' => 'product_view',
                'event_id' => 'view-content-'.Str::uuid(),
                'product_id' => $primaryProduct->getKey(),
                'value' => $viewPrice,
                'metadata' => [
                    'product_name' => $primaryProduct->name,
                    'currency' => 'BDT',
                    'content_ids' => [(string) $primaryProduct->getKey()],
                    'contents' => [[
                        'id' => (string) $primaryProduct->getKey(),
                        'quantity' => 1,
                        'item_price' => $viewPrice,
                    ]],
                    'num_items' => 1,
                ],
            ];

            try {
                $analytics->record($request, $viewContentEvent);
            } catch (Throwable) {
                // Analytics must never block a paid-ad landing page.
            }

            $metaBrowserEvents[] = $viewContentEvent;
        }

        return view('storefront.landing.show-classic', [
            'landingPage' => $landingPage,
            'primaryProduct' => $primaryProduct,
            'relatedProducts' => $relatedProducts,
            'preview' => $preview,
            'checkoutProducts' => $checkoutProducts,
            'initialSelection' => $initialSelection,
            'initialSubtotal' => $initialSubtotal,
            'regions' => $regions,
            'checkoutError' => $checkoutError,
            'checkoutToken' => $preview ? '' : $request->session()->get($sessionKey.'.token'),
            'metaBrowserEvents' => $metaBrowserEvents,
            'generalSettings' => SiteSetting::valuesFor('general'),
            'contactSettings' => SiteSetting::valuesFor('contact'),
            'seoSettings' => SiteSetting::valuesFor('seo'),
            'paymentMethods' => app(PaymentMethodService::class)->enabled(),
            'defaultPaymentMethod' => app(PaymentMethodService::class)->default(),
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function relatedProducts(LandingPage $landingPage, Product $primaryProduct): Collection
    {
        $excludedProductIds = $landingPage->products
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $query = fn (): Builder => Product::query()
            ->withReviewSummary()
            ->where('status', 'published')
            ->whereNotIn('id', $excludedProductIds)
            ->with($this->storefrontProductRelations())
            ->orderByDesc('featured')
            ->latest('id');

        $products = $primaryProduct->category_id
            ? $query()->where('category_id', $primaryProduct->category_id)->limit(6)->get()
            : collect();

        if ($products->count() < 6) {
            $products = $products->concat(
                $query()
                    ->whereNotIn('id', $products->pluck('id'))
                    ->limit(6 - $products->count())
                    ->get(),
            );
        }

        return $products
            ->take(6)
            ->map(fn (Product $product): array => MarketplaceProductPresenter::summarize($product))
            ->values();
    }

    /** @return array<int|string, mixed> */
    private function storefrontProductRelations(): array
    {
        return [
            'brand:id,name,slug',
            'category:id,parent_id,name,slug',
            'variations:id,product_id,sku,regular_price,sale_price,stock_quantity,stock_status,image,status,is_default,sort_order',
            'vendorListings' => fn ($query) => $query->where('status', VendorListingStatus::Approved->value),
            'vendorListings.items' => fn ($query) => $query->where('status', VendorListingItemStatus::Active->value),
            'vendorListings.items.stocks',
        ];
    }
}
