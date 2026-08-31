<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
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
