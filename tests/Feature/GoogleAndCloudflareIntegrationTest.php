<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendGoogleAnalyticsEvent;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Services\CloudflareApiService;
use App\Services\GoogleAnalyticsService;
use App\Services\GoogleSearchConsoleService;
use App\Services\GoogleServiceAccountTokenService;
use App\Services\VisitorAnalyticsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class GoogleAndCloudflareIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_storefront_and_order_lifecycle_events_are_queued_for_ga4(): void
    {
        $this->enableGoogleAnalytics();
        Queue::fake();
        $request = Request::create('https://www.tisilo.com/products/test-product', 'POST');

        app(VisitorAnalyticsService::class)->record($request, [
            'visitor_id' => (string) Str::uuid(),
            'event_type' => 'add_to_cart',
            'value' => 1250,
            'metadata' => ['currency' => 'BDT', 'quantity' => 2],
        ]);

        Queue::assertPushed(SendGoogleAnalyticsEvent::class, fn (SendGoogleAnalyticsEvent $job): bool => $job->payload['events'][0]['name'] === 'add_to_cart'
            && $job->payload['events'][0]['params']['value'] === 1250.0
        );

        $order = $this->order();
        $order->update(['status' => OrderStatus::Cancelled]);
        Queue::assertPushed(SendGoogleAnalyticsEvent::class, fn (SendGoogleAnalyticsEvent $job): bool => $job->payload['events'][0]['name'] === 'order_cancelled'
            && $job->payload['events'][0]['params']['transaction_id'] === $order->order_number
        );
    }

    public function test_ga4_measurement_protocol_and_data_api_are_both_connected(): void
    {
        $this->enableGoogleAnalytics();
        SiteSetting::put('google_analytics', [
            ...SiteSetting::valuesFor('google_analytics'),
            'property_id' => '123456789',
        ], ['service_account_json' => '{"client_email":"analytics@example.test"}']);
        $tokens = Mockery::mock(GoogleServiceAccountTokenService::class);
        $tokens->shouldReceive('accessToken')->once()->andReturn('google-oauth-token');
        $service = new GoogleAnalyticsService($tokens);

        Http::fake([
            'https://www.google-analytics.com/mp/collect*' => Http::response('', 204),
            'https://analyticsdata.googleapis.com/v1beta/properties/123456789:runReport' => Http::response([
                'rows' => [[
                    'dimensionValues' => [['value' => '20260907']],
                    'metricValues' => [
                        ['value' => '12'], ['value' => '15'], ['value' => '40'], ['value' => '2500.50'],
                    ],
                ]],
            ]),
        ]);

        $service->sendNow(['client_id' => 'test-client', 'events' => [['name' => 'purchase']]]);
        $report = $service->report();

        $this->assertSame(12, $report['summary']['active_users']);
        $this->assertSame(40, $report['summary']['event_count']);
        $this->assertSame(2500.5, $report['summary']['revenue']);
        Http::assertSent(fn (HttpRequest $request): bool => str_starts_with($request->url(), 'https://www.google-analytics.com/mp/collect?')
            && str_contains($request->url(), 'measurement_id=G-TEST1234')
            && $request->data()['client_id'] === 'test-client'
        );
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://analyticsdata.googleapis.com/v1beta/properties/123456789:runReport'
            && $request->hasHeader('Authorization', 'Bearer google-oauth-token')
        );
    }

    public function test_search_console_can_verify_read_performance_and_submit_sitemap(): void
    {
        $tokens = Mockery::mock(GoogleServiceAccountTokenService::class);
        $tokens->shouldReceive('accessToken')->times(3)->andReturn('search-oauth-token');
        $service = new GoogleSearchConsoleService($tokens);

        Http::fake(function (HttpRequest $request) {
            if ($request->method() === 'GET') {
                return Http::response(['siteUrl' => 'sc-domain:tisilo.com', 'permissionLevel' => 'siteOwner']);
            }
            if ($request->method() === 'PUT') {
                return Http::response('', 204);
            }
            if (($request->data()['dimensions'] ?? []) === ['query']) {
                return Http::response(['rows' => [[
                    'keys' => ['tisilo'], 'clicks' => 8, 'impressions' => 100, 'ctr' => 0.08, 'position' => 3.5,
                ]]]);
            }

            return Http::response(['rows' => [[
                'clicks' => 20, 'impressions' => 500, 'ctr' => 0.04, 'position' => 5.25,
            ]]]);
        });

        $this->assertSame('siteOwner', $service->site('{}', 'sc-domain:tisilo.com')['permissionLevel']);
        $performance = $service->performance('{}', 'sc-domain:tisilo.com');
        $this->assertSame(20, $performance['summary']['clicks']);
        $this->assertSame('tisilo', $performance['rows'][0]['query']);
        $this->assertTrue($service->submitSitemap('{}', 'sc-domain:tisilo.com', 'https://www.tisilo.com/sitemap.xml')['submitted']);
        Http::assertSent(fn (HttpRequest $request): bool => $request->hasHeader('Authorization', 'Bearer search-oauth-token'));
    }

    public function test_cloudflare_sync_reads_zone_settings_dns_and_traffic_analytics(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/*/settings' => Http::response(['success' => true, 'result' => [['id' => 'always_use_https', 'value' => 'on']]]),
            'https://api.cloudflare.com/client/v4/zones/*/dns_records*' => Http::response(['success' => true, 'result' => [['id' => 'dns-1']]]),
            'https://api.cloudflare.com/client/v4/graphql' => Http::response(['data' => ['viewer' => ['zones' => [[
                'httpRequests1dGroups' => [[
                    'dimensions' => ['date' => '2026-09-07'],
                    'sum' => ['requests' => 100, 'pageViews' => 70, 'bytes' => 1000, 'cachedRequests' => 80, 'cachedBytes' => 750, 'threats' => 3],
                    'uniq' => ['uniques' => 25],
                ]],
            ]]]]]),
        ]);
        $service = app(CloudflareApiService::class);
        $zoneId = str_repeat('a', 32);

        $this->assertCount(1, $service->zoneSettings('token', $zoneId));
        $this->assertCount(1, $service->dnsRecords('token', $zoneId));
        $traffic = $service->trafficAnalytics('token', $zoneId);
        $this->assertSame(100, $traffic['summary']['requests']);
        $this->assertSame(80.0, $traffic['summary']['cache_hit_rate']);
        $this->assertSame(3, $traffic['summary']['threats']);
    }

    private function enableGoogleAnalytics(): void
    {
        SiteSetting::put('google_analytics', [
            'enabled' => true,
            'enhanced_ecommerce' => true,
            'measurement_id' => 'G-TEST1234',
            'anonymize_ip' => true,
        ], ['measurement_protocol_secret' => 'private-ga-secret']);
    }

    private function order(): Order
    {
        return Order::query()->create([
            'customer_name' => 'Test Buyer',
            'customer_phone' => '01700000000',
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'payment_method' => 'cod',
            'subtotal_amount' => 1000,
            'shipping_amount' => 80,
            'total_amount' => 1080,
            'currency' => 'BDT',
            'shipping_address' => ['district' => 'Dhaka'],
            'marketing_attribution' => ['ga_client_id' => 'stored-ga-client'],
        ]);
    }
}
