<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariation;
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
            ->get(['id', 'name', 'shipping_class_id', 'weight', 'weight_unit'])
            ->keyBy('id');

        $listingItems = VendorListingItem::query()
            ->with(['listing.shippingClass:id,name,is_active', 'productVariation:id,weight,weight_unit'])
            ->whereIn('id', $cart->pluck('vendor_listing_item_id')->filter()->unique())
            ->get(['id', 'vendor_listing_id', 'product_variation_id'])
            ->keyBy('id');

        $variationIds = $cart->pluck('product_variation_id')->filter()->unique();
        $variations = ProductVariation::query()
            ->whereIn('id', $variationIds)
            ->get(['id', 'weight', 'weight_unit'])
            ->keyBy('id');

        $resolvedLines = $cart->map(function (array $line) use ($products, $listingItems, $variations): array {
            $product = $products->get((int) ($line['product_id'] ?? 0));
            $listingItem = $listingItems->get((int) ($line['vendor_listing_item_id'] ?? 0));
            $listingClass = $listingItem?->listing?->shippingClass;
            $shippingClass = $listingClass ?: $product?->shippingClass;
            $variation = $listingItem?->productVariation
                ?: $variations->get((int) ($line['product_variation_id'] ?? 0));
            $weight = $variation?->weight ?? $product?->weight;
            $unit = $variation?->weight !== null ? $variation->weight_unit : $product?->weight_unit;
            $weightKg = $weight === null ? null : ((float) $weight / (($unit ?? 'kg') === 'g' ? 1000 : 1));

            return [
                'line' => $line,
                'shipping_class_id' => $shippingClass?->is_active ? $shippingClass->getKey() : null,
                'weight_kg' => $weightKg,
                'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
            ];
        });

        $missingClass = $resolvedLines->first(fn (array $resolved): bool => ! $resolved['shipping_class_id']);
        if ($missingClass) {
            throw ValidationException::withMessages([
                'shipping_region_id' => ($missingClass['line']['name'] ?? 'একটি পণ্য').'–এর Shipping Class নির্ধারণ করা নেই।',
            ]);
        }

        $missingWeight = $resolvedLines->first(fn (array $resolved): bool => ! is_numeric($resolved['weight_kg']) || $resolved['weight_kg'] <= 0);
        if ($missingWeight) {
            throw ValidationException::withMessages([
                'shipping_region_id' => ($missingWeight['line']['name'] ?? 'একটি পণ্য').'–এর সঠিক Weight দিন, যাতে শিপিং চার্জ হিসাব করা যায়।',
            ]);
        }

        $classTotals = $resolvedLines
            ->groupBy('shipping_class_id')
            ->map(fn (Collection $lines): array => [
                'quantity' => (int) $lines->sum('quantity'),
                'weight_kg' => (float) $lines->sum(fn (array $resolved): float => (float) $resolved['weight_kg'] * $resolved['quantity']),
            ]);
        $classIds = $classTotals->keys();

        return ShippingRegion::query()
            ->active()
            ->with(['rates' => fn ($query) => $query
                ->where('is_active', true)
                ->whereNotNull('shipping_partner_id')
                ->whereIn('shipping_class_id', $classIds)
                ->with(['shippingClass:id,name', 'partner:id,name,estimated_min_days,estimated_max_days,inside_city_first_kg_charge,outside_city_first_kg_charge,additional_kg_charge'])])
            ->orderBy('sort_order')
            ->orderBy('division')
            ->orderBy('district')
            ->orderBy('upazila')
            ->get()
            ->mapWithKeys(function (ShippingRegion $region) use ($classTotals): array {
                $rates = $region->rates->keyBy('shipping_class_id');
                if ($classTotals->keys()->contains(fn ($classId): bool => ! $rates->has($classId))) {
                    return [];
                }

                $breakdown = $classTotals->map(function (array $totals, int $classId) use ($rates, $region): array {
                    $rate = $rates->get($classId);
                    $partner = $rate->partner;
                    $firstKgCharge = (float) ($region->is_inside_city
                        ? $partner?->inside_city_first_kg_charge
                        : $partner?->outside_city_first_kg_charge);
                    $additionalKgCharge = (float) ($partner?->additional_kg_charge ?? 0);
                    $chargeableKg = max(1, (int) ceil(round($totals['weight_kg'], 6)));
                    $amount = $firstKgCharge + max(0, $chargeableKg - 1) * $additionalKgCharge;
                    $minimumDays = max(1, (int) ($partner?->estimated_min_days ?? 1));
                    $maximumDays = max($minimumDays, (int) ($partner?->estimated_max_days ?? 3));

                    return [
                        'shipping_class_id' => $classId,
                        'shipping_class' => $rate->shippingClass->name,
                        'quantity' => $totals['quantity'],
                        'total_weight_kg' => round($totals['weight_kg'], 3),
                        'chargeable_weight_kg' => $chargeableKg,
                        'inside_city' => (bool) $region->is_inside_city,
                        'first_kg_charge' => $firstKgCharge,
                        'additional_kg_charge' => $additionalKgCharge,
                        'amount' => round($amount, 2),
                        'partner_id' => $rate->shipping_partner_id,
                        'partner' => $partner?->name,
                        'estimated_min_days' => $minimumDays,
                        'estimated_max_days' => $maximumDays,
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
                    'is_inside_city' => (bool) $region->is_inside_city,
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
