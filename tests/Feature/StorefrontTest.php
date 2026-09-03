<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariation;
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

    public function test_variable_product_uses_visual_options_and_only_switches_to_a_variation_image_when_present(): void
    {
        $product = Product::query()->create([
            'name' => 'Visual Variation Product', 'slug' => 'visual-variation-product',
            'product_type' => 'variable', 'regular_price' => 1500, 'sale_price' => 1200,
            'featured_image' => 'products/master.jpg', 'stock_quantity' => 10,
            'stock_status' => 'in_stock', 'status' => 'published',
            'description' => '<p><strong>Rich product details</strong></p>',
        ]);
        $masterOnly = ProductVariation::query()->create([
            'product_id' => $product->id, 'sku' => 'MASTER-ONLY', 'regular_price' => 1500,
            'sale_price' => 1200, 'stock_quantity' => 5, 'stock_status' => 'in_stock',
            'status' => true, 'is_default' => true,
        ]);
        $ownImage = ProductVariation::query()->create([
            'product_id' => $product->id, 'sku' => 'OWN-IMAGE', 'regular_price' => 1700,
            'sale_price' => 1400, 'stock_quantity' => 3, 'stock_status' => 'in_stock',
            'image' => 'products/variations/own.jpg', 'status' => true,
        ]);

        $this->get(route('store.products.show', $product))->assertOk()
            ->assertSee('data-product-variation-options', false)
            ->assertSee('data-product-variation-option="'.$masterOnly->id.'"', false)
            ->assertSee('data-product-variation-option="'.$ownImage->id.'"', false)
            ->assertSee(asset('storage/products/master.jpg'), false)
            ->assertSee(asset('storage/products/variations/own.jpg'), false)
            ->assertSee('<p><strong>Rich product details</strong></p>', false)
            ->assertDontSee('&lt;p&gt;&lt;strong&gt;Rich product details', false);
    }
}
