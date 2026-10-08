<?php

namespace App\Services;

use App\Jobs\SendSmsMessage;
use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SmsGatewayService
{
    public const CREATIVE_DESIGN_ENDPOINT = 'https://www.creativedesign.com.bd/api/smsapi';

    /** @return array{body?: string, message?: string, message_id?: string, provider?: string, response?: mixed, skipped?: bool, status?: string} */
    public function sendNow(string $number, string $message): array
    {
        $settings = SiteSetting::valuesFor('sms');

        if (! $this->boolean($settings['enabled'] ?? false)
            || ($settings['provider'] ?? null) !== 'creative_design') {
            return ['skipped' => true];
        }

        $apiKey = trim((string) (SiteSetting::secretsFor('sms')['creative_design_api_key'] ?? ''));
        if ($apiKey === '') {
            throw new RuntimeException('Save the encrypted Creative Design API key first.');
        }

        $phone = $this->normalizeBangladeshPhone($number);
        $message = trim($message);
        if ($message === '') {
            throw new RuntimeException('The SMS message cannot be empty.');
        }
        if (mb_strlen($message) > 1000) {
            throw new RuntimeException('The SMS message cannot exceed 1,000 characters.');
        }

        $response = Http::acceptJson()
            ->timeout(15)
            ->retry(2, 300, throw: false)
            ->get(self::CREATIVE_DESIGN_ENDPOINT, [
                'api_key' => $apiKey,
                'number' => $phone,
                'message' => $message,
                'type' => 'text',
            ]);

        $payload = $this->ensureSuccessful($response);

        return [
            'provider' => 'creative_design',
            'status' => 'success',
            'message' => (string) ($payload['msg'] ?? ''),
            'message_id' => (string) $payload['msg_id'],
            'response' => $payload,
            'body' => Str::limit(trim($response->body()), 500),
        ];
    }

    public function queueOrderPlaced(Order $order): void
    {
        $settings = SiteSetting::valuesFor('sms');
        if (! $this->boolean($settings['enabled'] ?? false)
            || ($settings['provider'] ?? null) !== 'creative_design'
            || blank(SiteSetting::secretsFor('sms')['creative_design_api_key'] ?? null)) {
            return;
        }

        if ($this->boolean($settings['order_confirmation'] ?? true) && filled($order->customer_phone)) {
            SendSmsMessage::dispatch(
                (string) $order->customer_phone,
                "আপনার Tisilo অর্ডার {$order->order_number} গ্রহণ করা হয়েছে। মোট: ৳".number_format((float) $order->total_amount, 2).'.',
            )->afterCommit();
        }

        if (! $this->boolean($settings['admin_new_order_alert'] ?? true)) {
            return;
        }

        $adminPhones = preg_split(
            '/[\s,;]+/',
            (string) (SiteSetting::secretsFor('sms')['admin_phone_list'] ?? ''),
            -1,
            PREG_SPLIT_NO_EMPTY,
        ) ?: [];

        collect($adminPhones)->unique()->each(function (string $phone) use ($order): void {
            SendSmsMessage::dispatch(
                $phone,
                "নতুন অর্ডার {$order->order_number}। ক্রেতা: {$order->customer_name}। মোট: ৳".number_format((float) $order->total_amount, 2).'.',
            )->afterCommit();
        });
    }

    /** @return array<string, mixed> */
    private function ensureSuccessful(Response $response): array
    {
        if (! $response->successful()) {
            throw new RuntimeException("Creative Design SMS request failed (HTTP {$response->status()}).");
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Creative Design returned an invalid JSON response.');
        }

        if (Str::lower(trim((string) ($payload['status'] ?? ''))) !== 'success') {
            $message = trim((string) ($payload['msg'] ?? ''));

            throw new RuntimeException($message !== ''
                ? 'Creative Design rejected the SMS request: '.Str::limit($message, 200)
                : 'Creative Design rejected the SMS request.');
        }

        if (trim((string) ($payload['msg_id'] ?? '')) === '') {
            throw new RuntimeException('Creative Design success response did not include a message ID.');
        }

        return $payload;
    }

    private function normalizeBangladeshPhone(string $number): string
    {
        $number = preg_replace('/\D+/', '', $number) ?? '';
        if (str_starts_with($number, '880') && strlen($number) === 13) {
            $number = '0'.substr($number, 3);
        }

        if (preg_match('/^01[3-9]\d{8}$/', $number) !== 1) {
            throw new RuntimeException('Enter a valid Bangladesh mobile number.');
        }

        return $number;
    }

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
