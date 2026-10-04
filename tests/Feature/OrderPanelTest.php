<?php

namespace Tests\Feature;

use App\Enums\IncompleteOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VendorStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ListPendingOrders;
use App\Models\IncompleteOrder;
use App\Models\Order;
use App\Models\ShippingPartner;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OrderPanelTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        SiteSetting::forget('courier');

        parent::tearDown();
    }

    public function test_order_panel_navigation_uses_the_requested_menu_order(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertSeeInOrder([
                'Pending Order',
                'Incomplete Order',
                'Vendor Order',
                'All Order',
            ]);
    }

    public function test_order_views_only_show_their_matching_orders(): void
    {
        $admin = $this->admin();
        $pending = $this->order(OrderStatus::Pending, 'Pending Customer');
        $delivered = $this->order(OrderStatus::Delivered, 'Delivered Customer');
        $vendorOrder = $this->order(OrderStatus::Processing, 'Vendor Customer');

        $vendor = Vendor::query()->create([
            'owner_id' => $admin->id,
            'name' => 'Order Vendor',
            'slug' => 'order-vendor-'.Str::lower(Str::random(8)),
            'status' => VendorStatus::Active,
            'commission_rate' => 10,
        ]);

        $vendorOrder->items()->create([
            'vendor_id' => $vendor->id,
            'product_name' => 'Vendor Product',
            'quantity' => 1,
            'unit_price' => 1200,
            'total_amount' => 1200,
        ]);

        $this->actingAs($admin)
            ->get('/admin/orders/pending')
            ->assertOk()
            ->assertSee($pending->order_number)
            ->assertDontSee($delivered->order_number)
            ->assertDontSee($vendorOrder->order_number);

        $this->actingAs($admin)
            ->get('/admin/orders/vendor')
            ->assertOk()
            ->assertSee($vendorOrder->order_number)
            ->assertDontSee($pending->order_number)
            ->assertDontSee($delivered->order_number);

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee($pending->order_number)
            ->assertSee($delivered->order_number)
            ->assertSee($vendorOrder->order_number);
    }

    public function test_order_list_has_the_shipping_columns_and_removes_legacy_columns(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ListOrders::class)
            ->assertTableColumnExists('confirmedBy.name')
            ->assertTableColumnExists('shippingPartner.name')
            ->assertTableColumnExists('tracking_number')
            ->assertTableColumnExists('shipping_status')
            ->assertTableColumnDoesNotExist('items_count')
            ->assertTableColumnDoesNotExist('payment_method')
            ->assertTableColumnDoesNotExist('shippingRegion.upazila')
            ->assertTableColumnDoesNotExist('placed_at');
    }

    public function test_incomplete_order_page_lists_recoverable_checkout_data(): void
    {
        $incomplete = IncompleteOrder::query()->create([
            'customer_name' => 'Recovery Customer',
            'customer_phone' => '01700000000',
            'items' => [['name' => 'Saved Product', 'quantity' => 2]],
            'total_amount' => 900,
            'status' => IncompleteOrderStatus::Incomplete,
            'last_activity_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/incomplete-orders')
            ->assertOk()
            ->assertSee('Incomplete Order')
            ->assertSee($incomplete->customer_name)
            ->assertSee($incomplete->customer_phone);
    }

    public function test_pending_order_can_be_confirmed_and_booked_with_steadfast(): void
    {
        Http::fake([
            'https://courier.example/create_order' => Http::response([
                'status' => 200,
                'consignment' => [
                    'consignment_id' => 987654,
                    'tracking_code' => 'SF-TISILO-1001',
                    'status' => 'in_review',
                ],
            ]),
        ]);

        SiteSetting::put('courier', [
            'steadfast_enabled' => true,
            'steadfast_endpoint' => 'https://courier.example',
        ], [
            'steadfast_api_key' => 'test-api-key',
            'steadfast_secret_key' => 'test-secret-key',
        ]);

        $partner = ShippingPartner::query()->create([
            'name' => 'Steadfast',
            'code' => 'steadfast-test-'.Str::lower(Str::random(6)),
            'api_provider' => 'steadfast',
            'is_active' => true,
        ]);
        $admin = $this->admin();
        $order = Order::query()->create([
            'customer_name' => 'Courier Customer',
            'customer_phone' => '+8801700000000',
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'payment_method' => 'cod',
            'subtotal_amount' => 1000,
            'total_amount' => 1120,
            'shipping_address' => [
                'address_line' => 'House 1, Road 2',
                'upazila' => 'Bagerhat Sadar',
                'district' => 'Bagerhat',
            ],
        ]);
        $order->items()->create([
            'product_name' => 'Test product',
            'quantity' => 2,
            'unit_price' => 500,
            'total_amount' => 1000,
        ]);

        Livewire::actingAs($admin)
            ->test(ListPendingOrders::class)
            ->callTableAction('confirmAndShip', $order, data: [
                'shipping_partner_id' => $partner->getKey(),
            ])
            ->assertHasNoTableActionErrors();

        $order->refresh();

        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame($admin->getKey(), $order->confirmed_by);
        $this->assertSame($partner->getKey(), $order->shipping_partner_id);
        $this->assertSame('SF-TISILO-1001', $order->tracking_number);
        $this->assertSame('in_review', $order->shipping_status);
        $this->assertNotNull($order->shipping_status_synced_at);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://courier.example/create_order'
            && $request->hasHeader('Api-Key', 'test-api-key')
            && $request->hasHeader('Secret-Key', 'test-secret-key')
            && $request['recipient_phone'] === '01700000000'
            && $request['total_lot'] === 2
            && (float) $request['cod_amount'] === 1120.0);
    }

    public function test_failed_courier_booking_does_not_confirm_the_order(): void
    {
        Http::fake([
            'https://courier.example/create_order' => Http::response([
                'message' => 'Courier rejected shipment',
            ], 422),
        ]);

        SiteSetting::put('courier', [
            'steadfast_enabled' => true,
            'steadfast_endpoint' => 'https://courier.example',
        ], [
            'steadfast_api_key' => 'test-api-key',
            'steadfast_secret_key' => 'test-secret-key',
        ]);

        $partner = ShippingPartner::query()->create([
            'name' => 'Steadfast',
            'code' => 'steadfast-failure-'.Str::lower(Str::random(6)),
            'api_provider' => 'steadfast',
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'customer_name' => 'Courier Customer',
            'customer_phone' => '01700000000',
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'payment_method' => 'cod',
            'subtotal_amount' => 1000,
            'total_amount' => 1000,
            'shipping_address' => ['address_line' => 'Bagerhat Sadar'],
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListPendingOrders::class)
            ->callTableAction('confirmAndShip', $order, data: [
                'shipping_partner_id' => $partner->getKey(),
            ]);

        $order->refresh();

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertNull($order->confirmed_by);
        $this->assertNull($order->tracking_number);
    }

    public function test_shipping_status_command_refreshes_the_tracking_status(): void
    {
        Http::fake([
            'https://courier.example/status_by_trackingcode/SF-TISILO-2002' => Http::response([
                'status' => 200,
                'delivery_status' => 'delivered',
            ]),
        ]);

        SiteSetting::put('courier', [
            'steadfast_enabled' => true,
            'steadfast_endpoint' => 'https://courier.example',
        ], [
            'steadfast_api_key' => 'test-api-key',
            'steadfast_secret_key' => 'test-secret-key',
        ]);

        $partner = ShippingPartner::query()->create([
            'name' => 'Steadfast',
            'code' => 'steadfast-sync-'.Str::lower(Str::random(6)),
            'api_provider' => 'steadfast',
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'customer_name' => 'Status Customer',
            'customer_phone' => '01700000000',
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'subtotal_amount' => 1000,
            'total_amount' => 1000,
            'shipping_partner_id' => $partner->getKey(),
            'tracking_number' => 'SF-TISILO-2002',
            'shipping_status' => 'in_review',
        ]);

        $this->artisan('shipping:sync-statuses')->assertSuccessful();

        $order->refresh();
        $this->assertSame('delivered', $order->shipping_status);
        $this->assertNotNull($order->shipping_status_synced_at);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);
    }

    private function order(OrderStatus $status, string $customer): Order
    {
        return Order::query()->create([
            'customer_name' => $customer,
            'status' => $status,
            'payment_status' => PaymentStatus::Unpaid,
            'subtotal_amount' => 1000,
            'total_amount' => 1000,
        ]);
    }
}
