<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\VendorListingItemStatus;
use App\Enums\VendorListingStatus;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\VendorListingItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    /**
     * @param  Collection<string, array<string, mixed>>  $cart
     * @param  array<string, mixed>  $customer
     * @param  array<string, mixed>  $shippingQuote
     */
    public function place(Collection $cart, array $customer, array $shippingQuote): Order
    {
        if ($cart->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'আপনার কার্ট খালি।']);
        }

        return DB::transaction(function () use ($cart, $customer, $shippingQuote): Order {
            $shippingAmount = round((float) ($shippingQuote['amount'] ?? 0), 2);
            $order = Order::query()->create([
                'user_id' => auth()->id(),
                'landing_page_id' => $customer['landing_page_id'] ?? null,
                'customer_name' => $customer['customer_name'],
                'customer_email' => $customer['customer_email'] ?? null,
                'customer_phone' => $customer['customer_phone'],
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Unpaid,
                'payment_method' => $customer['payment_method'],
                'channel' => 'website',
                'shipping_zone' => $shippingQuote['name'],
                'shipping_region_id' => $shippingQuote['region_id'],
                'shipping_partner_id' => $shippingQuote['partner_id'],
                'shipping_amount' => $shippingAmount,
                'shipping_address' => Arr::only($customer, [
                    'address_line',
                    'division',
                    'district',
                    'upazila',
                    'postal_code',
                ]),
                'shipping_breakdown' => [
                    'estimated_min_days' => $shippingQuote['estimated_min_days'],
                    'estimated_max_days' => $shippingQuote['estimated_max_days'],
                    'partners' => $shippingQuote['partners'],
                    'classes' => $shippingQuote['breakdown'],
                ],
                'marketing_attribution' => $customer['marketing_attribution'] ?? null,
                'notes' => $customer['notes'] ?? null,
            ]);

            $subtotal = 0.0;

            foreach ($cart as $key => $line) {
                $quantity = max(1, min(99, (int) ($line['quantity'] ?? 1)));
                $product = Product::query()
                    ->where('status', 'published')
                    ->lockForUpdate()
                    ->find($line['product_id'] ?? null);

                if (! $product) {
                    throw ValidationException::withMessages(['cart' => 'কার্টের একটি পণ্য এখন আর পাওয়া যাচ্ছে না।']);
                }

                $item = str_starts_with((string) $key, 'market-')
                    ? $this->marketplaceItem($order, $product, (string) $key, $line, $quantity)
                    : $this->catalogItem($order, $product, (string) $key, $line, $quantity);

                $subtotal += (float) $item->total_amount;
            }

            $order->forceFill([
                'subtotal_amount' => $subtotal,
                'total_amount' => $subtotal + $shippingAmount,
            ])->save();

            return $order->fresh(['items.vendor']);
        }, attempts: 3);
    }

    /** @param array<string, mixed> $line */
    private function marketplaceItem(Order $order, Product $product, string $key, array $line, int $quantity): OrderItem
    {
        $itemId = (int) ($line['vendor_listing_item_id'] ?? str($key)->after('market-')->toString());
        $listingItem = VendorListingItem::query()
            ->whereKey($itemId)
            ->where('status', VendorListingItemStatus::Active->value)
            ->whereHas('listing', fn ($query) => $query
                ->where('product_id', $product->getKey())
                ->where('status', VendorListingStatus::Approved->value))
            ->with('listing')
            ->lockForUpdate()
            ->first();

        if (! $listingItem) {
            throw ValidationException::withMessages(['cart' => "{$product->name} এখন অর্ডারের জন্য পাওয়া যাচ্ছে না।"]);
        }

        $stocks = InventoryStock::query()
            ->where('vendor_listing_item_id', $listingItem->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $available = $stocks->sum(fn (InventoryStock $stock): int => $stock->available_quantity);

        if (! $listingItem->backorders_allowed && $available < $quantity) {
            throw ValidationException::withMessages(['cart' => "{$product->name}-এর পর্যাপ্ত স্টক নেই।"]);
        }

        $unitPrice = (float) ($listingItem->sale_price ?? $listingItem->regular_price);
        $orderItem = $order->items()->create([
            'product_id' => $product->getKey(),
            'product_variation_id' => $listingItem->product_variation_id,
            'vendor_id' => $listingItem->vendor_id,
            'vendor_listing_item_id' => $listingItem->getKey(),
            'product_name' => $product->name,
            'sku' => $listingItem->seller_sku,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => $unitPrice * $quantity,
            'metadata' => ['option' => $line['option'] ?? null],
        ]);

        $this->reserveVendorStock($stocks, $listingItem, $orderItem, $quantity);

        return $orderItem;
    }

    /** @param array<string, mixed> $line */
    private function catalogItem(Order $order, Product $product, string $key, array $line, int $quantity): OrderItem
    {
        $variationId = $line['product_variation_id'] ?? null;
        if (! $variationId && preg_match('/^catalog-\d+-(\d+)$/', $key, $matches)) {
            $variationId = (int) $matches[1];
        }

        $variation = null;
        if ($product->product_type === 'variable') {
            $variation = ProductVariation::query()
                ->where('product_id', $product->getKey())
                ->where('status', true)
                ->lockForUpdate()
                ->find($variationId);

            if (! $variation || $variation->stock_quantity < $quantity) {
                throw ValidationException::withMessages(['cart' => "{$product->name}-এর নির্বাচিত ভ্যারিয়েশনের পর্যাপ্ত স্টক নেই।"]);
            }

            $variation->decrement('stock_quantity', $quantity);
            if ($variation->fresh()->stock_quantity === 0) {
                $variation->update(['stock_status' => 'out_of_stock']);
            }
        } elseif ($product->manage_stock) {
            if ($product->stock_quantity < $quantity) {
                throw ValidationException::withMessages(['cart' => "{$product->name}-এর পর্যাপ্ত স্টক নেই।"]);
            }

            $product->decrement('stock_quantity', $quantity);
            if ($product->fresh()->stock_quantity === 0) {
                $product->update(['stock_status' => 'out_of_stock']);
            }
        }

        $unitPrice = (float) ($variation?->sale_price ?? $variation?->regular_price ?? $product->sale_price ?? $product->regular_price);

        return $order->items()->create([
            'product_id' => $product->getKey(),
            'product_variation_id' => $variation?->getKey(),
            'product_name' => $product->name,
            'sku' => $variation?->sku ?? $product->sku,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => $unitPrice * $quantity,
            'metadata' => ['option' => $line['option'] ?? null],
        ]);
    }

    /** @param Collection<int, InventoryStock> $stocks */
    private function reserveVendorStock(Collection $stocks, VendorListingItem $listingItem, OrderItem $orderItem, int $quantity): void
    {
        $remaining = $quantity;

        foreach ($stocks as $stock) {
            $reserve = min($remaining, $stock->available_quantity);
            if ($reserve <= 0) {
                continue;
            }

            $this->recordReservation($stock, $orderItem, $reserve);
            $remaining -= $reserve;

            if ($remaining === 0) {
                break;
            }
        }

        if ($remaining > 0 && $listingItem->backorders_allowed && $stocks->isNotEmpty()) {
            $this->recordReservation($stocks->first(), $orderItem, $remaining);
        }
    }

    private function recordReservation(InventoryStock $stock, OrderItem $orderItem, int $quantity): void
    {
        $quantityBefore = $stock->quantity;
        $reservedBefore = $stock->reserved_quantity;
        $stock->forceFill(['reserved_quantity' => $reservedBefore + $quantity])->save();

        InventoryMovement::query()->create([
            'inventory_stock_id' => $stock->getKey(),
            'type' => InventoryMovementType::Reservation,
            'reserved_delta' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityBefore,
            'reserved_before' => $reservedBefore,
            'reserved_after' => $reservedBefore + $quantity,
            'reference_type' => $orderItem->getMorphClass(),
            'reference_id' => $orderItem->getKey(),
            'idempotency_key' => "checkout-order-item-{$orderItem->getKey()}-stock-{$stock->getKey()}",
            'reason' => 'Website checkout reservation',
            'created_at' => now(),
        ]);
    }
}
