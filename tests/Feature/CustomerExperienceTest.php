<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerExperienceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_can_add_and_remove_a_wishlist_product(): void
    {
        $product = Product::query()->create([
            'name' => 'Wishlist Product',
            'slug' => 'wishlist-product',
            'product_type' => 'simple',
            'regular_price' => 1000,
            'stock_quantity' => 5,
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);

        $this->post(route('store.wishlist.store', $product))
            ->assertRedirect()
            ->assertSessionHas('store_wishlist', [$product->id]);

        $this->get(route('store.wishlist.index'))->assertOk()->assertSee('Wishlist Product');

        $this->delete(route('store.wishlist.destroy', $product))
            ->assertRedirect()
            ->assertSessionHas('store_wishlist', []);
    }

    public function test_order_tracking_requires_matching_order_number_and_phone(): void
    {
        $order = Order::query()->create([
            'order_number' => 'TIS-TRACK-01',
            'customer_name' => 'Tracking Customer',
            'customer_phone' => '01700000000',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'cod',
            'subtotal_amount' => 900,
            'total_amount' => 900,
            'currency' => 'BDT',
            'shipping_address' => ['address_line' => 'Dhaka'],
        ]);

        $this->get(route('store.account.index', ['order_number' => $order->order_number, 'phone' => '01700000000']))
            ->assertOk()
            ->assertSee('Tracking Customer')
            ->assertSee($order->order_number);

        $this->get(route('store.account.index', ['order_number' => $order->order_number, 'phone' => '01800000000']))
            ->assertOk()
            ->assertDontSee('Tracking Customer')
            ->assertSee('কোনো অর্ডার পাওয়া যায়নি');
    }
}
