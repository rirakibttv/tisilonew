<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
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

        $response = $this->withSession(['store_wishlist' => [$product->id, 0]])
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

        $this->get(route('store.home'))
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
}
