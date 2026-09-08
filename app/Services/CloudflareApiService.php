<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudflareApiService
{
    private const BASE_URL = 'https://api.cloudflare.com/client/v4';

    /** @return array<string, mixed> */
    public function zone(string $apiToken, string $zoneId): array
    {
        return $this->result(
            $this->client($apiToken)->get("zones/{$zoneId}"),
            'Cloudflare zone verification',
        );
    }

    /** @return array<string, mixed> */
    public function purgeEverything(string $apiToken, string $zoneId): array
    {
        return $this->result(
            $this->client($apiToken)->post("zones/{$zoneId}/purge_cache", [
                'purge_everything' => true,
            ]),
            'Cloudflare cache purge',
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function zoneSettings(string $apiToken, string $zoneId): array
    {
        $result = $this->result(
            $this->client($apiToken)->get("zones/{$zoneId}/settings"),
            'Cloudflare zone-settings sync',
        );

        return array_is_list($result) ? $result : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function dnsRecords(string $apiToken, string $zoneId): array
    {
        $result = $this->result(
            $this->client($apiToken)->get("zones/{$zoneId}/dns_records", ['per_page' => 100]),
            'Cloudflare DNS-record sync',
        );

        return array_is_list($result) ? $result : [];
    }

    /** @return array{summary: array<string, float|int>, rows: array<int, array<string, mixed>>} */
    public function trafficAnalytics(string $apiToken, string $zoneId, int $days = 14): array
    {
        $query = <<<'GRAPHQL'
query TisiloZoneTraffic($zoneTag: string!, $start: Date!, $end: Date!) {
  viewer {
    zones(filter: {zoneTag: $zoneTag}) {
      httpRequests1dGroups(
        limit: 31
        filter: {date_geq: $start, date_leq: $end}
        orderBy: [date_ASC]
      ) {
        dimensions { date }
        sum { requests bytes cachedRequests cachedBytes threats pageViews }
        uniq { uniques }
      }
    }
  }
}
GRAPHQL;

        $response = $this->client($apiToken)->post('graphql', [
            'query' => $query,
            'variables' => [
                'zoneTag' => $zoneId,
                'start' => now()->subDays(max(2, $days) - 1)->toDateString(),
                'end' => now()->toDateString(),
            ],
        ]);
        $payload = $response->json();
        $errors = is_array($payload) ? ($payload['errors'] ?? []) : [];
        if (! $response->successful() || $errors !== []) {
            $message = collect($errors)->pluck('message')->filter()->implode('; ');
            throw new RuntimeException('Cloudflare traffic-analytics sync failed'.($message ? ': '.$message : " (HTTP {$response->status()})"));
        }

        $rows = collect(Arr::get($payload, 'data.viewer.zones.0.httpRequests1dGroups', []))
            ->map(fn (array $row): array => [
                'date' => (string) Arr::get($row, 'dimensions.date', ''),
                'requests' => (int) Arr::get($row, 'sum.requests', 0),
                'page_views' => (int) Arr::get($row, 'sum.pageViews', 0),
                'bytes' => (int) Arr::get($row, 'sum.bytes', 0),
                'cached_requests' => (int) Arr::get($row, 'sum.cachedRequests', 0),
                'cached_bytes' => (int) Arr::get($row, 'sum.cachedBytes', 0),
                'threats' => (int) Arr::get($row, 'sum.threats', 0),
                'unique_visitors' => (int) Arr::get($row, 'uniq.uniques', 0),
            ])->values();
        $requests = (int) $rows->sum('requests');
        $bytes = (int) $rows->sum('bytes');

        return [
            'summary' => [
                'requests' => $requests,
                'page_views' => (int) $rows->sum('page_views'),
                'bandwidth_bytes' => $bytes,
                'cached_requests' => (int) $rows->sum('cached_requests'),
                'cached_bytes' => (int) $rows->sum('cached_bytes'),
                'cache_hit_rate' => $requests > 0 ? round(((int) $rows->sum('cached_requests') / $requests) * 100, 2) : 0,
                'bandwidth_saved_rate' => $bytes > 0 ? round(((int) $rows->sum('cached_bytes') / $bytes) * 100, 2) : 0,
                'threats' => (int) $rows->sum('threats'),
                'unique_visitors' => (int) $rows->max('unique_visitors'),
            ],
            'rows' => $rows->all(),
        ];
    }

    private function client(string $apiToken): PendingRequest
    {
        $apiToken = $this->normalizeApiToken($apiToken);

        if ($apiToken === '') {
            throw new RuntimeException('Cloudflare API token is missing.');
        }

        return Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->asJson()
            ->withToken($apiToken)
            ->timeout(15)
            ->retry(2, 250, throw: false);
    }

    private function normalizeApiToken(string $apiToken): string
    {
        // Admins commonly paste either the token, "Bearer <token>", or the
        // complete Authorization header. Laravel adds Bearer itself, so remove
        // those wrappers and copy/paste whitespace before building the header.
        $apiToken = preg_replace('/^\xEF\xBB\xBF/', '', trim($apiToken)) ?? '';
        $apiToken = trim($apiToken, " \t\n\r\0\x0B\"'");
        $apiToken = preg_replace('/^Authorization\s*:\s*/i', '', $apiToken) ?? $apiToken;
        $apiToken = preg_replace('/^Bearer\s+/i', '', trim($apiToken)) ?? $apiToken;
        $apiToken = preg_replace('/\s+/', '', trim($apiToken)) ?? $apiToken;

        if ($apiToken !== '' && preg_match('/[^\x21-\x7E]/', $apiToken)) {
            throw new RuntimeException('Cloudflare API token contains unsupported characters. Paste the token secret only.');
        }

        return $apiToken;
    }

    /** @return array<string, mixed> */
    private function result(Response $response, string $operation): array
    {
        $payload = $response->json();
        $success = is_array($payload) && ($payload['success'] ?? false) === true;

        if (! $response->successful() || ! $success) {
            $message = collect(is_array($payload) ? ($payload['errors'] ?? []) : [])
                ->pluck('message')
                ->filter()
                ->implode('; ');

            if (str_contains(strtolower($message), 'invalid request headers')) {
                $message .= '. Paste only the API Token secret—not a Global API Key, curl command, or an Authorization header—then save and retry.';
            }

            throw new RuntimeException(
                $operation.' failed'.($message !== '' ? ': '.$message : " (HTTP {$response->status()})"),
            );
        }

        $result = $payload['result'] ?? [];

        return is_array($result) ? $result : [];
    }
}
