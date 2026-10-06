<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\MetaCatalogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MetaCatalogTest extends TestCase
{
    use DatabaseTransactions;

    public function test_secure_feed_contains_published_product_and_full_category_hierarchy(): void
    {
        $token = Str::random(64);
        SiteSetting::put('facebook_catalog', [
            'enabled' => true,
            'catalog_id' => '123456789012345',
            'api_version' => 'v23.0',
            'default_currency' => 'BDT',
            'default_brand' => 'Tisilo',
        ], ['feed_token' => $token, 'access_token' => 'catalog-secret-token']);

        $parent = Category::query()->create([
            'name' => 'Home & Kitchen',
            'slug' => 'meta-home-'.Str::lower(Str::random(8)),
            'status' => true,
        ]);
        $child = Category::query()->create([
            'parent_id' => $parent->id,
            'name' => 'Bed Sheet',
            'slug' => 'meta-bed-sheet-'.Str::lower(Str::random(8)),
            'status' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Meta Catalog Bed Sheet',
            'slug' => 'meta-catalog-bed-sheet-'.Str::lower(Str::random(8)),
            'product_type' => 'simple',
            'category_id' => $child->id,
            'sku' => 'META-'.Str::upper(Str::random(8)),
            'regular_price' => 1400,
            'sale_price' => 1050,
            'manage_stock' => true,
            'stock_quantity' => 20,
            'stock_status' => 'in_stock',
            'featured_image' => 'products/meta-bed-sheet.jpg',
            'status' => 'published',
        ]);

        $response = $this->get(route('integrations.meta.catalog-feed', ['token' => $token]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/tab-separated-values; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString("id\ttitle\tdescription\tavailability", $content);
        $this->assertStringContainsString((string) $product->id, $content);
        $this->assertStringContainsString('Meta Catalog Bed Sheet', $content);
        $this->assertStringContainsString('Home & Kitchen > Bed Sheet', $content);
        $this->assertStringContainsString('1050.00 BDT', $content);
        $this->assertStringContainsString(route('store.products.show', $product), $content);
    }

    public function test_feed_rejects_an_invalid_token(): void
    {
        SiteSetting::put('facebook_catalog', ['enabled' => true], ['feed_token' => Str::random(64)]);

        $this->get('/integrations/meta/catalog-feed/'.Str::random(64).'.tsv')->assertNotFound();
    }

    public function test_sync_creates_meta_feed_and_queues_secure_url_upload(): void
    {
        $token = Str::random(64);
        SiteSetting::put('facebook_catalog', [
            'enabled' => true,
            'catalog_id' => '123456789012345',
            'api_version' => 'v23.0',
            'default_currency' => 'BDT',
            'default_brand' => 'Tisilo',
        ], ['feed_token' => $token, 'access_token' => 'catalog-secret-token']);

        Http::fake([
            'https://graph.facebook.com/v23.0/123456789012345*' => Http::sequence()
                ->push(['id' => '123456789012345', 'name' => 'Tisilo Catalog', 'product_count' => 12], 200)
                ->push(['id' => 'feed-987'], 200),
            'https://graph.facebook.com/v23.0/feed-987/uploads' => Http::response(['id' => 'upload-654'], 200),
        ]);

        $result = app(MetaCatalogService::class)->sync();

        $this->assertSame('feed-987', $result['feedId']);
        $this->assertSame('upload-654', $result['upload']['id']);
        $this->assertSame('feed-987', SiteSetting::valuesFor('facebook_catalog')['feed_id']);
        $this->assertSame('Queued by Meta', SiteSetting::valuesFor('facebook_catalog')['last_sync_status']);

        Http::assertSent(function (HttpRequest $request) use ($token): bool {
            return $request->url() === 'https://graph.facebook.com/v23.0/feed-987/uploads'
                && $request->hasHeader('Authorization', 'Bearer catalog-secret-token')
                && $request['url'] === route('integrations.meta.catalog-feed', ['token' => $token]);
        });
    }
}
