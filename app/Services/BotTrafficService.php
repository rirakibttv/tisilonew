<?php

namespace App\Services;

class BotTrafficService
{
    /**
     * Stable self-declared AI crawler/agent user-agent tokens.
     *
     * Search-engine crawlers such as Googlebot and Bingbot are deliberately
     * not included here. They are excluded from analytics, but remain able to
     * crawl the storefront for search indexing.
     *
     * @var array<string, string>
     */
    private const AI_CRAWLERS = [
        'GPTBot' => 'gptbot',
        'ChatGPT-User' => 'chatgpt-user',
        'OAI-SearchBot' => 'oai-searchbot',
        'ClaudeBot' => 'claudebot',
        'Claude-SearchBot' => 'claude-searchbot',
        'Claude-User' => 'claude-user',
        'Claude-Web' => 'claude-web',
        'anthropic-ai' => 'anthropic-ai',
        'PerplexityBot' => 'perplexitybot',
        'Perplexity-User' => 'perplexity-user',
        'CCBot' => 'ccbot',
        'Bytespider' => 'bytespider',
        'cohere-ai' => 'cohere-ai',
        'Google-Extended' => 'google-extended',
        'Applebot-Extended' => 'applebot-extended',
        'Amazonbot' => 'amazonbot',
        'YouBot' => 'youbot',
        'Diffbot' => 'diffbot',
        'ImagesiftBot' => 'imagesiftbot',
        'Meta-ExternalAgent' => 'meta-externalagent',
        'omgili' => 'omgili',
        'Timpibot' => 'timpibot',
    ];

    public function blockedAiCrawler(?string $userAgent): ?string
    {
        $normalized = strtolower(trim((string) $userAgent));
        if ($normalized === '') {
            return null;
        }

        foreach ($this->aiCrawlerTokens() as $name => $token) {
            if ($token !== '' && str_contains($normalized, $token)) {
                return $name;
            }
        }

        return null;
    }

    public function isAutomatedAnalyticsTraffic(?string $userAgent): bool
    {
        $userAgent = trim((string) $userAgent);

        if ($userAgent === '' || $this->blockedAiCrawler($userAgent) !== null) {
            return true;
        }

        return (bool) preg_match(
            '/bot|crawl|spider|slurp|headless|lighthouse|facebookexternalhit|curl|wget|python-requests|go-http-client|libwww-perl|scrapy|selenium|phantomjs/i',
            $userAgent,
        );
    }

    /** @return array<string, string> */
    private function aiCrawlerTokens(): array
    {
        $tokens = self::AI_CRAWLERS;
        $extraTokens = config('security.ai_crawler_protection.extra_user_agents', []);

        if (! is_array($extraTokens)) {
            return $tokens;
        }

        foreach ($extraTokens as $token) {
            $token = strtolower(trim((string) $token));
            if ($token !== '') {
                $tokens[$token] = $token;
            }
        }

        return $tokens;
    }
}
