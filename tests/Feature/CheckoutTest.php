<?php

namespace Tests\Feature;

use App\Enums\IncompleteOrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\VendorListingStatus;
use App\Enums\VendorStatus;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ShippingClass;
use App\Models\ShippingPartner;
use App\Models\ShippingRegion;
use App\Models\ShippingRegionRate;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Models\VendorListingItem;
use App\Models\VendorWarehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use DatabaseTransactions;

    private int $shippingClassId;

    private int $shippingRegionId;

    private int $shippingPartnerId;

    protected function tearDown(): void
    {
        SiteSetting::forget('payment');
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
            ->assertSee('Checkout Product')
            ->assertSee('name="district_search"', false)
            ->assertSee('data-district-options', false)
            ->assertSee('name="thana"', false)
            ->assertDontSee('name="customer_email"', false)
            ->assertDontSee('name="terms"', false)
            ->assertDontSee('আমি অর্ডার, ডেলিভারি ও রিটার্ন সংক্রান্ত শর্তাবলিতে সম্মত।');

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
        $this->assertSame($this->shippingRegionId, $order->shipping_region_id);
        $this->assertNull($order->customer_email);
        $this->assertSame('Dhaka', $order->shipping_address['district']);
        $this->assertSame('Mirpur Model', $order->shipping_address['upazila']);
        $this->assertSame('Standard', $order->shipping_breakdown['classes'][0]['shipping_class']);
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

    public function test_checkout_shows_cod_and_bkash_and_completes_a_bkash_payment(): void
    {
        $this->enableBkash();
        $this->shippingSettings();
        $product = $this->product('bKash Checkout Product', 1250, 10);
        $this->post(route('store.cart.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect(route('store.cart.index'));

        $this->get(route('store.checkout.index'))
            ->assertOk()
            ->assertSee('value="cod"', false)
            ->assertSee('value="bkash"', false)
            ->assertSee('ক্যাশ অন ডেলিভারি')
            ->assertSee('bKash');

        Http::fake(function (HttpRequest $request) {
            if (str_ends_with($request->url(), '/tokenized/checkout/token/grant')) {
                return Http::response(['id_token' => 'test-bkash-token', 'expires_in' => 3600], 200);
            }
            if (str_ends_with($request->url(), '/tokenized/checkout/create')) {
                return Http::response([
                    'paymentID' => 'TEST-BKASH-PAYMENT',
                    'bkashURL' => 'https://tokenized.sandbox.bka.sh/checkout/test-payment',
                    'transactionStatus' => 'Initiated',
                    'statusCode' => '0000',
                ], 200);
            }
            if (str_ends_with($request->url(), '/tokenized/checkout/execute')) {
                $order = Order::query()->sole();

                return Http::response([
                    'paymentID' => 'TEST-BKASH-PAYMENT',
                    'trxID' => 'TEST-TRX-123',
                    'transactionStatus' => 'Completed',
                    'amount' => $order->total_amount,
                    'currency' => $order->currency,
                    'merchantInvoiceNumber' => $order->order_number,
                    'statusCode' => '0000',
                ], 200);
            }

            return Http::response([], 404);
        });

        $response = $this->post(route('store.checkout.store'), [
            ...$this->checkoutData(),
            'payment_method' => 'bkash',
        ]);
        $response->assertRedirect('https://tokenized.sandbox.bka.sh/checkout/test-payment');
        $order = Order::query()->sole();
        $transaction = PaymentTransaction::query()->sole();
        $this->assertSame('bkash', $order->payment_method);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame('initiated', $transaction->status);
        $this->assertNotEmpty(session('store_cart'));

        $callback = $this->get(route('store.payments.bkash.callback', [
            'paymentID' => 'TEST-BKASH-PAYMENT',
            'status' => 'success',
        ]));

        $callback->assertRedirect();
        $this->get($callback->headers->get('Location'))
            ->assertOk()
            ->assertSee('bKash')
            ->assertSee($order->order_number);
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertSame('completed', $transaction->fresh()->status);
        $this->assertSame('TEST-TRX-123', $transaction->fresh()->transaction_id);
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
            'shipping_class_id' => $vendorShippingClass = ShippingClass::query()->create([
                'name' => 'Vendor Fragile',
                'code' => 'vendor-fragile-'.Str::lower(Str::random(6)),
                'is_active' => true,
            ])->id,
            'status' => VendorListingStatus::Approved,
        ]);
        ShippingRegionRate::query()->create([
            'shipping_region_id' => $this->shippingRegionId,
            'shipping_class_id' => $vendorShippingClass,
            'shipping_partner_id' => $this->shippingPartnerId,
            'base_charge' => 120,
            'additional_item_charge' => 10,
            'estimated_min_days' => 1,
            'estimated_max_days' => 3,
            'is_active' => true,
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
        $this->assertSame('2930.00', $order->total_amount);
        $this->assertSame('Vendor Fragile', $order->shipping_breakdown['classes'][0]['shipping_class']);
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
            'address_line' => 'House 10, Road 5',
            'district_search' => 'Dhaka',
            'thana' => 'Mirpur Model',
            'shipping_region_id' => $this->shippingRegionId,
            'payment_method' => 'cod',
        ];
    }

    private function shippingSettings(): void
    {
        $class = ShippingClass::query()->create([
            'name' => 'Standard',
            'code' => 'test-standard-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        $partner = ShippingPartner::query()->create([
            'name' => 'Test Courier',
            'code' => 'test-courier-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        $region = ShippingRegion::query()->create([
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'upazila' => 'Mirpur',
            'postal_code' => '1216',
            'location_key' => 'test-mirpur-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        ShippingRegionRate::query()->create([
            'shipping_region_id' => $region->id,
            'shipping_class_id' => $class->id,
            'shipping_partner_id' => $partner->id,
            'base_charge' => 80,
            'additional_item_charge' => 0,
            'estimated_min_days' => 1,
            'estimated_max_days' => 2,
            'is_active' => true,
        ]);

        $this->shippingClassId = $class->id;
        $this->shippingRegionId = $region->id;
        $this->shippingPartnerId = $partner->id;
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
            'shipping_class_id' => $this->shippingClassId,
        ]);
    }

    private function enableBkash(): void
    {
        SiteSetting::put('payment', [
            'cod_enabled' => true,
            'bkash_enabled' => true,
            'bkash_environment' => 'sandbox',
            'bkash_username' => 'sandbox-user',
            'default_gateway' => 'cod',
            'currency' => 'BDT',
        ], [
            'bkash_password' => 'sandbox-password',
            'bkash_app_key' => 'test-app-key-'.Str::random(12),
            'bkash_app_secret' => 'sandbox-secret',
        ]);
    }
}
