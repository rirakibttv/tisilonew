<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\VisitorEvent;
use App\Services\BotTrafficControlService;
use App\Services\BotTrafficService;
use App\Services\ClientIpFingerprintService;
use App\Services\SeoOverviewReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AiCrawlerProtectionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        SiteSetting::query()->where('key', 'bot_security')->delete();
        SiteSetting::forget('bot_security');
    }

    protected function tearDown(): void
    {
        SiteSetting::forget('bot_security');
        parent::tearDown();
    }

    public function test_known_ai_crawlers_are_allowed_and_recorded_by_name(): void
    {
        $this->registerStorefrontProbe();
        $beforeMetrics = app(SeoOverviewReportService::class)->report(1)['metrics'];

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.71'])
            ->withHeader('User-Agent', 'Mozilla/5.0; GPTBot/1.2')
            ->get('/_bot-traffic-probe')
            ->assertOk()
            ->assertSee('ok');

        $event = VisitorEvent::query()
            ->where('event_type', 'bot_visit')
            ->where('traffic_campaign', 'GPTBot')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('bot', $event->traffic_source);
        $this->assertSame('AI Bot', $event->traffic_medium);
        $this->assertSame('/_bot-traffic-probe', $event->path);

        $report = app(SeoOverviewReportService::class)->report(1);
        $this->assertSame($beforeMetrics, $report['metrics']);
        $this->assertNotNull($report['botTraffic']->firstWhere('bot_name', 'GPTBot'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);
        $this->actingAs($admin)
            ->get('/admin/seo-overview?platform=all&period=1')
            ->assertOk()
            ->assertSee('Bot traffic identification')
            ->assertSee('GPTBot');
    }

    public function test_search_social_and_ai_bots_are_classified_without_blocking_search(): void
    {
        $service = app(BotTrafficService::class);

        $this->assertSame(
            ['name' => 'Googlebot', 'category' => 'Search Bot'],
            $service->identify('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'),
        );
        $this->assertSame(
            ['name' => 'Facebook Link Preview', 'category' => 'Social Bot'],
            $service->identify('facebookexternalhit/1.1'),
        );
        $this->assertSame(
            ['name' => 'Claude-User', 'category' => 'AI Agent'],
            $service->identify('Claude-User/1.0'),
        );
        $this->assertNull($service->identify('Mozilla/5.0 Chrome/124.0 Safari/537.36'));
    }

    public function test_ai_crawlers_and_automation_clients_do_not_create_browser_analytics_events(): void
    {
        $service = app(BotTrafficService::class);

        $this->assertTrue($service->isAutomatedAnalyticsTraffic('OAI-SearchBot/1.0'));
        $this->assertTrue($service->isAutomatedAnalyticsTraffic('curl/8.0'));
        $this->assertTrue($service->isAutomatedAnalyticsTraffic('Googlebot/2.1'));
        $this->assertFalse($service->isAutomatedAnalyticsTraffic('Mozilla/5.0 Chrome/124.0 Safari/537.36'));
    }

    public function test_an_admin_selected_bot_identity_can_be_blocked_and_allowed_again(): void
    {
        $this->registerStorefrontProbe();
        $control = app(BotTrafficControlService::class);
        $control->blockBot('GPTBot');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.91'])
            ->withHeader('User-Agent', 'Mozilla/5.0; GPTBot/1.2')
            ->get('/_bot-traffic-probe')
            ->assertForbidden()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $control->unblockBot('GPTBot');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.91'])
            ->withHeader('User-Agent', 'Mozilla/5.0; GPTBot/1.2')
            ->get('/_bot-traffic-probe')
            ->assertOk();
    }

    public function test_an_admin_selected_ip_fingerprint_is_blocked_without_storing_the_raw_ip(): void
    {
        $this->registerStorefrontProbe();
        $control = app(BotTrafficControlService::class);
        $ip = '203.0.113.92';
        $ipHash = app(ClientIpFingerprintService::class)->fingerprintIp($ip);
        $this->assertNotNull($ipHash);

        $control->blockIpHash($ipHash);

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('User-Agent', 'Mozilla/5.0 Chrome/124.0 Safari/537.36')
            ->get('/_bot-traffic-probe')
            ->assertForbidden();

        $stored = SiteSetting::valuesFor('bot_security');
        $this->assertContains($ipHash, $stored['blocked_ip_hashes']);
        $this->assertStringNotContainsString($ip, json_encode($stored, JSON_THROW_ON_ERROR));

        $control->unblockIpHash($ipHash);
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('User-Agent', 'Mozilla/5.0 Chrome/124.0 Safari/537.36')
            ->get('/_bot-traffic-probe')
            ->assertOk();
    }

    public function test_repeated_bot_requests_are_flagged_as_abnormal_in_the_report(): void
    {
        config()->set('security.bot_traffic.abnormal_requests_per_hour', 3);
        $this->registerStorefrontProbe();

        foreach (range(1, 3) as $request) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.93'])
                ->withHeader('User-Agent', 'SemrushBot/7~bl')
                ->get('/_bot-traffic-probe')
                ->assertOk();
        }

        $source = app(SeoOverviewReportService::class)
            ->report(1)['botSources']
            ->firstWhere('bot_name', 'SemrushBot');

        $this->assertNotNull($source);
        $this->assertSame(3, $source->requests_last_hour);
        $this->assertTrue($source->is_abnormal);
    }

    public function test_robots_file_allows_crawlers(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertIsString($robots);
        $this->assertStringContainsString('User-agent: *', $robots);
        $this->assertStringContainsString('Disallow:', $robots);
        $this->assertStringNotContainsString('User-agent: GPTBot', $robots);
    }

    private function registerStorefrontProbe(): void
    {
        if (! Route::has('store.bot-traffic-probe')) {
            Route::middleware('web')
                ->get('/_bot-traffic-probe', fn () => response('ok'))
                ->name('store.bot-traffic-probe');
        }
    }
}
