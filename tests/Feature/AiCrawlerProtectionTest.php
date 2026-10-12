<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\VisitorEvent;
use App\Services\BotTrafficService;
use App\Services\SeoOverviewReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AiCrawlerProtectionTest extends TestCase
{
    use DatabaseTransactions;

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
