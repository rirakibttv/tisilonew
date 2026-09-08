<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

class FraudCheckerService
{
    /** @return array{phone: string, provider: string, summary: array<string, int|float|string>, couriers: array<int, array<string, int|float|string>>, reports: array<int, array<string, mixed>>} */
    public function check(string $mobile): array
    {
        $phone = $this->normalizeBangladeshMobile($mobile);
        $settings = SiteSetting::valuesFor('fraud');
        $secrets = SiteSetting::secretsFor('fraud');

        if (! filter_var($settings['enabled'] ?? false, FILTER_VALIDATE_BOOL)) {
            throw new FraudCheckerApiException('Manage Fraud Checker থেকে integration চালু করুন।');
        }

        $endpoint = trim((string) ($settings['endpoint'] ?? ''));
        $apiKey = $this->normalizeSecret((string) ($secrets['fraud_api_key'] ?? ''));
        $provider = trim((string) ($settings['provider'] ?? 'BD Courier')) ?: 'BD Courier';

        if (! filter_var($endpoint, FILTER_VALIDATE_URL) || parse_url($endpoint, PHP_URL_SCHEME) !== 'https') {
            throw new FraudCheckerApiException('Manage Fraud Checker-এ একটি নিরাপদ HTTPS provider endpoint সেভ করুন।');
        }

        if ($apiKey === '') {
            throw new FraudCheckerApiException('Manage Fraud Checker-এ Fraud API Key সেভ করুন।');
        }

        $method = strtoupper((string) ($settings['http_method'] ?? 'POST'));
        $method = in_array($method, ['GET', 'POST'], true) ? $method : 'POST';
        $phoneField = trim((string) ($settings['phone_field'] ?? 'phone')) ?: 'phone';
        $authType = (string) ($settings['auth_type'] ?? 'bearer');
        if (! preg_match('/^[A-Za-z0-9_.-]+$/', $phoneField)) {
            throw new FraudCheckerApiException('Mobile field name-এ unsupported character আছে।');
        }
        $parameters = [$phoneField => $phone];
        $request = $this->client($settings);

        if ($authType === 'header') {
            $header = trim((string) ($settings['api_key_header'] ?? 'X-API-Key')) ?: 'X-API-Key';
            if (! preg_match('/^[A-Za-z0-9-]+$/', $header)) {
                throw new FraudCheckerApiException('API key header name-এ unsupported character আছে।');
            }
            $request = $request->withHeader($header, $apiKey);
        } elseif ($authType === 'query') {
            $keyParameter = trim((string) ($settings['api_key_parameter'] ?? 'api_key')) ?: 'api_key';
            if (! preg_match('/^[A-Za-z0-9_.-]+$/', $keyParameter)) {
                throw new FraudCheckerApiException('API key query parameter-এ unsupported character আছে।');
            }
            $parameters[$keyParameter] = $apiKey;
        } elseif ($authType !== 'none') {
            $request = $request->withToken($apiKey);
        }

        try {
            $response = $method === 'GET'
                ? $request->get($endpoint, $parameters)
                : $request->post($endpoint, $parameters);
        } catch (Throwable $exception) {
            throw new FraudCheckerApiException('Fraud provider-এর সাথে সংযোগ করা যায়নি: '.$exception->getMessage());
        }

        return $this->normalizeResponse($response, $phone, $provider, (float) ($settings['risk_threshold'] ?? 70));
    }

    public function normalizeBangladeshMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if (str_starts_with($digits, '880')) {
            $digits = '0'.substr($digits, 3);
        } elseif (str_starts_with($digits, '88') && strlen($digits) === 13) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            $digits = '0'.$digits;
        }

        if (! preg_match('/^01[3-9]\d{8}$/', $digits)) {
            throw new FraudCheckerApiException('সঠিক ১১ সংখ্যার বাংলাদেশি মোবাইল নম্বর লিখুন।');
        }

        return $digits;
    }

    /** @param array<string, mixed> $settings */
    private function client(array $settings): PendingRequest
    {
        $timeout = min(30, max(1, (int) ($settings['timeout_seconds'] ?? 8)));

        return Http::acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->connectTimeout(min(10, $timeout))
            ->retry(2, 250, throw: false);
    }

    /** @return array{phone: string, provider: string, summary: array<string, int|float|string>, couriers: array<int, array<string, int|float|string>>, reports: array<int, array<string, mixed>>} */
    private function normalizeResponse(Response $response, string $phone, string $provider, float $riskThreshold): array
    {
        $body = trim($response->body());
        $bodyLower = strtolower($body);
        $providerBlocked = in_array($response->status(), [401, 403, 429], true)
            || str_contains($bodyLower, 'imunify360')
            || str_contains($bodyLower, 'bot-protection')
            || str_contains($bodyLower, 'access denied');

        if ($providerBlocked) {
            throw new FraudCheckerApiException(
                'Provider request বন্ধ করেছে। Hosting server IP-টি provider-এর allowlist/whitelist-এ যোগ করুন।',
                true,
                $response->status(),
            );
        }

        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload)) {
            throw new FraudCheckerApiException("Fraud provider সঠিক JSON response দেয়নি (HTTP {$response->status()})।", false, $response->status());
        }

        $status = strtolower((string) ($payload['status'] ?? 'success'));
        if (in_array($status, ['error', 'failed', 'failure', 'false'], true) || ($payload['success'] ?? true) === false) {
            $message = trim((string) ($payload['message'] ?? $payload['error'] ?? 'Provider fraud check সম্পন্ন করতে পারেনি।'));
            throw new FraudCheckerApiException($message, false, $response->status());
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $summarySource = is_array($data['summary'] ?? null)
            ? $data['summary']
            : (is_array($payload['summary'] ?? null) ? $payload['summary'] : []);
        $couriers = $this->couriers($data, $summarySource);
        $total = $this->number($summarySource, ['total_parcel', 'total', 'total_orders', 'total_order'], (int) collect($couriers)->sum('total_parcel'));
        $success = $this->number($summarySource, ['success_parcel', 'success', 'delivered', 'delivered_parcel'], (int) collect($couriers)->sum('success_parcel'));
        $cancelled = $this->number($summarySource, ['cancelled_parcel', 'cancel_parcel', 'cancelled', 'canceled', 'returned'], (int) collect($couriers)->sum('cancelled_parcel'));
        $ratio = $this->decimal($summarySource, ['success_ratio', 'success_rate', 'delivery_rate'], $total > 0 ? round(($success / $total) * 100, 2) : 0.0);
        $ratio = min(100, max(0, $ratio));
        $safeThreshold = min(100, max(1, $riskThreshold));

        $riskLevel = match (true) {
            $total === 0 => 'no_history',
            $ratio >= max(90, $safeThreshold) => 'very_safe',
            $ratio >= $safeThreshold => 'safe',
            $ratio >= max(0, $safeThreshold - 25) => 'caution',
            default => 'high_risk',
        };

        return [
            'phone' => $phone,
            'provider' => $provider,
            'summary' => [
                'total_parcel' => $total,
                'success_parcel' => $success,
                'cancelled_parcel' => $cancelled,
                'success_ratio' => $ratio,
                'risk_level' => $riskLevel,
            ],
            'couriers' => $couriers,
            'reports' => collect(is_array($payload['reports'] ?? null) ? $payload['reports'] : ($data['reports'] ?? []))
                ->filter(fn (mixed $report): bool => is_array($report))
                ->take(25)
                ->map(fn (array $report): array => Arr::only($report, ['courier', 'name', 'order_id', 'status', 'date', 'message']))
                ->values()
                ->all(),
        ];
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $summary */
    private function couriers(array $data, array $summary): array
    {
        $source = $data['couriers'] ?? $data['courier_data'] ?? null;
        if (! is_array($source)) {
            $source = Arr::except($data, ['summary', 'reports', 'phone', 'mobile', 'status', 'message']);
        }

        return collect($source)->map(function (mixed $row, int|string $key) use ($summary): ?array {
            if (! is_array($row) || $row === $summary) {
                return null;
            }

            $name = trim((string) ($row['courier_name'] ?? $row['name'] ?? (is_string($key) ? $key : 'Courier')));
            $total = $this->number($row, ['total_parcel', 'total', 'total_orders', 'total_order']);
            $success = $this->number($row, ['success_parcel', 'success', 'delivered', 'delivered_parcel']);
            $cancelled = $this->number($row, ['cancelled_parcel', 'cancel_parcel', 'cancelled', 'canceled', 'returned']);
            $ratio = $this->decimal($row, ['success_ratio', 'success_rate', 'delivery_rate'], $total > 0 ? round(($success / $total) * 100, 2) : 0.0);

            if ($total === 0 && $success === 0 && $cancelled === 0) {
                return null;
            }

            return [
                'name' => $name !== '' ? str($name)->replace(['_', '-'], ' ')->title()->toString() : 'Courier',
                'total_parcel' => $total,
                'success_parcel' => $success,
                'cancelled_parcel' => $cancelled,
                'success_ratio' => min(100, max(0, $ratio)),
            ];
        })->filter()->take(25)->values()->all();
    }

    /** @param array<string, mixed> $source @param array<int, string> $keys */
    private function number(array $source, array $keys, int $default = 0): int
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_numeric($source[$key])) {
                return max(0, (int) $source[$key]);
            }
        }

        return $default;
    }

    /** @param array<string, mixed> $source @param array<int, string> $keys */
    private function decimal(array $source, array $keys, float $default = 0): float
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_numeric(str_replace('%', '', (string) $source[$key]))) {
                return round((float) str_replace('%', '', (string) $source[$key]), 2);
            }
        }

        return $default;
    }

    private function normalizeSecret(string $secret): string
    {
        $secret = trim($secret, " \t\n\r\0\x0B\"'");
        $secret = preg_replace('/^Authorization\s*:\s*/i', '', $secret) ?? $secret;

        return preg_replace('/^Bearer\s+/i', '', trim($secret)) ?? $secret;
    }
}
