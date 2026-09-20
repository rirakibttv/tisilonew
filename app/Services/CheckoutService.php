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
    public function __construct(private readonly FlashSalePricingService $flashSalePricing) {}

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
                'checkout_reference' => $customer['checkout_reference'] ?? null,
                'user_id' => array_key_exists('user_id', $customer) ? $customer['user_id'] : auth()->id(),
                'landing_page_id' => $customer['landing_page_id'] ?? null,
                'customer_name' => $customer['customer_name'],
                'customer_email' => $customer['customer_email'] ?? null,
                'customer_phone' => $customer['customer_phone'],
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Unpaid,
                'payment_method' => $customer['payment_method'],
                'channel' => $customer['channel'] ?? 'website',
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

        $unitPrice = $this->flashSalePricing->priceFor(
            $product,
            (float) ($listingItem->sale_price ?? $listingItem->regular_price),
        );
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

        $unitPrice = $this->flashSalePricing->priceFor(
            $product,
            (float) ($variation?->sale_price ?? $variation?->regular_price ?? $product->sale_price ?? $product->regular_price),
        );

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

    public function cancelUnpaid(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($lockedOrder->payment_status === PaymentStatus::Paid || $lockedOrder->status === OrderStatus::Cancelled) {
                return $lockedOrder;
            }

            $items = $lockedOrder->items()->with(['product', 'productVariation'])->get();
            foreach ($items as $item) {
                if ($item->vendor_listing_item_id) {
                    $this->releaseVendorReservations($item);
                } elseif ($item->product_variation_id) {
                    $variation = ProductVariation::query()->lockForUpdate()->find($item->product_variation_id);
                    $variation?->increment('stock_quantity', $item->quantity);
                    $variation?->update(['stock_status' => 'in_stock']);
                } elseif ($item->product?->manage_stock) {
                    $product = Product::query()->lockForUpdate()->find($item->product_id);
                    $product?->increment('stock_quantity', $item->quantity);
                    $product?->update(['stock_status' => 'in_stock']);
                }
            }

            $lockedOrder->update([
                'status' => OrderStatus::Cancelled,
                'payment_status' => PaymentStatus::Failed,
            ]);

            return $lockedOrder->fresh();
        }, attempts: 3);
    }

    private function releaseVendorReservations(OrderItem $item): void
    {
        $reservations = InventoryMovement::query()
            ->where('reference_type', $item->getMorphClass())
            ->where('reference_id', $item->getKey())
            ->where('type', InventoryMovementType::Reservation->value)
            ->get();

        foreach ($reservations as $reservation) {
            $idempotencyKey = "payment-cancel-order-item-{$item->getKey()}-stock-{$reservation->inventory_stock_id}";
            if (InventoryMovement::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                continue;
            }

            $stock = InventoryStock::query()->lockForUpdate()->find($reservation->inventory_stock_id);
            if (! $stock) {
                continue;
            }

            $quantityBefore = $stock->quantity;
            $reservedBefore = $stock->reserved_quantity;
            $released = min($reservedBefore, max(0, (int) $reservation->reserved_delta));
            $reservedAfter = $reservedBefore - $released;
            $stock->forceFill(['reserved_quantity' => $reservedAfter])->save();

            InventoryMovement::query()->create([
                'inventory_stock_id' => $stock->getKey(),
                'type' => InventoryMovementType::ReservationRelease,
                'reserved_delta' => -$released,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityBefore,
                'reserved_before' => $reservedBefore,
                'reserved_after' => $reservedAfter,
                'reference_type' => $item->getMorphClass(),
                'reference_id' => $item->getKey(),
                'idempotency_key' => $idempotencyKey,
                'reason' => 'Release inventory after unsuccessful online payment',
                'created_at' => now(),
            ]);
        }
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
