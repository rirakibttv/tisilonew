<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShippingRegion;
use App\Models\VendorListingItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ShippingRateService
{
    /**
     * @param  Collection<string, array<string, mixed>>  $cart
     * @return Collection<int, array<string, mixed>>
     */
    public function quotesForCart(Collection $cart): Collection
    {
        $productIds = $cart->pluck('product_id')->filter()->unique()->values();
        $products = Product::query()
            ->with('shippingClass:id,name,is_active')
            ->whereIn('id', $productIds)
            ->get(['id', 'name', 'shipping_class_id'])
            ->keyBy('id');

        $listingItems = VendorListingItem::query()
            ->with('listing.shippingClass:id,name,is_active')
            ->whereIn('id', $cart->pluck('vendor_listing_item_id')->filter()->unique())
            ->get(['id', 'vendor_listing_id'])
            ->keyBy('id');

        $resolvedLines = $cart->map(function (array $line) use ($products, $listingItems): array {
            $product = $products->get((int) ($line['product_id'] ?? 0));
            $listingItem = $listingItems->get((int) ($line['vendor_listing_item_id'] ?? 0));
            $listingClass = $listingItem?->listing?->shippingClass;
            $shippingClass = $listingClass ?: $product?->shippingClass;

            return [
                'line' => $line,
                'shipping_class_id' => $shippingClass?->is_active ? $shippingClass->getKey() : null,
            ];
        });

        $missing = $resolvedLines->first(fn (array $resolved): bool => ! $resolved['shipping_class_id']);
        if ($missing) {
            throw ValidationException::withMessages([
                'shipping_region_id' => ($missing['line']['name'] ?? 'একটি পণ্য').'–এর Shipping Class নির্ধারণ করা নেই।',
            ]);
        }

        $quantities = $resolvedLines
            ->groupBy('shipping_class_id')
            ->map(fn (Collection $lines): int => $lines->sum(
                fn (array $resolved): int => max(1, (int) ($resolved['line']['quantity'] ?? 1)),
            ));
        $classIds = $quantities->keys();

        return ShippingRegion::query()
            ->active()
            ->with(['rates' => fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('shipping_class_id', $classIds)
                ->with(['shippingClass:id,name', 'partner:id,name'])])
            ->orderBy('sort_order')
            ->orderBy('division')
            ->orderBy('district')
            ->orderBy('upazila')
            ->get()
            ->mapWithKeys(function (ShippingRegion $region) use ($quantities): array {
                $rates = $region->rates->keyBy('shipping_class_id');
                if ($quantities->keys()->contains(fn ($classId): bool => ! $rates->has($classId))) {
                    return [];
                }

                $breakdown = $quantities->map(function (int $quantity, int $classId) use ($rates): array {
                    $rate = $rates->get($classId);
                    $amount = (float) $rate->base_charge
                        + max(0, $quantity - 1) * (float) $rate->additional_item_charge;

                    return [
                        'shipping_class_id' => $classId,
                        'shipping_class' => $rate->shippingClass->name,
                        'quantity' => $quantity,
                        'base_charge' => (float) $rate->base_charge,
                        'additional_item_charge' => (float) $rate->additional_item_charge,
                        'amount' => round($amount, 2),
                        'partner_id' => $rate->shipping_partner_id,
                        'partner' => $rate->partner?->name,
                        'estimated_min_days' => $rate->estimated_min_days,
                        'estimated_max_days' => $rate->estimated_max_days,
                    ];
                })->values();

                $partnerIds = $breakdown->pluck('partner_id')->filter()->unique();

                return [$region->id => [
                    'region_id' => $region->id,
                    'name' => $region->full_name,
                    'division' => $region->division,
                    'district' => $region->district,
                    'upazila' => $region->upazila,
                    'postal_code' => $region->postal_code,
                    'amount' => round((float) $breakdown->sum('amount'), 2),
                    'estimated_min_days' => (int) $breakdown->max('estimated_min_days'),
                    'estimated_max_days' => (int) $breakdown->max('estimated_max_days'),
                    'partner_id' => $partnerIds->count() === 1 ? (int) $partnerIds->first() : null,
                    'partners' => $breakdown->pluck('partner')->filter()->unique()->values()->all(),
                    'breakdown' => $breakdown->all(),
                ]];
            });
    }
}
