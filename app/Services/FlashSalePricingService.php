<?php

namespace App\Services;

use App\Models\FlashSaleItem;
use App\Models\Product;
use Illuminate\Support\Collection;

class FlashSalePricingService
{
    /** @var Collection<int, FlashSaleItem>|null */
    private ?Collection $activeItems = null;

    public function activeItemFor(Product|int $product): ?FlashSaleItem
    {
        $productId = $product instanceof Product ? $product->getKey() : $product;

        return $this->activeItems()->get((int) $productId);
    }

    public function priceFor(Product|int $product, float $fallbackPrice): float
    {
        $flashPrice = (float) ($this->activeItemFor($product)?->flash_price ?? 0);

        return $flashPrice > 0 ? $flashPrice : $fallbackPrice;
    }

    /** @param array<string, mixed> $summary
     * @return array<string, mixed>
     */
    public function applyToSummary(array $summary): array
    {
        $item = $this->activeItemFor($summary['product']);
        if (! $item || (float) $item->flash_price <= 0) {
            $summary['is_flash_sale'] = false;

            return $summary;
        }

        $originalPrice = (float) $summary['price'];
        $regularPrice = max((float) $summary['regular_price'], $originalPrice);
        $flashPrice = (float) $item->flash_price;

        $summary['price'] = $flashPrice;
        $summary['regular_price'] = $regularPrice;
        $summary['discount'] = $regularPrice > $flashPrice && $regularPrice > 0
            ? (int) round((($regularPrice - $flashPrice) / $regularPrice) * 100)
            : 0;
        $summary['is_flash_sale'] = true;
        $summary['flash_sale_id'] = $item->flash_sale_id;

        return $summary;
    }

    /** @return Collection<int, FlashSaleItem> */
    private function activeItems(): Collection
    {
        if ($this->activeItems !== null) {
            return $this->activeItems;
        }

        return $this->activeItems = FlashSaleItem::query()
            ->select('flash_sale_items.*')
            ->join('flash_sales', 'flash_sales.id', '=', 'flash_sale_items.flash_sale_id')
            ->where('flash_sale_items.is_active', true)
            ->where('flash_sales.is_active', true)
            ->where('flash_sales.starts_at', '<=', now())
            ->where('flash_sales.ends_at', '>', now())
            ->orderByDesc('flash_sales.starts_at')
            ->orderBy('flash_sale_items.sort_order')
            ->get()
            ->unique('product_id')
            ->keyBy(fn (FlashSaleItem $item): int => (int) $item->product_id);
    }
}
