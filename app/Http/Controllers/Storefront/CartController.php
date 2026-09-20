<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\VendorListingItemStatus;
use App\Enums\VendorListingStatus;
use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Models\Product;
use App\Models\VendorListingItem;
use App\Services\CatalogCartLineService;
use App\Services\FlashSalePricingService;
use App\Services\VisitorAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class CartController extends Controller
{
    public function index(Request $request): View
    {
        $lines = collect($request->session()->get('store_cart', []));

        return view('storefront.cart.index', [
            'lines' => $lines,
            'subtotal' => $lines->sum(fn (array $line): float => $line['price'] * $line['quantity']),
        ]);
    }

    public function store(
        Request $request,
        VisitorAnalyticsService $analytics,
        CatalogCartLineService $catalogCart,
        FlashSalePricingService $flashSalePricing,
    ): RedirectResponse {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variation_id' => ['nullable', 'integer'],
            'vendor_listing_item_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'landing_page_id' => ['nullable', 'integer', 'exists:landing_pages,id'],
            'redirect_to' => ['nullable', 'in:cart,checkout'],
        ]);

        $product = Product::query()->where('status', 'published')->findOrFail($validated['product_id']);
        $line = isset($validated['vendor_listing_item_id'])
            ? $this->marketplaceLine($product, (int) $validated['vendor_listing_item_id'], $flashSalePricing)
            : $catalogCart->make($product, isset($validated['product_variation_id']) ? (int) $validated['product_variation_id'] : null);

        $landingPageId = $validated['landing_page_id'] ?? null;
        $landingPage = $landingPageId
            ? LandingPage::query()
                ->published()
                ->whereKey($landingPageId)
                ->whereHas('products', fn ($query) => $query->whereKey($product->getKey()))
                ->first()
            : null;
        $line['landing_page_id'] = $landingPage?->getKey();

        $cart = $request->session()->get('store_cart', []);
        $newQuantity = ($cart[$line['key']]['quantity'] ?? 0) + (int) $validated['quantity'];

        if (! $line['backorders_allowed'] && $line['available'] < $newQuantity) {
            throw ValidationException::withMessages(['quantity' => 'পর্যাপ্ত স্টক নেই।']);
        }

        $line['quantity'] = $newQuantity;
        $cart[$line['key']] = $line;
        $request->session()->put('store_cart', $cart);

        try {
            $analytics->record($request, [
                'event_type' => 'add_to_cart',
                'product_id' => $product->getKey(),
                'value' => $line['price'] * (int) $validated['quantity'],
                'metadata' => [
                    'product_name' => $product->name,
                    'quantity' => (int) $validated['quantity'],
                    'currency' => 'BDT',
                ],
            ]);
        } catch (Throwable) {
            // Analytics must never block the cart workflow.
        }

        $destination = ($validated['redirect_to'] ?? 'cart') === 'checkout'
            ? 'store.checkout.index'
            : 'store.cart.index';

        return to_route($destination)->with('success', 'পণ্যটি কার্টে যোগ হয়েছে।');
    }

    public function update(Request $request, string $line): RedirectResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);
        $cart = $request->session()->get('store_cart', []);
        abort_unless(isset($cart[$line]), 404);

        if (! $cart[$line]['backorders_allowed'] && $cart[$line]['available'] < $validated['quantity']) {
            throw ValidationException::withMessages(['quantity' => 'পর্যাপ্ত স্টক নেই।']);
        }

        $cart[$line]['quantity'] = (int) $validated['quantity'];
        $request->session()->put('store_cart', $cart);

        return back()->with('success', 'কার্ট আপডেট হয়েছে।');
    }

    public function destroy(Request $request, string $line): RedirectResponse
    {
        $cart = $request->session()->get('store_cart', []);
        unset($cart[$line]);
        $request->session()->put('store_cart', $cart);

        return back()->with('success', 'পণ্যটি কার্ট থেকে সরানো হয়েছে।');
    }

    /** @return array<string, mixed> */
    private function marketplaceLine(Product $product, int $itemId, FlashSalePricingService $flashSalePricing): array
    {
        $item = VendorListingItem::query()
            ->whereKey($itemId)
            ->where('status', VendorListingItemStatus::Active->value)
            ->whereHas('listing', fn ($query) => $query
                ->where('product_id', $product->getKey())
                ->where('status', VendorListingStatus::Approved->value))
            ->with(['listing.vendor:id,name', 'productVariation.attributeValues.attribute:id,name', 'stocks'])
            ->firstOrFail();

        return [
            'key' => 'market-'.$item->getKey(),
            'product_id' => $product->getKey(),
            'product_variation_id' => $item->product_variation_id,
            'vendor_listing_item_id' => $item->getKey(),
            'vendor_id' => $item->vendor_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'option' => $item->productVariation
                ? $item->productVariation->attributeValues->map(fn ($value) => $value->attribute->name.': '.$value->value)->join(', ')
                : null,
            'vendor' => $item->listing->vendor->name,
            'sku' => $item->seller_sku,
            'image' => $this->imageFor($product),
            'price' => $flashSalePricing->priceFor(
                $product,
                (float) ($item->sale_price ?? $item->regular_price),
            ),
            'available' => $item->available_quantity,
            'backorders_allowed' => $item->backorders_allowed,
        ];
    }

    private function imageFor(Product $product): ?string
    {
        return filled($product->featured_image)
            ? asset('storage/'.ltrim($product->featured_image, '/'))
            : null;
    }
}
