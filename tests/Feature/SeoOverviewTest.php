<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\VisitorEvent;
use App\Services\SeoOverviewReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeoOverviewTest extends TestCase
{
    use DatabaseTransactions;

    public function test_storefront_records_privacy_safe_attributed_events(): void
    {
        $visitorId = (string) Str::uuid();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.15'])
            ->withHeader('User-Agent', 'Mozilla/5.0 Chrome/124.0 Safari/537.36')
            ->postJson(route('visitor.analytics.track'), [
                'visitor_id' => $visitorId,
                'event_type' => 'page_view',
                'event_id' => 'page-'.$visitorId,
                'path' => '/products?utm_source=facebook',
                'referrer' => 'https://www.facebook.com/',
                'attribution' => [
                    'source' => 'facebook',
                    'medium' => 'paid_social',
                    'campaign' => 'eid-sale',
                    'click_source' => 'facebook',
                ],
            ])
            ->assertNoContent();

        $event = VisitorEvent::query()->where('event_key', 'page-'.$visitorId)->firstOrFail();

        $this->assertSame($visitorId, $event->visitor_id);
        $this->assertSame('/products', $event->path);
        $this->assertSame('facebook', $event->traffic_source);
        $this->assertSame('Facebook', $event->sourceLabel());
        $this->assertNotSame('203.0.113.15', $event->ip_hash);
        $this->assertSame(64, strlen((string) $event->ip_hash));
    }

    public function test_bots_are_ignored(): void
    {
        $before = VisitorEvent::query()->count();

        $this->withHeader('User-Agent', 'Googlebot/2.1')
            ->postJson(route('visitor.analytics.track'), [
                'event_type' => 'page_view',
            ])
            ->assertNoContent();

        $this->assertSame($before, VisitorEvent::query()->count());
    }

    public function test_reports_filter_facebook_and_google_attribution(): void
    {
        VisitorEvent::query()->create([
            'visitor_id' => (string) Str::uuid(),
            'event_type' => 'page_view',
            'traffic_source' => 'facebook',
            'occurred_at' => now(),
        ]);
        VisitorEvent::query()->create([
            'visitor_id' => (string) Str::uuid(),
            'event_type' => 'page_view',
            'traffic_source' => 'google',
            'occurred_at' => now(),
        ]);

        $facebook = app(SeoOverviewReportService::class)->report(7, 'facebook');
        $google = app(SeoOverviewReportService::class)->report(7, 'google');

        $this->assertSame(1, $facebook['metrics']['visitors']);
        $this->assertSame(1, $google['metrics']['visitors']);
        $this->assertSame('facebook', $facebook['platform']);
        $this->assertSame('google', $google['platform']);
    }

    public function test_active_admin_can_open_each_seo_overview_report(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        foreach (['all', 'facebook', 'google'] as $platform) {
            $this->actingAs($admin)
                ->get('/admin/seo-overview?platform='.$platform.'&period=7')
                ->assertOk()
                ->assertSee($platform === 'all' ? 'Visitor Analytics' : ucfirst($platform).' Overview')
                ->assertSee('Conversion funnel');
        }
    }

    public function test_visitor_analytics_opens_with_today_selected_by_default(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/seo-overview?platform=all')
            ->assertOk();

        $response->assertSee('Visitor Analytics');
        $response->assertSee('period=1', false);
        $response->assertSee('bg-white text-indigo-950 shadow-lg', false);
    }
}
