<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CustomerAddress;
use App\Models\CustomerOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontAccountTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_can_register_and_open_the_account_dashboard(): void
    {
        $token = Str::lower(Str::random(8));
        $product = Product::query()->create([
            'name' => 'Saved Before Registration '.$token,
            'slug' => 'saved-before-registration-'.$token,
            'product_type' => 'simple',
            'regular_price' => 500,
            'stock_quantity' => 2,
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);

        $response = $this->withSession([
            'store_wishlist' => [$product->id, 0],
            'storefront_locale' => 'bn',
        ])
            ->post(route('store.account.store'), [
                'name' => 'Store Customer',
                'email' => "customer-{$token}@example.test",
                'phone' => '01'.random_int(100000000, 999999999),
                'password' => 'StrongPassword123!',
                'password_confirmation' => 'StrongPassword123!',
                'role' => 'super_admin',
                'rbac_role_id' => 1,
            ]);

        $customer = User::query()->where('email', "customer-{$token}@example.test")->sole();

        $response->assertRedirect(route('store.account.dashboard'));
        $this->assertAuthenticatedAs($customer);
        $this->assertSame(UserRole::Customer, $customer->role);
        $this->assertSame(UserStatus::Active, $customer->status);
        $this->assertNull($customer->rbac_role_id);
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        $this->get(route('store.account.dashboard'))
            ->assertOk()
            ->assertSee('সাম্প্রতিক অর্ডার')
            ->assertSee('Store Customer');
    }

    public function test_account_and_wishlist_links_are_functional_from_the_homepage(): void
    {
        $product = Product::query()->create([
            'name' => 'Wishlist Product '.Str::random(6),
            'slug' => 'wishlist-product-'.Str::lower(Str::random(8)),
            'product_type' => 'simple',
            'regular_price' => 750,
            'stock_quantity' => 5,
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);

        $this->withSession(['storefront_locale' => 'bn'])
            ->get(route('store.home'))
            ->assertOk()
            ->assertSee(route('store.account.login'), false)
            ->assertSee(route('store.wishlist.index'), false)
            ->assertDontSee('href="#"', false);

        $this->post(route('store.wishlist.store', $product))
            ->assertRedirect()
            ->assertSessionHas('store_wishlist', [$product->id]);

        $this->get(route('store.wishlist.index'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('উইশলিস্ট থেকে সরান');

        $this->delete(route('store.wishlist.destroy', $product))
            ->assertRedirect()
            ->assertSessionHas('store_wishlist', []);
    }

    public function test_guest_is_sent_to_customer_login_for_protected_account_pages(): void
    {
        $this->get(route('store.account.dashboard'))
            ->assertRedirect(route('store.account.login'));
    }

    public function test_customer_can_login_and_logout_and_deleted_guest_items_are_ignored(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => UserStatus::Active]);
        $missingId = (int) Product::query()->max('id') + 100;

        $this->withSession(['store_wishlist' => [$missingId]])
            ->post(route('store.account.authenticate'), ['email' => $customer->email, 'password' => 'password'])
            ->assertRedirect(route('store.account.dashboard'));
        $this->assertAuthenticatedAs($customer);
        $this->post(route('store.account.logout'))->assertRedirect(route('store.home'));
        $this->assertGuest();
    }

    public function test_inactive_customer_and_incorrect_password_cannot_login(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => UserStatus::Suspended]);

        foreach (['incorrect', 'password'] as $password) {
            $this->post(route('store.account.authenticate'), ['email' => $customer->email, 'password' => $password])
                ->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    public function test_authenticated_wishlist_is_persistent_and_scoped_to_its_owner(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => UserStatus::Active]);
        $other = User::factory()->create(['role' => UserRole::Customer, 'status' => UserStatus::Active]);
        $product = Product::query()->create([
            'name' => 'Persistent Wishlist '.Str::random(8),
            'slug' => 'persistent-wishlist-'.Str::lower(Str::random(8)),
            'product_type' => 'simple',
            'regular_price' => 500,
            'stock_quantity' => 2,
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);

        $this->actingAs($customer)->post(route('store.wishlist.store', $product))->assertRedirect();
        $this->post(route('store.wishlist.store', $product))->assertRedirect();
        $this->assertSame(1, $customer->wishlistProducts()->count());
        $this->withSession(['store_wishlist' => []])->get(route('store.wishlist.index'))
            ->assertOk()->assertSee($product->name);

        $this->actingAs($other)->get(route('store.wishlist.index'))->assertOk()->assertDontSee($product->name);
        $this->delete(route('store.wishlist.destroy', $product))->assertRedirect();
        $this->assertSame(1, $customer->wishlistProducts()->count());
        $this->actingAs($customer)->delete(route('store.wishlist.destroy', $product))->assertRedirect();
        $this->assertSame(0, $customer->wishlistProducts()->count());
    }

    public function test_customer_dashboard_contains_the_complete_account_navigation(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => UserStatus::Active]);

        $this->actingAs($customer)->get(route('store.account.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'My Wishlist',
                'Order Info',
                'To Pay',
                'To Ship',
                'To Receive',
                'All Order',
                'My Review',
                'To Review',
                'All Review',
                'Return &amp; Cancellation',
                'All Return',
                'All Cancellation',
                'Manage My Account',
                'My Profile',
                'Address Book',
                'Payment Option',
            ], false);
    }

    public function test_every_customer_dashboard_section_renders_for_an_authenticated_customer(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => UserStatus::Active]);

        $urls = [
            route('store.account.orders', 'to-pay'),
            route('store.account.orders', 'to-ship'),
            route('store.account.orders', 'to-receive'),
            route('store.account.orders', 'all'),
            route('store.account.reviews', 'to-review'),
            route('store.account.reviews', 'all'),
            route('store.account.requests', ['return', 'new']),
            route('store.account.requests', ['return', 'all']),
            route('store.account.requests', ['cancellation', 'new']),
            route('store.account.requests', ['cancellation', 'all']),
            route('store.account.profile'),
            route('store.account.addresses'),
            route('store.account.payment-options'),
        ];

        $this->actingAs($customer);
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_order_tabs_are_status_filtered_and_scoped_to_the_customer(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => UserStatus::Active]);
        $other = User::factory()->create(['role' => UserRole::Customer, 'status' => UserStatus::Active]);
        $toPay = $this->createOrder($customer, OrderStatus::Pending, PaymentStatus::Unpaid, 'TIS-ACCOUNT-PAY');
        $toReceive = $this->createOrder($customer, OrderStatus::Shipped, PaymentStatus::Paid, 'TIS-ACCOUNT-SHIP');
        $otherOrder = $this->createOrder($other, OrderStatus::Pending, PaymentStatus::Unpaid, 'TIS-OTHER-PAY');

        $this->actingAs($customer)->get(route('store.account.orders', 'to-pay'))
            ->assertOk()
            ->assertSee($toPay->order_number)
            ->assertDontSee($toReceive->order_number)
            ->assertDontSee($otherOrder->order_number);

        $this->get(route('store.account.orders', 'to-receive'))
            ->assertOk()
            ->assertSee($toReceive->order_number)
            ->assertDontSee($toPay->order_number);
    }

    public function test_customer_can_manage_addresses_without_touching_another_customers_address(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => UserStatus::Active]);
        $other = User::factory()->create(['role' => UserRole::Customer, 'status' => UserStatus::Active]);
        $otherAddress = CustomerAddress::query()->create([
            'user_id' => $other->id,
            'label' => 'Other',
            'recipient_name' => $other->name,
            'phone' => '01700000000',
            'address_line' => 'Other address',
            'district' => 'Dhaka',
            'is_default' => true,
        ]);

        $this->actingAs($customer)->post(route('store.account.addresses.store'), [
            'label' => 'Home',
            'recipient_name' => 'Customer Name',
            'phone' => '01800000000',
            'address_line' => 'House 10, Road 2',
            'district' => 'Bagerhat',
            'thana' => 'Bagerhat Sadar',
            'is_default' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('customer_addresses', [
            'user_id' => $customer->id,
            'district' => 'Bagerhat',
            'is_default' => true,
        ]);
        $this->delete(route('store.account.addresses.destroy', $otherAddress))->assertNotFound();
        $this->assertDatabaseHas('customer_addresses', ['id' => $otherAddress->id]);
    }

    public function test_customer_can_request_a_return_only_for_their_delivered_order(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => UserStatus::Active]);
        $other = User::factory()->create(['role' => UserRole::Customer, 'status' => UserStatus::Active]);
        $delivered = $this->createOrder($customer, OrderStatus::Delivered, PaymentStatus::Paid, 'TIS-RETURN-OWN');
        $otherDelivered = $this->createOrder($other, OrderStatus::Delivered, PaymentStatus::Paid, 'TIS-RETURN-OTHER');

        $this->actingAs($customer)->post(route('store.account.requests.store', ['return', $delivered]), [
            'reason' => 'পণ্য ক্ষতিগ্রস্ত',
            'details' => 'প্যাকেট খোলার পর ক্ষতি দেখা গেছে।',
        ])->assertRedirect();

        $this->assertDatabaseHas('customer_order_requests', [
            'user_id' => $customer->id,
            'order_id' => $delivered->id,
            'type' => CustomerOrderRequest::TYPE_RETURN,
            'status' => 'pending',
        ]);

        $this->post(route('store.account.requests.store', ['return', $otherDelivered]), [
            'reason' => 'অন্য কারণ',
        ])->assertNotFound();
    }

    private function createOrder(User $customer, OrderStatus $status, PaymentStatus $paymentStatus, string $number): Order
    {
        return Order::query()->create([
            'order_number' => $number.'-'.Str::upper(Str::random(5)),
            'user_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'subtotal_amount' => 1000,
            'total_amount' => 1080,
            'currency' => 'BDT',
        ]);
    }
}
