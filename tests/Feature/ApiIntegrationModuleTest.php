<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Pages\ApiIntegrationSettings;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\CloudflareApiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiIntegrationModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_active_admin_can_open_every_api_integration_module_in_the_requested_order(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(array_values(ApiIntegrationSettings::SECTIONS));

        foreach (ApiIntegrationSettings::SECTIONS as $section => $label) {
            $this->actingAs($admin)
                ->get('/admin/api-integrations?section='.$section)
                ->assertOk()
                ->assertSee($label);
        }
    }

    public function test_api_credentials_are_encrypted_and_not_returned_as_public_values(): void
    {
        SiteSetting::put('facebook_capi', [
            'enabled' => false,
            'pixel_id' => '123456789',
        ], [
            'access_token' => 'private-meta-token',
        ]);

        $setting = SiteSetting::query()->where('key', 'facebook_capi')->firstOrFail();

        $this->assertSame('123456789', SiteSetting::valuesFor('facebook_capi')['pixel_id']);
        $this->assertSame('private-meta-token', SiteSetting::secretsFor('facebook_capi')['access_token']);
        $this->assertStringNotContainsString('private-meta-token', (string) $setting->getRawOriginal('secret_values'));
        $this->assertArrayNotHasKey('access_token', SiteSetting::valuesFor('facebook_capi'));
    }

    public function test_cloudflare_is_the_last_api_integration_module_and_token_is_encrypted(): void
    {
        $this->assertSame('Cloudflare API', ApiIntegrationSettings::SECTIONS['cloudflare']);
        $this->assertSame('cloudflare', array_key_last(ApiIntegrationSettings::SECTIONS));

        SiteSetting::put('cloudflare', [
            'enabled' => true,
            'zone_id' => str_repeat('a', 32),
            'hostname' => 'www.tisilo.com',
        ], [
            'api_token' => 'private-cloudflare-token',
        ]);

        $setting = SiteSetting::query()->where('key', 'cloudflare')->firstOrFail();

        $this->assertSame('private-cloudflare-token', SiteSetting::secretsFor('cloudflare')['api_token']);
        $this->assertStringNotContainsString('private-cloudflare-token', (string) $setting->getRawOriginal('secret_values'));
        $this->assertArrayNotHasKey('api_token', SiteSetting::valuesFor('cloudflare'));
    }

    public function test_cloudflare_service_creates_a_non_html_static_asset_cache_rule_without_replacing_rules(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/*/rulesets/phases/http_request_cache_settings/entrypoint' => Http::response([
                'success' => false,
                'errors' => [['message' => 'entry point not found']],
            ], 404),
            'https://api.cloudflare.com/client/v4/zones/*/rulesets' => Http::response([
                'success' => true,
                'errors' => [],
                'result' => [
                    'id' => 'ruleset-123',
                    'rules' => [[
                        'id' => 'rule-123',
                        'description' => 'Tisilo: cache static storefront assets',
                    ]],
                ],
            ]),
        ]);

        $result = app(CloudflareApiService::class)->applyStaticAssetCacheRule(
            'cloudflare-token',
            str_repeat('a', 32),
            'www.tisilo.com',
            86400,
            14400,
        );

        $this->assertSame('ruleset-123', $result['ruleset_id']);
        $this->assertSame('rule-123', $result['rule_id']);

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/rulesets')) {
                return false;
            }

            $rule = $request->data()['rules'][0] ?? [];

            return $request->hasHeader('Authorization', 'Bearer cloudflare-token')
                && ($request->data()['phase'] ?? null) === 'http_request_cache_settings'
                && ($rule['action'] ?? null) === 'set_cache_settings'
                && str_contains((string) ($rule['expression'] ?? ''), 'http.request.uri.path.extension')
                && ! str_contains((string) ($rule['expression'] ?? ''), '"json"')
                && ! str_contains((string) ($rule['expression'] ?? ''), '/cart')
                && ($rule['action_parameters']['edge_ttl']['default'] ?? null) === 86400;
        });
    }

    public function test_cloudflare_service_uses_the_official_purge_endpoint(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/*/purge_cache' => Http::response([
                'success' => true,
                'errors' => [],
                'result' => ['id' => 'purge-123'],
            ]),
        ]);

        app(CloudflareApiService::class)->purgeEverything('cloudflare-token', str_repeat('b', 32));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/purge_cache')
            && ($request->data()['purge_everything'] ?? false) === true);
    }

    public function test_cloudflare_service_normalizes_a_pasted_authorization_header(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/*' => Http::response([
                'success' => true,
                'errors' => [],
                'result' => ['name' => 'tisilo.com', 'status' => 'active'],
            ]),
        ]);

        app(CloudflareApiService::class)->zone(
            "  Authorization: Bearer cloudflare-token\r\n",
            str_repeat('c', 32),
        );

        Http::assertSent(fn (Request $request): bool => $request->hasHeader(
            'Authorization',
            'Bearer cloudflare-token',
        ));
    }

    public function test_origin_cache_headers_cache_static_assets_but_not_dynamic_html(): void
    {
        $htaccess = file_get_contents(public_path('.htaccess'));

        $this->assertStringContainsString('Cloudflare-CDN-Cache-Control "public, max-age=2592000', $htaccess);
        $this->assertStringContainsString('Cloudflare-CDN-Cache-Control "no-store"', $htaccess);
        $this->assertStringNotContainsString('|json|', $htaccess);
    }
}
