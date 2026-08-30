<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudflareApiService
{
    private const BASE_URL = 'https://api.cloudflare.com/client/v4';

    private const RULE_DESCRIPTION = 'Tisilo: cache static storefront assets';

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

    /**
     * @return array{ruleset_id: string|null, rule_id: string|null, enabled: bool}
     */
    public function applyStaticAssetCacheRule(
        string $apiToken,
        string $zoneId,
        string $hostname,
        int $edgeTtl,
        int $browserTtl,
        bool $enabled = true,
    ): array {
        $hostname = strtolower(trim($hostname));

        if (! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $hostname)) {
            throw new RuntimeException('Cloudflare hostname is invalid.');
        }

        $entrypointResponse = $this->client($apiToken)
            ->get("zones/{$zoneId}/rulesets/phases/http_request_cache_settings/entrypoint");

        $rule = $this->staticAssetRule($hostname, $edgeTtl, $browserTtl, $enabled);

        if ($entrypointResponse->status() === 404) {
            $ruleset = $this->result(
                $this->client($apiToken)->post("zones/{$zoneId}/rulesets", [
                    'name' => 'Tisilo managed cache rules',
                    'description' => 'Cache rules managed from the Tisilo admin dashboard.',
                    'kind' => 'zone',
                    'phase' => 'http_request_cache_settings',
                    'rules' => [$rule],
                ]),
                'Cloudflare cache ruleset creation',
            );

            return [
                'ruleset_id' => $ruleset['id'] ?? null,
                'rule_id' => $this->managedRuleId($ruleset),
                'enabled' => $enabled,
            ];
        }

        $ruleset = $this->result($entrypointResponse, 'Cloudflare cache ruleset lookup');
        $rulesetId = $ruleset['id'] ?? null;

        if (! is_string($rulesetId) || $rulesetId === '') {
            throw new RuntimeException('Cloudflare did not return a cache ruleset ID.');
        }

        $existingRule = collect($ruleset['rules'] ?? [])->first(
            fn (mixed $candidate): bool => is_array($candidate)
                && ($candidate['description'] ?? null) === self::RULE_DESCRIPTION,
        );

        if (is_array($existingRule) && filled($existingRule['id'] ?? null)) {
            $updatedRuleset = $this->result(
                $this->client($apiToken)->patch(
                    "zones/{$zoneId}/rulesets/{$rulesetId}/rules/{$existingRule['id']}",
                    $rule,
                ),
                'Cloudflare cache rule update',
            );

            return [
                'ruleset_id' => $rulesetId,
                'rule_id' => $this->managedRuleId($updatedRuleset) ?? (string) $existingRule['id'],
                'enabled' => $enabled,
            ];
        }

        $updatedRuleset = $this->result(
            $this->client($apiToken)->post("zones/{$zoneId}/rulesets/{$rulesetId}/rules", $rule),
            'Cloudflare cache rule creation',
        );

        return [
            'ruleset_id' => $rulesetId,
            'rule_id' => $this->managedRuleId($updatedRuleset),
            'enabled' => $enabled,
        ];
    }

    private function client(string $apiToken): PendingRequest
    {
        if (trim($apiToken) === '') {
            throw new RuntimeException('Cloudflare API token is missing.');
        }

        return Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->asJson()
            ->withToken($apiToken)
            ->timeout(15)
            ->retry(2, 250, throw: false);
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

            throw new RuntimeException(
                $operation.' failed'.($message !== '' ? ': '.$message : " (HTTP {$response->status()})"),
            );
        }

        $result = $payload['result'] ?? [];

        return is_array($result) ? $result : [];
    }

    /** @return array<string, mixed> */
    private function staticAssetRule(
        string $hostname,
        int $edgeTtl,
        int $browserTtl,
        bool $enabled,
    ): array {
        $extensions = implode(' ', array_map(
            fn (string $extension): string => '"'.$extension.'"',
            [
                'avif', 'bmp', 'css', 'eot', 'gif', 'ico', 'jpeg', 'jpg', 'js',
                'mp3', 'mp4', 'ogg', 'otf', 'png', 'svg', 'ttf', 'webm', 'webp',
                'woff', 'woff2',
            ],
        ));

        return [
            'expression' => sprintf(
                '(http.host eq "%s" and http.request.uri.path.extension in {%s})',
                $hostname,
                $extensions,
            ),
            'description' => self::RULE_DESCRIPTION,
            'action' => 'set_cache_settings',
            'action_parameters' => [
                'cache' => true,
                'edge_ttl' => [
                    'mode' => 'override_origin',
                    'default' => max(60, $edgeTtl),
                ],
                'browser_ttl' => [
                    'mode' => 'override_origin',
                    'default' => max(60, $browserTtl),
                ],
                'cache_key' => [
                    'cache_deception_armor' => true,
                    'ignore_query_strings_order' => true,
                ],
                'serve_stale' => [
                    'disable_stale_while_updating' => false,
                ],
            ],
            'enabled' => $enabled,
        ];
    }

    private function managedRuleId(array $ruleset): ?string
    {
        $rule = collect($ruleset['rules'] ?? [])->first(
            fn (mixed $candidate): bool => is_array($candidate)
                && ($candidate['description'] ?? null) === self::RULE_DESCRIPTION,
        );

        return is_array($rule) && filled($rule['id'] ?? null) ? (string) $rule['id'] : null;
    }
}
