<?php

namespace App\Services;

use App\Models\ShippingRegion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShippingRegionDuplicator
{
    public function duplicate(ShippingRegion $region): ShippingRegion
    {
        $region->loadMissing('rates');

        return DB::transaction(function () use ($region): ShippingRegion {
            // Filament's table query appends `rates_count` via withCount(). It is
            // not a real shipping_regions column and must never be replicated.
            $duplicate = $region->replicate(['rates_count']);
            $duplicate->forceFill([
                'upazila' => $this->uniqueUpazilaName($region),
                'location_key' => null,
                'is_active' => false,
            ]);
            $duplicate->save();

            foreach ($region->rates as $rate) {
                $duplicateRate = $rate->replicate();
                $duplicateRate->shipping_region_id = $duplicate->getKey();
                $duplicateRate->save();
            }

            return $duplicate->load('rates');
        });
    }

    private function uniqueUpazilaName(ShippingRegion $region): string
    {
        $base = $region->upazila.' (Copy';
        $copyNumber = 1;

        do {
            $suffix = $copyNumber === 1 ? ')' : ' '.$copyNumber.')';
            $candidate = Str::limit($base, 120 - mb_strlen($suffix), '').$suffix;
            $copyNumber++;
        } while (ShippingRegion::query()
            ->where('division', $region->division)
            ->where('district', $region->district)
            ->where('upazila', $candidate)
            ->exists());

        return $candidate;
    }
}
