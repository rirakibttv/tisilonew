<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use DatabaseTransactions;

    public function test_homepage_and_catalog_are_available(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('TISILO')
            ->assertSee('আপনার প্রয়োজনের সবকিছু');

        $this->get('/products')
            ->assertOk()
            ->assertSee('সব পণ্য');
    }

    public function test_customer_can_view_a_product_and_add_it_to_cart(): void
    {
        $product = Product::query()->create([
            'name' => 'Storefront Test Product',
            'slug' => 'storefront-test-product',
            'product_type' => 'simple',
            'regular_price' => 1500,
            'sale_price' => 1250,
            'stock_quantity' => 10,
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);

        $this->get(route('store.products.show', $product->slug))
            ->assertOk()
            ->assertSee('Storefront Test Product')
            ->assertSee('কার্টে যোগ করুন');

        $this->post(route('store.cart.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertRedirect(route('store.cart.index'))
            ->assertSessionHas('store_cart.catalog-'.$product->id.'-base.quantity', 2);

        $this->get(route('store.cart.index'))
            ->assertOk()
            ->assertSee('Storefront Test Product')
            ->assertSee('2,500');
    }
}
