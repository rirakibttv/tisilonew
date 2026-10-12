<?php

namespace Tests\Feature;

use App\Services\BotTrafficService;
use Tests\TestCase;

class AiCrawlerProtectionTest extends TestCase
{
    public function test_known_ai_crawlers_are_blocked_before_the_storefront_loads(): void
    {
        foreach (['GPTBot/1.2', 'ChatGPT-User/1.0', 'Claude-User/1.0', 'PerplexityBot/1.0'] as $userAgent) {
            $this->withHeader('User-Agent', $userAgent)
                ->get('/')
                ->assertForbidden()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }
    }

    public function test_search_crawlers_and_normal_browsers_are_not_in_the_ai_blocklist(): void
    {
        $service = app(BotTrafficService::class);

        $this->assertNull($service->blockedAiCrawler('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'));
        $this->assertNull($service->blockedAiCrawler('Mozilla/5.0 Chrome/124.0 Safari/537.36'));
        $this->assertSame('GPTBot', $service->blockedAiCrawler('Mozilla/5.0; GPTBot/1.2'));
    }

    public function test_ai_crawlers_and_automation_clients_are_excluded_from_analytics(): void
    {
        $service = app(BotTrafficService::class);

        $this->assertTrue($service->isAutomatedAnalyticsTraffic('OAI-SearchBot/1.0'));
        $this->assertTrue($service->isAutomatedAnalyticsTraffic('curl/8.0'));
        $this->assertTrue($service->isAutomatedAnalyticsTraffic('Googlebot/2.1'));
        $this->assertFalse($service->isAutomatedAnalyticsTraffic('Mozilla/5.0 Chrome/124.0 Safari/537.36'));
    }

    public function test_robots_file_contains_ai_crawler_opt_outs_without_blocking_all_crawlers(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertIsString($robots);
        $this->assertStringContainsString("User-agent: *\nDisallow:", $robots);
        $this->assertStringContainsString("User-agent: GPTBot\nDisallow: /", $robots);
        $this->assertStringContainsString("User-agent: ClaudeBot\nDisallow: /", $robots);
        $this->assertStringNotContainsString("User-agent: Googlebot\nDisallow: /", $robots);
    }
}
