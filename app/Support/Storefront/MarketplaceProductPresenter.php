<?php

namespace App\Support\Storefront;

use App\Enums\VendorListingItemStatus;
use App\Enums\VendorListingStatus;
use App\Models\Product;
use App\Models\VendorListingItem;
use App\Services\FlashSalePricingService;

class MarketplaceProductPresenter
{
    /** @return array<string, mixed> */
    public static function summarize(Product $product): array
    {
        $marketplaceItems = $product->vendorListings
            ->where('status', VendorListingStatus::Approved)
            ->flatMap->items
            ->where('status', VendorListingItemStatus::Active)
            ->map(function (VendorListingItem $item): array {
                $available = $item->stocks->sum(
                    fn ($stock): int => $stock->available_quantity,
                );

                return [
                    'id' => $item->getKey(),
                    'price' => (float) ($item->sale_price ?? $item->regular_price),
                    'regular_price' => (float) $item->regular_price,
                    'available' => $available,
                    'backorders_allowed' => $item->backorders_allowed,
                    'vendor_id' => $item->vendor_id,
                ];
            })
            ->filter(fn (array $item): bool => $item['available'] > 0 || $item['backorders_allowed'])
            ->sortBy('price')
            ->values();

        $lowestOffer = $marketplaceItems->first();

        if ($lowestOffer) {
            $price = $lowestOffer['price'];
            $regularPrice = $lowestOffer['regular_price'];
            $available = $marketplaceItems->sum('available');
            $vendorCount = $marketplaceItems->pluck('vendor_id')->unique()->count();
            $productVariationId = null;
            $vendorListingItemId = $lowestOffer['id'];
            $canPurchase = true;
            $stockLabel = $available > 0 ? __(':count in stock', ['count' => $available]) : __('Available to order');
        } else {
            $activeVariations = $product->variations->where('status', true);
            $pricedVariations = $activeVariations->filter(
                fn ($variation): bool => (float) $variation->regular_price > 0,
            );
            $purchasableVariations = $pricedVariations->filter(
                fn ($variation): bool => $variation->stock_status === 'on_backorder'
                    || (int) $variation->stock_quantity > 0,
            );

            $lowestVariation = ($purchasableVariations->isNotEmpty() ? $purchasableVariations : $pricedVariations)
                ->sortBy(fn ($variation): float => (float) ($variation->sale_price ?? $variation->regular_price))
                ->first();

            $price = (float) (
                $lowestVariation?->sale_price
                ?? $lowestVariation?->regular_price
                ?? $product->sale_price
                ?? $product->regular_price
            );
            $regularPrice = (float) (
                $lowestVariation?->regular_price
                ?? $product->regular_price
            );
            $vendorCount = 0;
            $vendorListingItemId = null;

            if ($product->product_type === 'variable') {
                $available = $activeVariations->sum('stock_quantity');
                $hasBackorder = $activeVariations->contains(
                    fn ($variation): bool => $variation->stock_status === 'on_backorder',
                );
                $canPurchase = $lowestVariation !== null
                    && ((int) $lowestVariation->stock_quantity > 0 || $lowestVariation->stock_status === 'on_backorder')
                    && $price > 0;
                $productVariationId = $canPurchase ? $lowestVariation->getKey() : null;
                $stockLabel = $available > 0
                    ? __(':count in stock', ['count' => $available])
                    : ($hasBackorder ? __('Pre-order') : __('Out of Stock'));
            } else {
                $available = max(0, (int) $product->stock_quantity);
                $hasUnlimitedStock = ! $product->manage_stock && $product->stock_status !== 'out_of_stock';
                $isBackorder = $product->stock_status === 'on_backorder';
                $canPurchase = $price > 0
                    && $product->stock_status !== 'out_of_stock'
                    && ($hasUnlimitedStock || $available > 0 || $isBackorder);
                $productVariationId = null;
                $stockLabel = match (true) {
                    $product->manage_stock && $available > 0 => __(':count in stock', ['count' => $available]),
                    $isBackorder => __('Pre-order'),
                    $hasUnlimitedStock => __('In Stock'),
                    default => __('Out of Stock'),
                };
            }
        }

        $discount = $regularPrice > $price && $regularPrice > 0
            ? (int) round((($regularPrice - $price) / $regularPrice) * 100)
            : 0;

        return app(FlashSalePricingService::class)->applyToSummary([
            'product' => $product,
            'price' => $price,
            'regular_price' => $regularPrice,
            'discount' => $discount,
            'available' => $available,
            'vendor_count' => $vendorCount,
            'review_rating' => round((float) ($product->getAttribute('review_rating') ?? 0), 1),
            'review_count' => (int) ($product->getAttribute('review_count') ?? 0),
            'stock_label' => $stockLabel,
            'can_purchase' => $canPurchase,
            'product_variation_id' => $productVariationId,
            'vendor_listing_item_id' => $vendorListingItemId,
            'image' => filled($product->featured_image)
                ? asset('storage/'.ltrim($product->featured_image, '/'))
                : null,
        ]);
    }
}
