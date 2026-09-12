<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use DatabaseTransactions;

    public function test_homepage_and_catalog_are_available(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('TISILO')
            ->assertSee('আপনার প্রয়োজনের সবকিছু')
            ->assertSee('data-product-grid', false);

        $this->get('/products')
            ->assertOk()
            ->assertSee('Tisilo Shop')
            ->assertSee('data-product-grid', false);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('Tisilo Shop');
    }

    public function test_product_grids_have_five_pixel_gap_and_no_padding(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('.storefront-product-grid', $css);
        $this->assertStringContainsString('gap: 5px;', $css);
        $this->assertStringContainsString('padding: 0;', $css);
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
            ['parent_id' => null, 'slug' => 'electronics-electrical'],
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

    public function test_subcategory_permalink_and_slug_are_scoped_to_the_parent_category(): void
    {
        $token = Str::lower(Str::random(8));
        $sharedSlug = 'inner-wear-'.$token;
        $mens = Category::query()->create([
            'name' => 'Men’s Fashion '.$token,
            'slug' => 'mens-fashion-'.$token,
            'status' => true,
        ]);
        $womens = Category::query()->create([
            'name' => 'Women’s Fashion '.$token,
            'slug' => 'womens-fashion-'.$token,
            'status' => true,
        ]);
        $mensInnerWear = $mens->children()->create([
            'name' => 'Men’s Inner Wear',
            'slug' => $sharedSlug,
            'status' => true,
        ]);
        $womensInnerWear = $womens->children()->create([
            'name' => 'Women’s Inner Wear',
            'slug' => $sharedSlug,
            'status' => true,
        ]);
        $uniqueChild = $mens->children()->create([
            'name' => 'Full Sleeve Shirt',
            'slug' => 'full-sleeve-shirt-'.$token,
            'status' => true,
        ]);
        Product::query()->create([
            'category_id' => $mensInnerWear->id,
            'name' => 'Men Inner Wear Product '.$token,
            'slug' => 'men-inner-wear-product-'.$token,
            'product_type' => 'simple',
            'regular_price' => 500,
            'stock_quantity' => 1,
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);
        Product::query()->create([
            'category_id' => $womensInnerWear->id,
            'name' => 'Women Inner Wear Product '.$token,
            'slug' => 'women-inner-wear-product-'.$token,
            'product_type' => 'simple',
            'regular_price' => 600,
            'stock_quantity' => 1,
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);

        $mensUrl = url('/product-category/'.$mens->slug.'/'.$sharedSlug).'/';
        $womensUrl = url('/product-category/'.$womens->slug.'/'.$sharedSlug).'/';

        $this->assertSame($mensUrl, $mensInnerWear->permalink);
        $this->assertSame($womensUrl, $womensInnerWear->permalink);
        $kernel = $this->app->make(Kernel::class);
        $mensRequest = Request::create($mensUrl, 'GET');
        $mensResponse = $kernel->handle($mensRequest);
        $kernel->terminate($mensRequest, $mensResponse);
        $this->assertSame(200, $mensResponse->getStatusCode());
        $this->assertStringContainsString('Men Inner Wear Product '.$token, (string) $mensResponse->getContent());
        $this->assertStringNotContainsString('Women Inner Wear Product '.$token, (string) $mensResponse->getContent());

        $womensRequest = Request::create($womensUrl, 'GET');
        $womensResponse = $kernel->handle($womensRequest);
        $kernel->terminate($womensRequest, $womensResponse);
        $this->assertSame(200, $womensResponse->getStatusCode());
        $this->assertStringContainsString('Women Inner Wear Product '.$token, (string) $womensResponse->getContent());
        $this->assertStringNotContainsString('Men Inner Wear Product '.$token, (string) $womensResponse->getContent());

        $this->get('/product-category/'.$sharedSlug.'/')->assertNotFound();
        $this->get('/product-category/'.$uniqueChild->slug.'/')
            ->assertRedirect($uniqueChild->permalink)
            ->assertStatus(301);
        $this->get('/product-category/'.$mens->slug.'/'.$sharedSlug)
            ->assertRedirect($mensUrl)
            ->assertStatus(301);
        $this->get('/shop?category='.$mens->slug.'/'.$sharedSlug)
            ->assertRedirect($mensUrl)
            ->assertStatus(301);
    }

    public function test_apache_preserves_trailing_slashes_for_nested_category_permalinks(): void
    {
        $htaccess = file_get_contents(public_path('.htaccess'));

        $this->assertIsString($htaccess);
        $this->assertStringContainsString(
            'RewriteCond %{REQUEST_URI} !^/product-category(?:/.+)?/$ [NC]',
            $htaccess,
        );
        $this->assertStringNotContainsString(
            'RewriteCond %{REQUEST_URI} !^/product-category/[^/]+/$ [NC]',
            $htaccess,
        );
    }

    public function test_variable_product_keeps_main_image_primary_and_lists_main_and_variation_thumbnails(): void
    {
        $product = Product::query()->create([
            'name' => 'Visual Variation Product', 'slug' => 'visual-variation-product',
            'product_type' => 'variable', 'regular_price' => 1500, 'sale_price' => 1200,
            'featured_image' => 'products/master.jpg', 'stock_quantity' => 10,
            'stock_status' => 'in_stock', 'status' => 'published',
            'description' => '<p><strong>Rich product details</strong></p>',
            'seo_title' => 'Legacy Product SEO Title',
            'meta_description' => 'Legacy product meta description.',
        ]);
        $masterOnly = ProductVariation::query()->create([
            'product_id' => $product->id, 'sku' => 'MASTER-ONLY', 'regular_price' => 1500,
            'sale_price' => 1200, 'stock_quantity' => 5, 'stock_status' => 'in_stock',
            'status' => true,
        ]);
        $ownImage = ProductVariation::query()->create([
            'product_id' => $product->id, 'sku' => 'OWN-IMAGE', 'regular_price' => 1700,
            'sale_price' => 1400, 'stock_quantity' => 3, 'stock_status' => 'in_stock',
            'image' => 'products/variations/own.jpg', 'status' => true, 'is_default' => true,
        ]);

        $response = $this->get(route('store.products.show', $product));

        $response->assertOk()
            ->assertSee('data-product-variation-options', false)
            ->assertSee('data-product-gallery-master', false)
            ->assertSee('data-product-gallery-variation="'.$ownImage->id.'"', false)
            ->assertSee('data-product-main-image src="'.asset('storage/products/master.jpg').'"', false)
            ->assertSee('data-product-quantity-step="-1"', false)
            ->assertSee('data-product-variation-option="'.$masterOnly->id.'"', false)
            ->assertSee('data-product-variation-option="'.$ownImage->id.'"', false)
            ->assertSee('name="redirect_to" value="checkout"', false)
            ->assertSee(asset('storage/products/master.jpg'), false)
            ->assertSee(asset('storage/products/variations/own.jpg'), false)
            ->assertSee('<p><strong>Rich product details</strong></p>', false)
            ->assertSee('<title>Visual Variation Product — ', false)
            ->assertSee('<meta name="description" content="Rich product details">', false)
            ->assertDontSee('Legacy Product SEO Title')
            ->assertDontSee('Legacy product meta description.')
            ->assertDontSee('&lt;p&gt;&lt;strong&gt;Rich product details', false);

        $content = (string) $response->getContent();
        $masterThumbnailPosition = strpos($content, 'data-product-gallery-master');
        $variationThumbnailPosition = strpos($content, 'data-product-gallery-variation="'.$ownImage->id.'"');

        $this->assertNotFalse($masterThumbnailPosition);
        $this->assertNotFalse($variationThumbnailPosition);
        $this->assertLessThan($variationThumbnailPosition, $masterThumbnailPosition);
        $this->assertGreaterThanOrEqual(2, substr_count($content, asset('storage/products/master.jpg')));
        $this->assertMatchesRegularExpression('/data-product-gallery-master[^>]+aria-pressed="true"/', $content);
        $this->assertMatchesRegularExpression('/data-product-gallery-variation="'.$ownImage->id.'"[^>]+aria-pressed="false"/', $content);

        $variationScript = file_get_contents(resource_path('js/product-variations.js'));
        $this->assertIsString($variationScript);
        $this->assertStringContainsString('[data-product-gallery-master]', $variationScript);
        $this->assertStringContainsString("setGallerySelection('master')", $variationScript);

        $this->post(route('store.cart.store'), [
            'product_id' => $product->id,
            'product_variation_id' => $ownImage->id,
            'quantity' => 1,
            'redirect_to' => 'checkout',
        ])->assertRedirect(route('store.checkout.index'))
            ->assertSessionHas('store_cart.catalog-'.$product->id.'-'.$ownImage->id.'.product_variation_id', $ownImage->id);
    }
}
