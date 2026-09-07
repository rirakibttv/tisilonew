<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BkashPaymentGateway
{
    /** @return array{payment_id: string, redirect_url: string, response: array<string, mixed>} */
    public function createPayment(Order $order, string $callbackUrl): array
    {
        $configuration = $this->configuration();
        $response = $this->authorizedRequest($configuration)
            ->post($configuration['base_url'].'/tokenized/checkout/create', [
                'mode' => '0011',
                'payerReference' => $order->customer_phone ?: $order->order_number,
                'callbackURL' => $callbackUrl,
                'amount' => number_format((float) $order->total_amount, 2, '.', ''),
                'currency' => $order->currency ?: 'BDT',
                'intent' => 'sale',
                'merchantInvoiceNumber' => $order->order_number,
            ]);

        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload) || blank($payload['paymentID'] ?? null) || blank($payload['bkashURL'] ?? null)) {
            throw new RuntimeException($this->failureMessage($payload, 'bKash payment তৈরি করা যায়নি।'));
        }

        return [
            'payment_id' => (string) $payload['paymentID'],
            'redirect_url' => (string) $payload['bkashURL'],
            'response' => Arr::only($payload, [
                'paymentID', 'paymentCreateTime', 'transactionStatus', 'amount', 'currency',
                'intent', 'merchantInvoiceNumber', 'statusCode', 'statusMessage',
            ]),
        ];
    }

    /** @return array<string, mixed> */
    public function executePayment(string $paymentId): array
    {
        $configuration = $this->configuration();
        $response = $this->authorizedRequest($configuration)
            ->post($configuration['base_url'].'/tokenized/checkout/execute', [
                'paymentID' => $paymentId,
            ]);
        $payload = $response->json();

        if (! $response->successful() || ! is_array($payload)) {
            throw new RuntimeException($this->failureMessage($payload, 'bKash payment যাচাই করা যায়নি।'));
        }

        return $payload;
    }

    /** @param array<string, string> $configuration */
    private function authorizedRequest(array $configuration): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(25)
            ->withHeaders([
                'Authorization' => $this->accessToken($configuration),
                'X-App-Key' => $configuration['app_key'],
            ]);
    }

    /** @param array<string, string> $configuration */
    private function accessToken(array $configuration): string
    {
        $cacheKey = 'payment:bkash:token:'.hash('sha256', $configuration['base_url'].'|'.$configuration['app_key']);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->withHeaders([
                'username' => $configuration['username'],
                'password' => $configuration['password'],
            ])
            ->post($configuration['base_url'].'/tokenized/checkout/token/grant', [
                'app_key' => $configuration['app_key'],
                'app_secret' => $configuration['app_secret'],
            ]);
        $payload = $response->json();

        if (! $response->successful() || ! is_array($payload) || blank($payload['id_token'] ?? null)) {
            throw new RuntimeException($this->failureMessage($payload, 'bKash authentication ব্যর্থ হয়েছে।'));
        }

        $token = (string) $payload['id_token'];
        $expiresIn = max(300, min(3300, (int) ($payload['expires_in'] ?? 3600) - 60));
        Cache::put($cacheKey, $token, now()->addSeconds($expiresIn));

        return $token;
    }

    /** @return array{base_url: string, username: string, password: string, app_key: string, app_secret: string} */
    private function configuration(): array
    {
        $settings = SiteSetting::valuesFor('payment');
        $secrets = SiteSetting::secretsFor('payment');
        $configuration = [
            'base_url' => ($settings['bkash_environment'] ?? 'sandbox') === 'production'
                ? 'https://tokenized.pay.bka.sh/v1.2.0-beta'
                : 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'username' => trim((string) ($settings['bkash_username'] ?? '')),
            'password' => trim((string) ($secrets['bkash_password'] ?? '')),
            'app_key' => trim((string) ($secrets['bkash_app_key'] ?? '')),
            'app_secret' => trim((string) ($secrets['bkash_app_secret'] ?? '')),
        ];

        if (collect(Arr::except($configuration, ['base_url']))->contains(fn (string $value): bool => $value === '')) {
            throw new RuntimeException('bKash gateway credentials অসম্পূর্ণ।');
        }

        return $configuration;
    }

    /** @param array<string, mixed>|null $payload */
    private function failureMessage(?array $payload, string $fallback): string
    {
        $message = trim((string) ($payload['statusMessage'] ?? $payload['errorMessage'] ?? ''));

        return $message !== '' ? $message : $fallback;
    }
}
