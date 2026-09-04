<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
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
            ->assertSee('Tisilo Shop');

        $this->get('/shop')
            ->assertOk()
            ->assertSee('Tisilo Shop');
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

    public function test_category_uses_the_canonical_product_category_permalink(): void
    {
        $category = Category::query()->updateOrCreate(
            ['slug' => 'electronics-electrical'],
            ['name' => 'Electronics & Electrical', 'status' => true],
        );
        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Category Permalink Product',
            'slug' => 'category-permalink-product',
            'product_type' => 'simple',
            'regular_price' => 500,
            'stock_quantity' => 1,
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);

        $canonicalUrl = rtrim(url('/product-category/electronics-electrical'), '/').'/';

        $this->assertSame($canonicalUrl, $category->permalink);
        $kernel = $this->app->make(Kernel::class);
        $request = Request::create($canonicalUrl, 'GET');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Electronics &amp; Electrical', (string) $response->getContent());
        $this->assertStringContainsString('Category Permalink Product', (string) $response->getContent());
        $this->assertStringContainsString('<link rel="canonical" href="'.$canonicalUrl.'">', (string) $response->getContent());

        $this->get('/product-category/electronics-electrical')
            ->assertRedirect($canonicalUrl)
            ->assertStatus(301);

        $this->get('/shop?category=electronics-electrical&sort=name')
            ->assertRedirect($canonicalUrl.'?sort=name')
            ->assertStatus(301);
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
            ->assertSee('data-product-gallery-variation="'.$ownImage->id.'"', false)
            ->assertSee('data-product-quantity-step="-1"', false)
            ->assertSee('data-product-variation-option="'.$masterOnly->id.'"', false)
            ->assertSee('data-product-variation-option="'.$ownImage->id.'"', false)
            ->assertSee('name="redirect_to" value="checkout"', false)
            ->assertSee(asset('storage/products/master.jpg'), false)
            ->assertSee(asset('storage/products/variations/own.jpg'), false)
            ->assertSee('<p><strong>Rich product details</strong></p>', false)
            ->assertDontSee('&lt;p&gt;&lt;strong&gt;Rich product details', false);

        $this->post(route('store.cart.store'), [
            'product_id' => $product->id,
            'product_variation_id' => $ownImage->id,
            'quantity' => 1,
            'redirect_to' => 'checkout',
        ])->assertRedirect(route('store.checkout.index'))
            ->assertSessionHas('store_cart.catalog-'.$product->id.'-'.$ownImage->id.'.product_variation_id', $ownImage->id);
    }
}
