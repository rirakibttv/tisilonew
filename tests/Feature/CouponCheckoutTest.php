<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ShippingClass;
use App\Models\ShippingPartner;
use App\Models\ShippingRegion;
use App\Models\ShippingRegionRate;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class CouponCheckoutTest extends TestCase
{
    use DatabaseTransactions;

    private int $shippingClassId;

    private int $shippingRegionId;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $class = ShippingClass::query()->create([
            'name' => 'Coupon Standard',
            'code' => 'coupon-standard-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);
        $partner = ShippingPartner::query()->create([
            'name' => 'Coupon Courier',
            'code' => 'coupon-courier-'.Str::lower(Str::random(8)),
            'estimated_min_days' => 1,
            'estimated_max_days' => 2,
            'is_active' => true,
        ]);
        $region = ShippingRegion::query()->create([
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'upazila' => 'Mirpur',
            'postal_code' => '1216',
            'location_key' => 'coupon-dhaka-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);
        ShippingRegionRate::query()->create([
            'shipping_region_id' => $region->id,
            'shipping_class_id' => $class->id,
            'shipping_partner_id' => $partner->id,
            'base_charge' => 80,
            'additional_item_charge' => 0,
            'is_active' => true,
        ]);

        $this->shippingClassId = $class->id;
        $this->shippingRegionId = $region->id;
    }

    protected function tearDown(): void
    {
        SiteSetting::forget('payment');

        parent::tearDown();
    }

    public function test_customer_can_apply_coupon_and_discount_is_revalidated_on_order(): void
    {
        $coupon = Coupon::query()->create([
            'code' => 'SAVE500',
            'discount_type' => 'fixed',
            'discount_value' => 500,
            'minimum_order_amount' => 1500,
            'usage_limit' => 10,
            'usage_limit_per_customer' => 1,
            'is_active' => true,
        ]);
        $this->addProductToCart(1000, 2);

        $this->from(route('store.checkout.index'))
            ->post(route('store.checkout.coupon.store'), ['coupon_code' => 'save500'])
            ->assertRedirect(route('store.checkout.index'))
            ->assertSessionHas('store_coupon_code', 'SAVE500');

        $this->get(route('store.checkout.index'))
            ->assertOk()
            ->assertSee('SAVE500')
            ->assertSee('Coupon Discount');

        $this->post(route('store.checkout.store'), $this->checkoutData())->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertSame('SAVE500', $order->coupon_code);
        $this->assertSame('2000.00', $order->subtotal_amount);
        $this->assertSame('500.00', $order->discount_amount);
        $this->assertSame('1580.00', $order->total_amount);
        $this->assertDatabaseHas('coupon_usages', [
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'discount_amount' => 500,
        ]);
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertNull(session('store_coupon_code'));
    }

    public function test_percentage_coupon_respects_maximum_discount(): void
    {
        $coupon = Coupon::query()->create([
            'code' => 'TWENTY',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'maximum_discount_amount' => 300,
            'is_active' => true,
        ]);
        $this->addProductToCart(1000, 2);

        $this->post(route('store.checkout.coupon.store'), ['coupon_code' => $coupon->code]);
        $this->post(route('store.checkout.store'), $this->checkoutData())->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame('300.00', $order->discount_amount);
        $this->assertSame('1780.00', $order->total_amount);
    }

    public function test_expired_and_minimum_order_coupons_are_rejected(): void
    {
        Coupon::query()->create([
            'code' => 'EXPIRED',
            'discount_type' => 'fixed',
            'discount_value' => 100,
            'expires_at' => now()->subMinute(),
            'is_active' => true,
        ]);
        Coupon::query()->create([
            'code' => 'MINIMUM',
            'discount_type' => 'fixed',
            'discount_value' => 100,
            'minimum_order_amount' => 5000,
            'is_active' => true,
        ]);
        $this->addProductToCart(1000, 1);

        $this->from(route('store.checkout.index'))
            ->post(route('store.checkout.coupon.store'), ['coupon_code' => 'EXPIRED'])
            ->assertRedirect(route('store.checkout.index'))
            ->assertSessionHasErrors('coupon_code');
        $this->assertNull(session('store_coupon_code'));

        $this->from(route('store.checkout.index'))
            ->post(route('store.checkout.coupon.store'), ['coupon_code' => 'MINIMUM'])
            ->assertRedirect(route('store.checkout.index'))
            ->assertSessionHasErrors('coupon_code');
        $this->assertNull(session('store_coupon_code'));
    }

    public function test_admin_can_open_coupon_menu_list_and_create_pages(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(['Add New Coupon', 'All Coupon']);
        $this->actingAs($admin)->get('/admin/coupons')
            ->assertOk()
            ->assertSee('Coupons');
        $this->actingAs($admin)->get('/admin/coupons/create')
            ->assertOk()
            ->assertSee('Coupon Information')
            ->assertSee('Discount Rules')
            ->assertSee('Usage &amp; Schedule', false);
    }

    public function test_bkash_uses_discounted_total_and_keeps_coupon_usage_after_payment(): void
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
        $coupon = Coupon::query()->create([
            'code' => 'BKASH200',
            'discount_type' => 'fixed',
            'discount_value' => 200,
            'is_active' => true,
        ]);
        $this->addProductToCart(1000, 1);
        $this->post(route('store.checkout.coupon.store'), ['coupon_code' => $coupon->code]);

        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/tokenized/checkout/token/grant')) {
                return Http::response(['id_token' => 'coupon-bkash-token', 'expires_in' => 3600]);
            }
            if (str_ends_with($request->url(), '/tokenized/checkout/create')) {
                return Http::response([
                    'paymentID' => 'COUPON-BKASH-PAYMENT',
                    'bkashURL' => 'https://tokenized.sandbox.bka.sh/checkout/coupon-payment',
                    'transactionStatus' => 'Initiated',
                    'statusCode' => '0000',
                ]);
            }
            if (str_ends_with($request->url(), '/tokenized/checkout/execute')) {
                $order = PaymentTransaction::query()
                    ->where('gateway_payment_id', 'COUPON-BKASH-PAYMENT')
                    ->firstOrFail()
                    ->order;

                return Http::response([
                    'paymentID' => 'COUPON-BKASH-PAYMENT',
                    'trxID' => 'COUPON-TRX-123',
                    'transactionStatus' => 'Completed',
                    'amount' => $order->total_amount,
                    'currency' => $order->currency,
                    'merchantInvoiceNumber' => $order->order_number,
                    'statusCode' => '0000',
                ]);
            }

            return Http::response([
                'status' => 'success',
                'msg' => 'SMS Sent Successfully',
                'msg_id' => 'coupon-test',
            ]);
        });

        $response = $this->post(route('store.checkout.store'), [
            ...$this->checkoutData(),
            'payment_method' => 'bkash',
        ]);
        $response->assertRedirect('https://tokenized.sandbox.bka.sh/checkout/coupon-payment');

        $order = Order::query()->where('coupon_id', $coupon->id)->sole();
        $transaction = PaymentTransaction::query()
            ->where('gateway_payment_id', 'COUPON-BKASH-PAYMENT')
            ->sole();
        $this->assertSame('200.00', $order->discount_amount);
        $this->assertSame('880.00', $order->total_amount);
        $this->assertSame('880.00', $transaction->amount);
        $this->assertSame('BKASH200', session('store_coupon_code'));

        $this->get(route('store.payments.bkash.callback', [
            'paymentID' => 'COUPON-BKASH-PAYMENT',
            'status' => 'success',
        ]))->assertRedirect();

        $this->assertNull(session('store_coupon_code'));
        $this->assertDatabaseHas('coupon_usages', [
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
        ]);
    }

    public function test_per_customer_limit_is_enforced_again_at_final_checkout(): void
    {
        $coupon = Coupon::query()->create([
            'code' => 'ONCEONLY',
            'discount_type' => 'fixed',
            'discount_value' => 100,
            'usage_limit_per_customer' => 1,
            'is_active' => true,
        ]);

        $this->addProductToCart(1000, 1);
        $this->post(route('store.checkout.coupon.store'), ['coupon_code' => $coupon->code]);
        $this->post(route('store.checkout.store'), $this->checkoutData())->assertRedirect();

        $this->addProductToCart(1000, 1);
        $this->post(route('store.checkout.coupon.store'), ['coupon_code' => $coupon->code]);
        $this->from(route('store.checkout.index'))
            ->post(route('store.checkout.store'), $this->checkoutData())
            ->assertRedirect(route('store.checkout.index'))
            ->assertSessionHasErrors('coupon_code');

        $this->assertSame(1, Order::query()->where('coupon_id', $coupon->id)->count());
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertDatabaseCount('coupon_usages', 1);
    }

    public function test_cancelled_unpaid_order_releases_coupon_usage(): void
    {
        $coupon = Coupon::query()->create([
            'code' => 'REUSABLE',
            'discount_type' => 'fixed',
            'discount_value' => 100,
            'usage_limit' => 1,
            'is_active' => true,
        ]);
        $this->addProductToCart(1000, 1);
        $this->post(route('store.checkout.coupon.store'), ['coupon_code' => $coupon->code]);
        $this->post(route('store.checkout.store'), $this->checkoutData())->assertRedirect();

        $order = Order::query()->where('coupon_id', $coupon->id)->sole();
        app(CheckoutService::class)->cancelUnpaid($order);

        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertDatabaseMissing('coupon_usages', ['order_id' => $order->id]);
    }

    private function addProductToCart(float $price, int $quantity): void
    {
        $product = Product::query()->create([
            'name' => 'Coupon Product '.Str::random(6),
            'slug' => 'coupon-product-'.Str::lower(Str::random(8)),
            'product_type' => 'simple',
            'regular_price' => $price,
            'stock_quantity' => 20,
            'stock_status' => 'in_stock',
            'status' => 'published',
            'shipping_class_id' => $this->shippingClassId,
        ]);

        $this->post(route('store.cart.store'), [
            'product_id' => $product->id,
            'quantity' => $quantity,
        ])->assertRedirect(route('store.cart.index'));
    }

    /** @return array<string, mixed> */
    private function checkoutData(): array
    {
        return [
            'customer_name' => 'Coupon Customer',
            'customer_phone' => '01700000001',
            'address_line' => 'House 20, Road 5',
            'district_search' => 'Dhaka',
            'thana' => 'Mirpur',
            'shipping_region_id' => $this->shippingRegionId,
            'payment_method' => 'cod',
        ];
    }
}
