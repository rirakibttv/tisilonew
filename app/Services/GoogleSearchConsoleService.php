<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleSearchConsoleService
{
    private const BASE_URL = 'https://www.googleapis.com/webmasters/v3';

    private const SCOPE = 'https://www.googleapis.com/auth/webmasters';

    public function __construct(private GoogleServiceAccountTokenService $tokens) {}

    /** @return array<string, mixed> */
    public function site(string $serviceAccountJson, string $propertyUrl): array
    {
        return $this->result(
            $this->client($serviceAccountJson)->get($this->sitePath($propertyUrl)),
            'Search Console property verification',
        );
    }

    /** @return array<string, mixed> */
    public function submitSitemap(string $serviceAccountJson, string $propertyUrl, string $sitemapUrl): array
    {
        $response = $this->client($serviceAccountJson)
            ->withBody('')
            ->send(
                'PUT',
                $this->sitePath($propertyUrl).'/sitemaps/'.rawurlencode($sitemapUrl),
            );

        if (! $response->successful()) {
            $this->throwFailure($response, 'Search Console sitemap submission');
        }

        return ['submitted' => true, 'sitemap_url' => $sitemapUrl];
    }

    /** @return array{summary: array<string, float|int>, rows: array<int, array<string, mixed>>} */
    public function performance(string $serviceAccountJson, string $propertyUrl, int $days = 28): array
    {
        $end = now()->subDays(2)->toDateString();
        $start = now()->subDays(max(3, $days) + 1)->toDateString();
        $path = $this->sitePath($propertyUrl).'/searchAnalytics/query';
        $client = $this->client($serviceAccountJson);

        $summaryResponse = $client->post($path, [
            'startDate' => $start,
            'endDate' => $end,
            'dataState' => 'all',
        ]);
        $summaryPayload = $this->result($summaryResponse, 'Search Console performance summary');

        $rowsResponse = $client->post($path, [
            'startDate' => $start,
            'endDate' => $end,
            'dimensions' => ['query'],
            'dataState' => 'all',
            'rowLimit' => 25,
        ]);
        $rowsPayload = $this->result($rowsResponse, 'Search Console top-query report');
        $summary = $summaryPayload['rows'][0] ?? [];

        return [
            'summary' => [
                'clicks' => (int) round((float) ($summary['clicks'] ?? 0)),
                'impressions' => (int) round((float) ($summary['impressions'] ?? 0)),
                'ctr' => round((float) ($summary['ctr'] ?? 0) * 100, 2),
                'position' => round((float) ($summary['position'] ?? 0), 2),
            ],
            'rows' => collect($rowsPayload['rows'] ?? [])->map(fn (array $row): array => [
                'query' => (string) ($row['keys'][0] ?? ''),
                'clicks' => (int) round((float) ($row['clicks'] ?? 0)),
                'impressions' => (int) round((float) ($row['impressions'] ?? 0)),
                'ctr' => round((float) ($row['ctr'] ?? 0) * 100, 2),
                'position' => round((float) ($row['position'] ?? 0), 2),
            ])->values()->all(),
        ];
    }

    private function client(string $serviceAccountJson): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->asJson()
            ->withToken($this->tokens->accessToken($serviceAccountJson, [self::SCOPE]))
            ->timeout(20)
            ->retry(2, 250, throw: false);
    }

    private function sitePath(string $propertyUrl): string
    {
        $propertyUrl = trim($propertyUrl);
        if ($propertyUrl === '' || (! str_starts_with($propertyUrl, 'sc-domain:') && ! filter_var($propertyUrl, FILTER_VALIDATE_URL))) {
            throw new RuntimeException('Save a valid URL-prefix or sc-domain Search Console property first.');
        }

        return 'sites/'.rawurlencode($propertyUrl);
    }

    /** @return array<string, mixed> */
    private function result(Response $response, string $operation): array
    {
        if (! $response->successful()) {
            $this->throwFailure($response, $operation);
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    private function throwFailure(Response $response, string $operation): never
    {
        $message = trim((string) ($response->json('error.message') ?: $response->json('error_description')));
        throw new RuntimeException($operation.' failed'.($message !== '' ? ': '.$message : " (HTTP {$response->status()})"));
    }
}
