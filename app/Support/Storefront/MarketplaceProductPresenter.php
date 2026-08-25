<?php

namespace App\Support\Storefront;

use App\Enums\VendorListingItemStatus;
use App\Enums\VendorListingStatus;
use App\Models\Product;
use App\Models\VendorListingItem;

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
        } else {
            $activeVariations = $product->variations->where('status', true);
            $pricedVariations = $activeVariations->filter(
                fn ($variation): bool => (float) $variation->regular_price > 0,
            );

            $lowestVariation = $pricedVariations
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
            $available = $product->product_type === 'variable'
                ? $activeVariations->sum('stock_quantity')
                : $product->stock_quantity;
            $vendorCount = 0;
        }

        $discount = $regularPrice > $price && $regularPrice > 0
            ? (int) round((($regularPrice - $price) / $regularPrice) * 100)
            : 0;

        return [
            'product' => $product,
            'price' => $price,
            'regular_price' => $regularPrice,
            'discount' => $discount,
            'available' => $available,
            'vendor_count' => $vendorCount,
            'image' => filled($product->featured_image)
                ? asset('storage/'.ltrim($product->featured_image, '/'))
                : null,
        ];
    }
}
