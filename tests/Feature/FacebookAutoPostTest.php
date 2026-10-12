<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\PublishFacebookProductPost;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\FacebookAutoPostService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class FacebookAutoPostTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        SiteSetting::forget('facebook_capi');
        SiteSetting::forget('facebook_auto_post');

        parent::tearDown();
    }

    public function test_admin_can_load_page_id_and_encrypted_page_token_from_capi(): void
    {
        SiteSetting::put('facebook_capi', [
            'enabled' => true,
            'api_version' => 'v23.0',
        ], ['access_token' => 'capi-user-access-token']);
        SiteSetting::put('facebook_auto_post', [
            'enabled' => false,
            'api_version' => 'v23.0',
        ]);
        Http::fake([
            'https://graph.facebook.com/v23.0/me/accounts*' => Http::response([
                'data' => [[
                    'id' => '123456789012345',
                    'name' => 'Tisilo Supermarket',
                    'access_token' => 'encrypted-page-token-source',
                    'tasks' => ['CREATE_CONTENT', 'ANALYZE'],
                ]],
            ]),
        ]);

        $result = app(FacebookAutoPostService::class)->discoverPagesFromCapi();

        $this->assertSame(1, $result['count']);
        $this->assertSame('123456789012345', $result['selected_page_id']);
        $this->assertSame('Tisilo Supermarket', SiteSetting::valuesFor('facebook_auto_post')['available_pages']['123456789012345']);
        $this->assertSame('encrypted-page-token-source', SiteSetting::secretsFor('facebook_auto_post')['page_access_token']);
        $this->assertSame('encrypted-page-token-source', SiteSetting::secretsFor('facebook_auto_post')['page_tokens']['123456789012345']);

        $setting = SiteSetting::query()->where('key', 'facebook_auto_post')->firstOrFail();
        $this->assertStringNotContainsString('encrypted-page-token-source', (string) $setting->getRawOriginal('secret_values'));
        $this->assertArrayNotHasKey('page_access_token', SiteSetting::valuesFor('facebook_auto_post'));

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://graph.facebook.com/v23.0/me/accounts?')
            && $request->hasHeader('Authorization', 'Bearer capi-user-access-token')
            && $request['fields'] === 'id,name,access_token,tasks'
            && (int) $request['limit'] === 100);
    }

    public function test_publishing_a_product_queues_an_automatic_facebook_post(): void
    {
        Queue::fake();
        $this->configureAutoPost();
        $product = $this->product('draft');

        Queue::assertNotPushed(PublishFacebookProductPost::class);

        $product->update(['status' => 'published']);

        Queue::assertPushed(PublishFacebookProductPost::class, 1);
        Queue::assertPushed(PublishFacebookProductPost::class, fn (PublishFacebookProductPost $job): bool => $job->productId === $product->id);
    }

    public function test_auto_post_publishes_the_product_link_and_tracks_the_meta_post_id(): void
    {
        Queue::fake();
        $this->configureAutoPost();
        Http::fake([
            'https://graph.facebook.com/v23.0/123456789012345/feed' => Http::response([
                'id' => '123456789012345_9988776655',
            ]),
        ]);
        $product = $this->product('published');

        $result = app(FacebookAutoPostService::class)->publishProduct($product);

        $this->assertSame('123456789012345_9988776655', $result['id']);
        $this->assertSame('Published', SiteSetting::valuesFor('facebook_auto_post')['last_post_status']);
        $this->assertSame($product->id, SiteSetting::valuesFor('facebook_auto_post')['last_post_product_id']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://graph.facebook.com/v23.0/123456789012345/feed'
            && $request->hasHeader('Authorization', 'Bearer selected-page-token')
            && str_contains((string) $request['message'], $product->name)
            && str_contains((string) $request['message'], '৳1,050.00')
            && $request['link'] === route('store.products.show', $product)
            && $request['published'] === 'true');
    }

    public function test_auto_post_page_exposes_capi_discovery_and_verification_actions(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get('/admin/api-integrations?section=facebook_auto_post')
            ->assertOk()
            ->assertSee('Get Pages from CAPI')
            ->assertSee('Verify Selected Page')
            ->assertSee('Facebook Page')
            ->assertSee('Post when a product is first published');
    }

    private function configureAutoPost(): void
    {
        SiteSetting::put('facebook_auto_post', [
            'enabled' => true,
            'post_on_product_publish' => true,
            'api_version' => 'v23.0',
            'page_id' => '123456789012345',
            'page_name' => 'Tisilo Supermarket',
            'default_message_template' => "New: {product_name}\nPrice: ৳{price}\n{url}",
        ], ['page_access_token' => 'selected-page-token']);
    }

    private function product(string $status): Product
    {
        return Product::query()->create([
            'name' => 'Facebook Auto Post Product',
            'slug' => 'facebook-auto-post-'.Str::lower(Str::random(10)),
            'product_type' => 'simple',
            'sku' => 'FB-'.Str::upper(Str::random(8)),
            'regular_price' => 1400,
            'sale_price' => 1050,
            'manage_stock' => true,
            'stock_quantity' => 10,
            'stock_status' => 'in_stock',
            'status' => $status,
        ]);
    }
}
