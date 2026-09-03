<?php

namespace App\Services;

use App\Models\LandingPage;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LandingCheckoutService
{
    public function options(LandingPage $campaign): array
    {
        return $campaign->products->map(function (Product $product): array {
            $variations = $product->variations->where('status', true)
                ->sortBy([['is_default', 'desc'], ['sort_order', 'asc']]);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'image' => $product->featured_image ? asset('storage/'.ltrim($product->featured_image, '/')) : null,
                'variable' => $product->product_type === 'variable',
                'price' => (float) ($product->sale_price ?? $product->regular_price),
                'available' => $product->stock_status === 'out_of_stock' ? 0 : ($product->manage_stock ? min(99, $product->stock_quantity) : 99),
                'variations' => $variations->map(fn (ProductVariation $variation): array => [
                    'id' => $variation->id,
                    'label' => $variation->attributeValues->isNotEmpty()
                        ? $variation->attributeValues->map(fn ($value) => $value->attribute->name.': '.$value->value)->join(' · ')
                        : ($variation->sku ?: 'Option '.$variation->id),
                    'price' => (float) ($variation->sale_price ?? $variation->regular_price),
                    'available' => $variation->stock_status === 'out_of_stock' ? 0 : min(99, $variation->stock_quantity),
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /** Build a standalone campaign cart from trusted catalog data, never the customer's store cart. */
    public function cart(LandingPage $campaign, array $selection): Collection
    {
        $product = $campaign->products()->where('products.status', 'published')
            ->find($selection['product_id']);
        if (! $product) {
            throw ValidationException::withMessages(['product_id' => 'এই অফারে নির্বাচিত পণ্যটি পাওয়া যাচ্ছে না।']);
        }

        $variation = null;
        if ($product->product_type === 'variable') {
            $variation = $product->variations()->where('status', true)
                ->with('attributeValues.attribute')->find($selection['product_variation_id'] ?? null);
            if (! $variation) {
                throw ValidationException::withMessages(['product_variation_id' => 'পণ্যের সঠিক অপশন নির্বাচন করুন।']);
            }
        } elseif (! empty($selection['product_variation_id'])) {
            throw ValidationException::withMessages(['product_variation_id' => 'এই পণ্যের জন্য ভ্যারিয়েশন প্রযোজ্য নয়।']);
        }

        $quantity = (int) $selection['quantity'];
        $price = (float) ($variation?->sale_price ?? $variation?->regular_price ?? $product->sale_price ?? $product->regular_price);
        $available = $variation ? $variation->stock_quantity : ($product->manage_stock ? $product->stock_quantity : 99);
        if ($quantity < 1 || $quantity > 99 || $available < $quantity || ($variation?->stock_status ?? $product->stock_status) === 'out_of_stock') {
            throw ValidationException::withMessages(['quantity' => 'নির্বাচিত পরিমাণের পর্যাপ্ত স্টক নেই।']);
        }
        if ($price <= 0) {
            throw ValidationException::withMessages(['product_id' => 'এই পণ্যের মূল্য এখনো প্রস্তুত নয়।']);
        }

        $key = 'catalog-'.$product->id.'-'.($variation?->id ?? 'base');

        return collect([$key => [
            'key' => $key,
            'product_id' => $product->id,
            'product_variation_id' => $variation?->id,
            'landing_page_id' => $campaign->id,
            'name' => $product->name,
            'option' => $variation?->attributeValues->map(fn ($value) => $value->attribute->name.': '.$value->value)->join(', '),
            'quantity' => $quantity,
            'price' => $price,
        ]]);
    }
}
