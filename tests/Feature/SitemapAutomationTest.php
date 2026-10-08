<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\GoogleSearchConsoleService;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class SitemapAutomationTest extends TestCase
{
    use DatabaseTransactions;

    private bool $sitemapExisted;

    private string $originalSitemap = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->sitemapExisted = File::exists(public_path('sitemap.xml'));
        if ($this->sitemapExisted) {
            $this->originalSitemap = (string) File::get(public_path('sitemap.xml'));
        }
    }

    protected function tearDown(): void
    {
        if ($this->sitemapExisted) {
            File::put(public_path('sitemap.xml'), $this->originalSitemap, true);
        } else {
            File::delete(public_path('sitemap.xml'));
        }

        parent::tearDown();
    }

    public function test_sitemap_contains_published_products_active_category_hierarchy_and_clean_page_urls(): void
    {
        SiteSetting::put('seo', [
            'search_console_enabled' => false,
            'search_console_sitemap_url' => url('/sitemap.xml'),
            'sitemap_include_products' => true,
            'sitemap_include_categories' => true,
            'sitemap_include_pages' => true,
            'sitemap_change_frequency' => 'hourly',
        ]);
        SiteSetting::put('pages', ['pages' => [
            ['slug' => 'contact-us', 'status' => true],
            ['slug' => 'buying-guide', 'status' => true],
            ['slug' => 'draft-page', 'status' => false],
        ]]);

        $parent = Category::query()->create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'status' => true,
        ]);
        Category::query()->create([
            'parent_id' => $parent->getKey(),
            'name' => 'Mobile Phones',
            'slug' => 'mobile-phones',
            'status' => true,
        ]);
        Category::query()->create([
            'name' => 'Hidden Category',
            'slug' => 'hidden-category',
            'status' => false,
        ]);
        Product::query()->create([
            'name' => 'Published Phone',
            'slug' => 'published-phone',
            'status' => 'published',
        ]);
        Product::query()->create([
            'name' => 'Draft Phone',
            'slug' => 'draft-phone',
            'status' => 'draft',
        ]);

        $result = app(SitemapService::class)->generate();
        $xml = (string) File::get(public_path('sitemap.xml'));

        $this->assertTrue($result['changed']);
        $this->assertStringContainsString(route('store.products.show', ['product' => 'published-phone']), $xml);
        $this->assertStringNotContainsString('draft-phone', $xml);
        $this->assertStringContainsString('/product-category/electronics/mobile-phones', $xml);
        $this->assertStringNotContainsString('hidden-category', $xml);
        $this->assertStringContainsString(route('store.contact'), $xml);
        $this->assertStringNotContainsString('/page/contact-us', $xml);
        $this->assertStringContainsString('/page/buying-guide', $xml);
        $this->assertStringContainsString('<changefreq>hourly</changefreq>', $xml);
        $this->assertSame($result['url_count'], SiteSetting::valuesFor('seo')['sitemap_url_count']);
        $this->assertSame('Generated successfully', SiteSetting::valuesFor('seo')['sitemap_last_generated_status']);
    }

    public function test_changed_sitemap_is_submitted_automatically_and_unchanged_sitemap_is_not_resubmitted(): void
    {
        SiteSetting::put('seo', [
            'search_console_enabled' => true,
            'search_console_property_url' => 'sc-domain:tisilo.com',
            'search_console_sitemap_url' => 'https://www.tisilo.com/sitemap.xml',
            'sitemap_include_products' => false,
            'sitemap_include_categories' => false,
            'sitemap_include_pages' => false,
            'sitemap_change_frequency' => 'hourly',
        ], [
            'search_console_service_account_json' => '{"client_email":"search@example.test"}',
        ]);
        File::put(public_path('sitemap.xml'), 'stale sitemap', true);

        $searchConsole = Mockery::mock(GoogleSearchConsoleService::class);
        $searchConsole->shouldReceive('submitSitemap')
            ->once()
            ->with(
                '{"client_email":"search@example.test"}',
                'sc-domain:tisilo.com',
                'https://www.tisilo.com/sitemap.xml',
            )
            ->andReturn(['submitted' => true]);
        $service = new SitemapService($searchConsole);

        $first = $service->sync();
        $second = $service->sync();

        $this->assertTrue($first['submitted']);
        $this->assertFalse($second['changed']);
        $this->assertFalse($second['submitted']);
        $this->assertNotEmpty(SiteSetting::valuesFor('seo')['search_console_last_sitemap_submitted_at']);
    }

    public function test_hourly_sitemap_command_is_registered_with_the_scheduler(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString('sitemap:sync', $output);
        $this->assertMatchesRegularExpression('/0\s+\*\s+\*\s+\*\s+\*/', $output);
    }
}
