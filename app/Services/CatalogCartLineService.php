<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Validation\ValidationException;

class CatalogCartLineService
{
    /** @return array<string, mixed> */
    public function make(Product $product, ?int $variationId = null): array
    {
        $variation = null;

        if ($product->product_type === 'variable') {
            $variation = ProductVariation::query()
                ->where('product_id', $product->getKey())
                ->where('status', true)
                ->when($variationId, fn ($query) => $query->whereKey($variationId))
                ->when(! $variationId, fn ($query) => $query->orderByDesc('is_default')->orderBy('sort_order'))
                ->with('attributeValues.attribute:id,name')
                ->first();

            if ($variationId && ! $variation) {
                throw ValidationException::withMessages([
                    'product_variation_id' => 'নির্বাচিত ভ্যারিয়েশনটি পাওয়া যায়নি।',
                ]);
            }

            if (! $variation && (float) ($product->sale_price ?? $product->regular_price) <= 0) {
                throw ValidationException::withMessages([
                    'product_variation_id' => 'এই পণ্যের মূল্য বা ভ্যারিয়েশন এখনো প্রস্তুত নয়।',
                ]);
            }
        }

        return [
            'key' => 'catalog-'.$product->getKey().'-'.($variation?->getKey() ?? 'base'),
            'product_id' => $product->getKey(),
            'product_variation_id' => $variation?->getKey(),
            'vendor_listing_item_id' => null,
            'vendor_id' => null,
            'name' => $product->name,
            'slug' => $product->slug,
            'option' => $variation?->attributeValues
                ->map(fn ($value) => $value->attribute->name.': '.$value->value)
                ->join(', '),
            'vendor' => 'Tisilo',
            'sku' => $variation?->sku ?? $product->sku,
            'image' => $this->imageFor($product, $variation),
            'price' => (float) ($variation?->sale_price ?? $variation?->regular_price ?? $product->sale_price ?? $product->regular_price),
            'available' => $variation
                ? (int) $variation->stock_quantity
                : ($product->manage_stock ? (int) $product->stock_quantity : PHP_INT_MAX),
            'backorders_allowed' => ! $variation && ! $product->manage_stock,
        ];
    }

    private function imageFor(Product $product, ?ProductVariation $variation): ?string
    {
        $path = $variation?->image ?: $product->featured_image;

        return filled($path) ? asset('storage/'.ltrim($path, '/')) : null;
    }
}
