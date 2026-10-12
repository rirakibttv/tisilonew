<?php

namespace App\Services;

use Illuminate\Support\Str;

class BotTrafficService
{
    /**
     * Stable self-declared AI crawler/agent user-agent tokens.
     *
     * Search-engine crawlers such as Googlebot and Bingbot are deliberately
     * not included here. They are excluded from analytics, but remain able to
     * crawl the storefront for search indexing.
     *
     * @var array<int, array{name: string, token: string, category: string}>
     */
    private const BOT_SIGNATURES = [
        ['name' => 'GPTBot', 'token' => 'gptbot', 'category' => 'AI Bot'],
        ['name' => 'ChatGPT-User', 'token' => 'chatgpt-user', 'category' => 'AI Agent'],
        ['name' => 'OAI-SearchBot', 'token' => 'oai-searchbot', 'category' => 'AI Search Bot'],
        ['name' => 'ClaudeBot', 'token' => 'claudebot', 'category' => 'AI Bot'],
        ['name' => 'Claude-SearchBot', 'token' => 'claude-searchbot', 'category' => 'AI Search Bot'],
        ['name' => 'Claude-User', 'token' => 'claude-user', 'category' => 'AI Agent'],
        ['name' => 'Claude-Web', 'token' => 'claude-web', 'category' => 'AI Agent'],
        ['name' => 'Anthropic AI', 'token' => 'anthropic-ai', 'category' => 'AI Bot'],
        ['name' => 'PerplexityBot', 'token' => 'perplexitybot', 'category' => 'AI Search Bot'],
        ['name' => 'Perplexity-User', 'token' => 'perplexity-user', 'category' => 'AI Agent'],
        ['name' => 'CCBot', 'token' => 'ccbot', 'category' => 'AI/Data Crawler'],
        ['name' => 'Bytespider', 'token' => 'bytespider', 'category' => 'AI/Data Crawler'],
        ['name' => 'Cohere AI', 'token' => 'cohere-ai', 'category' => 'AI Bot'],
        ['name' => 'Google-Extended', 'token' => 'google-extended', 'category' => 'AI/Data Crawler'],
        ['name' => 'Applebot-Extended', 'token' => 'applebot-extended', 'category' => 'AI/Data Crawler'],
        ['name' => 'Amazonbot', 'token' => 'amazonbot', 'category' => 'AI Bot'],
        ['name' => 'YouBot', 'token' => 'youbot', 'category' => 'AI Search Bot'],
        ['name' => 'Diffbot', 'token' => 'diffbot', 'category' => 'AI/Data Crawler'],
        ['name' => 'ImagesiftBot', 'token' => 'imagesiftbot', 'category' => 'AI/Data Crawler'],
        ['name' => 'Meta-ExternalAgent', 'token' => 'meta-externalagent', 'category' => 'AI Bot'],
        ['name' => 'Omgili', 'token' => 'omgili', 'category' => 'AI/Data Crawler'],
        ['name' => 'Timpibot', 'token' => 'timpibot', 'category' => 'AI Search Bot'],
        ['name' => 'Googlebot', 'token' => 'googlebot', 'category' => 'Search Bot'],
        ['name' => 'Bingbot', 'token' => 'bingbot', 'category' => 'Search Bot'],
        ['name' => 'BingPreview', 'token' => 'bingpreview', 'category' => 'Search Bot'],
        ['name' => 'DuckDuckBot', 'token' => 'duckduckbot', 'category' => 'Search Bot'],
        ['name' => 'YandexBot', 'token' => 'yandexbot', 'category' => 'Search Bot'],
        ['name' => 'Baiduspider', 'token' => 'baiduspider', 'category' => 'Search Bot'],
        ['name' => 'Applebot', 'token' => 'applebot', 'category' => 'Search Bot'],
        ['name' => 'PetalBot', 'token' => 'petalbot', 'category' => 'Search Bot'],
        ['name' => 'Facebook Link Preview', 'token' => 'facebookexternalhit', 'category' => 'Social Bot'],
        ['name' => 'Facebook Bot', 'token' => 'facebot', 'category' => 'Social Bot'],
        ['name' => 'Twitterbot', 'token' => 'twitterbot', 'category' => 'Social Bot'],
        ['name' => 'LinkedInBot', 'token' => 'linkedinbot', 'category' => 'Social Bot'],
        ['name' => 'TelegramBot', 'token' => 'telegrambot', 'category' => 'Social Bot'],
        ['name' => 'Discordbot', 'token' => 'discordbot', 'category' => 'Social Bot'],
        ['name' => 'Slackbot', 'token' => 'slackbot', 'category' => 'Social Bot'],
        ['name' => 'AhrefsBot', 'token' => 'ahrefsbot', 'category' => 'SEO Bot'],
        ['name' => 'SemrushBot', 'token' => 'semrushbot', 'category' => 'SEO Bot'],
        ['name' => 'MJ12bot', 'token' => 'mj12bot', 'category' => 'SEO Bot'],
        ['name' => 'DotBot', 'token' => 'dotbot', 'category' => 'SEO Bot'],
        ['name' => 'UptimeRobot', 'token' => 'uptimerobot', 'category' => 'Monitoring Bot'],
        ['name' => 'cURL', 'token' => 'curl/', 'category' => 'Automation'],
        ['name' => 'Python Requests', 'token' => 'python-requests', 'category' => 'Automation'],
        ['name' => 'Go HTTP Client', 'token' => 'go-http-client', 'category' => 'Automation'],
        ['name' => 'Scrapy', 'token' => 'scrapy', 'category' => 'Automation'],
        ['name' => 'Selenium', 'token' => 'selenium', 'category' => 'Automation'],
        ['name' => 'PhantomJS', 'token' => 'phantomjs', 'category' => 'Automation'],
        ['name' => 'Headless Browser', 'token' => 'headless', 'category' => 'Automation'],
    ];

    /** @return array{name: string, category: string}|null */
    public function identify(?string $userAgent): ?array
    {
        $normalized = strtolower(trim((string) $userAgent));
        if ($normalized === '') {
            return null;
        }

        foreach ($this->botSignatures() as $signature) {
            if ($signature['token'] !== '' && str_contains($normalized, $signature['token'])) {
                return [
                    'name' => $signature['name'],
                    'category' => $signature['category'],
                ];
            }
        }

        if (preg_match('/([a-z0-9._-]*(?:bot|crawler|spider)[a-z0-9._-]*)/i', $normalized, $match)) {
            return [
                'name' => Str::limit($match[1], 80, ''),
                'category' => 'Other Bot',
            ];
        }

        return null;
    }

    public function isAutomatedAnalyticsTraffic(?string $userAgent): bool
    {
        $userAgent = trim((string) $userAgent);

        if ($userAgent === '' || $this->identify($userAgent) !== null) {
            return true;
        }

        return (bool) preg_match(
            '/bot|crawl|spider|slurp|headless|lighthouse|facebookexternalhit|curl|wget|python-requests|go-http-client|libwww-perl|scrapy|selenium|phantomjs/i',
            $userAgent,
        );
    }

    /** @return array<int, array{name: string, token: string, category: string}> */
    private function botSignatures(): array
    {
        $signatures = self::BOT_SIGNATURES;
        $extraTokens = config('security.bot_traffic.extra_user_agents', []);

        if (! is_array($extraTokens)) {
            return $signatures;
        }

        foreach ($extraTokens as $token) {
            $token = strtolower(trim((string) $token));
            if ($token !== '') {
                $signatures[] = [
                    'name' => Str::limit($token, 80, ''),
                    'token' => $token,
                    'category' => 'Custom Bot',
                ];
            }
        }

        return $signatures;
    }
}
