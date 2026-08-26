<?php

namespace Tests\Feature;

use App\Enums\IncompleteOrderStatus;
use App\Enums\VendorListingStatus;
use App\Enums\VendorStatus;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Models\VendorListingItem;
use App\Models\VendorWarehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        SiteSetting::forget('shipping');
        parent::tearDown();
    }

    public function test_customer_can_checkout_and_create_a_pending_order(): void
    {
        $this->shippingSettings();
        $product = $this->product('Checkout Product', 1250, 10);

        $this->post(route('store.cart.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertRedirect(route('store.cart.index'));

        $this->get(route('store.checkout.index'))
            ->assertOk()
            ->assertSee('অর্ডার সম্পন্ন করুন')
            ->assertSee('Checkout Product');

        $response = $this->post(route('store.checkout.store'), $this->checkoutData());
        $order = Order::query()->sole();

        $response->assertRedirect();
        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('আপনার অর্ডারটি গ্রহণ করা হয়েছে');

        $this->assertSame('pending', $order->status->value);
        $this->assertSame('cod', $order->payment_method);
        $this->assertSame('2580.00', $order->total_amount);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'total_amount' => 2500,
        ]);
        $this->assertDatabaseHas('incomplete_orders', [
            'converted_order_id' => $order->id,
            'status' => IncompleteOrderStatus::Converted->value,
        ]);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertEmpty(session('store_cart', []));
    }

    public function test_vendor_checkout_reserves_vendor_inventory(): void
    {
        $this->shippingSettings();
        $product = $this->product('Vendor Checkout Product', 1500, 0);
        $owner = User::factory()->create();
        $vendor = Vendor::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Checkout Vendor',
            'slug' => 'checkout-vendor-'.Str::lower(Str::random(6)),
            'status' => VendorStatus::Active,
            'commission_rate' => 10,
        ]);
        $listing = VendorListing::query()->create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => VendorListingStatus::Approved,
        ]);
        $listingItem = VendorListingItem::query()->create([
            'vendor_listing_id' => $listing->id,
            'seller_sku' => 'CHECKOUT-'.Str::upper(Str::random(8)),
            'regular_price' => 1400,
            'status' => 'active',
            'is_default' => true,
        ]);
        $warehouse = VendorWarehouse::query()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Checkout Warehouse',
            'code' => 'CHECKOUT',
            'address_line_1' => 'Dhaka',
            'district' => 'Dhaka',
            'is_default' => true,
            'status' => true,
        ]);
        $stock = InventoryStock::query()->create([
            'vendor_listing_item_id' => $listingItem->id,
            'vendor_warehouse_id' => $warehouse->id,
            'quantity' => 5,
        ]);

        $this->post(route('store.cart.store'), [
            'product_id' => $product->id,
            'vendor_listing_item_id' => $listingItem->id,
            'quantity' => 2,
        ])->assertRedirect(route('store.cart.index'));

        $this->post(route('store.checkout.store'), $this->checkoutData())->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame(2, $stock->fresh()->reserved_quantity);
        $this->assertSame(1, Order::query()->vendorOrders()->count());
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'vendor_listing_item_id' => $listingItem->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'reference_type' => 'App\\Models\\OrderItem',
            'type' => 'reservation',
            'reserved_delta' => 2,
        ]);
    }

    /** @return array<string, mixed> */
    private function checkoutData(): array
    {
        return [
            'customer_name' => 'Checkout Customer',
            'customer_phone' => '01700000000',
            'customer_email' => 'checkout@example.com',
            'address_line' => 'House 10, Road 5',
            'district' => 'Dhaka',
            'upazila' => 'Mirpur',
            'postal_code' => '1216',
            'shipping_zone' => 'Inside Dhaka',
            'payment_method' => 'cod',
            'terms' => '1',
        ];
    }

    private function shippingSettings(): void
    {
        SiteSetting::put('shipping', [
            'zones' => [[
                'name' => 'Inside Dhaka',
                'amount' => 80,
                'estimated_days' => 2,
                'status' => true,
            ]],
        ]);
    }

    private function product(string $name, float $price, int $stock): Product
    {
        return Product::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'product_type' => 'simple',
            'regular_price' => $price,
            'stock_quantity' => $stock,
            'stock_status' => $stock > 0 ? 'in_stock' : 'out_of_stock',
            'status' => 'published',
        ]);
    }
}
