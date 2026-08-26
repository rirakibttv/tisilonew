<?php

namespace Tests\Feature;

use App\Enums\IncompleteOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VendorStatus;
use App\Models\IncompleteOrder;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderPanelTest extends TestCase
{
    use DatabaseTransactions;

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
