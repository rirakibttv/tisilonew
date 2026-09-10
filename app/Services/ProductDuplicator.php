<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductDuplicator
{
    public function duplicate(Product $product): Product
    {
        $product->loadMissing([
            'tags:id',
            'attributes:id',
            'variations.attributeValues:id',
        ]);

        return DB::transaction(function () use ($product): Product {
            $duplicate = $product->replicate();
            $duplicate->forceFill([
                'name' => $product->name.' (Copy)',
                'slug' => $this->uniqueValue(
                    Product::class,
                    'slug',
                    Str::slug($product->slug.'-copy'),
                ),
                'sku' => $this->duplicateCode(Product::class, 'sku', $product->sku),
                'barcode' => $this->duplicateCode(Product::class, 'barcode', $product->barcode),
                'status' => 'draft',
                'featured' => false,
            ]);
            $duplicate->save();

            $duplicate->tags()->sync($product->tags->modelKeys());
            $duplicate->attributes()->sync($product->attributes->modelKeys());

            foreach ($product->variations as $variation) {
                $duplicateVariation = $variation->replicate();
                $duplicateVariation->forceFill([
                    'product_id' => $duplicate->getKey(),
                    'sku' => $this->duplicateCode(ProductVariation::class, 'sku', $variation->sku),
                    'barcode' => $this->duplicateCode(ProductVariation::class, 'barcode', $variation->barcode),
                ]);
                $duplicateVariation->save();
                $duplicateVariation->attributeValues()->sync($variation->attributeValues->modelKeys());
            }

            return $duplicate->load(['tags', 'attributes', 'variations.attributeValues']);
        });
    }

    /** @param class-string<Product|ProductVariation> $model */
    private function duplicateCode(string $model, string $column, ?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return $this->uniqueValue($model, $column, $value.'-COPY');
    }

    /** @param class-string<Product|ProductVariation> $model */
    private function uniqueValue(string $model, string $column, string $base): string
    {
        $candidate = Str::limit($base, 255, '');
        $suffix = 2;

        while ($model::query()->where($column, $candidate)->exists()) {
            $tail = '-'.$suffix++;
            $candidate = Str::limit($base, 255 - strlen($tail), '').$tail;
        }

        return $candidate;
    }
}
