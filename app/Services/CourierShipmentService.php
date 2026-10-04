<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ShippingPartner;
use App\Models\SiteSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CourierShipmentService
{
    /** @return array<int, string> */
    public function availablePartnerOptions(): array
    {
        $settings = SiteSetting::valuesFor('courier');

        return ShippingPartner::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(function (ShippingPartner $partner) use ($settings): bool {
                $provider = $this->providerFor($partner);

                return in_array($provider, ['steadfast', 'pathao'], true)
                    && filter_var($settings[$provider.'_enabled'] ?? false, FILTER_VALIDATE_BOOL);
            })
            ->mapWithKeys(fn (ShippingPartner $partner): array => [
                $partner->getKey() => $partner->name.' · '.Str::headline($this->providerFor($partner)),
            ])
            ->all();
    }

    /** @return array{tracking_number: string, shipping_status: string} */
    public function book(Order $order, ShippingPartner $partner): array
    {
        $provider = $this->providerFor($partner);

        return match ($provider) {
            'steadfast' => $this->bookSteadfast($order),
            'pathao' => $this->bookPathao($order),
            default => throw new RuntimeException("{$partner->name}-এর automatic courier booking এখনো configured নয়।"),
        };
    }

    public function fetchStatus(Order $order): string
    {
        $order->loadMissing('shippingPartner');

        if (! $order->shippingPartner || blank($order->tracking_number)) {
            throw new RuntimeException('Shipping partner এবং Shipping ID দুটোই প্রয়োজন।');
        }

        return match ($this->providerFor($order->shippingPartner)) {
            'steadfast' => $this->steadfastStatus($order),
            'pathao' => $this->pathaoStatus($order),
            default => throw new RuntimeException('এই shipping partner-এর status sync configured নয়।'),
        };
    }

    /** @return array{tracking_number: string, shipping_status: string} */
    private function bookSteadfast(Order $order): array
    {
        $configuration = $this->configuration('steadfast');
        $response = $this->steadfastClient($configuration)
            ->post($configuration['endpoint'].'/create_order', [
                'invoice' => $order->order_number,
                'recipient_name' => Str::limit($order->customer_name, 100, ''),
                'recipient_phone' => $this->phone($order),
                'recipient_email' => $order->customer_email,
                'recipient_address' => $this->address($order),
                'cod_amount' => $this->cashToCollect($order),
                'note' => Str::limit((string) $order->notes, 250, ''),
                'item_description' => $this->itemDescription($order),
                'total_lot' => $this->itemQuantity($order),
                'delivery_type' => 0,
            ]);
        $decoded = $response->json();
        $payload = is_array($decoded) ? $decoded : null;
        $consignment = data_get($payload, 'consignment', data_get($payload, 'data', []));
        $trackingNumber = data_get($consignment, 'tracking_code') ?: data_get($consignment, 'consignment_id');

        if (! $response->successful() || ! is_array($payload) || blank($trackingNumber)) {
            throw new RuntimeException($this->failureMessage($payload, 'Steadfast shipment তৈরি করা যায়নি।'));
        }

        return [
            'tracking_number' => (string) $trackingNumber,
            'shipping_status' => $this->normalizeStatus((string) (data_get($consignment, 'status') ?: 'in_review')),
        ];
    }

    private function steadfastStatus(Order $order): string
    {
        $configuration = $this->configuration('steadfast');
        $response = $this->steadfastClient($configuration)
            ->get($configuration['endpoint'].'/status_by_trackingcode/'.rawurlencode((string) $order->tracking_number));
        $decoded = $response->json();
        $payload = is_array($decoded) ? $decoded : null;
        $status = data_get($payload, 'delivery_status') ?: data_get($payload, 'status');

        if (! $response->successful() || ! is_array($payload) || blank($status) || is_numeric($status)) {
            throw new RuntimeException($this->failureMessage($payload, 'Steadfast shipping status পাওয়া যায়নি।'));
        }

        return $this->normalizeStatus((string) $status);
    }

    /** @return array{tracking_number: string, shipping_status: string} */
    private function bookPathao(Order $order): array
    {
        $configuration = $this->configuration('pathao');
        $order->loadMissing('shippingRegion');
        $region = $order->shippingRegion;

        if (! $region?->pathao_city_id || ! $region->pathao_zone_id || ! $region->pathao_area_id) {
            throw new RuntimeException('এই Shipping Region-এ Pathao City, Zone এবং Area ID সেট করা নেই।');
        }

        if (blank($configuration['store_id'])) {
            throw new RuntimeException('Pathao Store ID configured নয়।');
        }

        $response = $this->pathaoClient($configuration)
            ->post($configuration['endpoint'].'/aladdin/api/v1/orders', [
                'store_id' => (int) $configuration['store_id'],
                'merchant_order_id' => $order->order_number,
                'recipient_name' => Str::limit($order->customer_name, 100, ''),
                'recipient_phone' => $this->phone($order),
                'recipient_address' => $this->address($order),
                'recipient_city' => (int) $region->pathao_city_id,
                'recipient_zone' => (int) $region->pathao_zone_id,
                'recipient_area' => (int) $region->pathao_area_id,
                'delivery_type' => 48,
                'item_type' => 2,
                'special_instruction' => Str::limit((string) $order->notes, 250, ''),
                'item_quantity' => $this->itemQuantity($order),
                'item_weight' => 0.5,
                'amount_to_collect' => $this->cashToCollect($order),
                'item_description' => $this->itemDescription($order),
            ]);
        $decoded = $response->json();
        $payload = is_array($decoded) ? $decoded : null;
        $trackingNumber = data_get($payload, 'data.consignment_id')
            ?: data_get($payload, 'consignment_id')
            ?: data_get($payload, 'data.tracking_number');

        if (! $response->successful() || ! is_array($payload) || blank($trackingNumber)) {
            throw new RuntimeException($this->failureMessage($payload, 'Pathao shipment তৈরি করা যায়নি।'));
        }

        return [
            'tracking_number' => (string) $trackingNumber,
            'shipping_status' => $this->normalizeStatus((string) (data_get($payload, 'data.order_status') ?: 'pending')),
        ];
    }

    private function pathaoStatus(Order $order): string
    {
        $configuration = $this->configuration('pathao');
        $response = $this->pathaoClient($configuration)
            ->get($configuration['endpoint'].'/aladdin/api/v1/orders/'.rawurlencode((string) $order->tracking_number).'/info');
        $decoded = $response->json();
        $payload = is_array($decoded) ? $decoded : null;
        $status = data_get($payload, 'data.order_status_slug')
            ?: data_get($payload, 'data.order_status')
            ?: data_get($payload, 'data.status');

        if (! $response->successful() || ! is_array($payload) || blank($status)) {
            throw new RuntimeException($this->failureMessage($payload, 'Pathao shipping status পাওয়া যায়নি।'));
        }

        return $this->normalizeStatus((string) $status);
    }

    /** @param array<string, string> $configuration */
    private function steadfastClient(array $configuration): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(25)
            ->retry(2, 300, throw: false)
            ->withHeaders([
                'Api-Key' => $configuration['api_key'],
                'Secret-Key' => $configuration['secret_key'],
            ]);
    }

    /** @param array<string, string> $configuration */
    private function pathaoClient(array $configuration): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(25)
            ->retry(2, 300, throw: false)
            ->withToken($this->pathaoToken($configuration));
    }

    /** @param array<string, string> $configuration */
    private function pathaoToken(array $configuration): string
    {
        if (filled($configuration['access_token'])) {
            return $configuration['access_token'];
        }

        foreach (['client_id', 'client_secret', 'username', 'password'] as $field) {
            if (blank($configuration[$field])) {
                throw new RuntimeException('Pathao API credentials অসম্পূর্ণ।');
            }
        }

        $cacheKey = 'courier:pathao:token:'.hash('sha256', $configuration['endpoint'].'|'.$configuration['client_id'].'|'.$configuration['username']);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::acceptJson()->asJson()->timeout(20)->retry(2, 300, throw: false)
            ->post($configuration['endpoint'].'/aladdin/api/v1/issue-token', [
                'client_id' => $configuration['client_id'],
                'client_secret' => $configuration['client_secret'],
                'username' => $configuration['username'],
                'password' => $configuration['password'],
                'grant_type' => 'password',
            ]);
        $decoded = $response->json();
        $payload = is_array($decoded) ? $decoded : null;
        $token = data_get($payload, 'access_token');

        if (! $response->successful() || ! is_array($payload) || blank($token)) {
            throw new RuntimeException($this->failureMessage($payload, 'Pathao authentication ব্যর্থ হয়েছে।'));
        }

        Cache::put($cacheKey, (string) $token, now()->addSeconds(max(300, (int) data_get($payload, 'expires_in', 3600) - 120)));

        return (string) $token;
    }

    /** @return array<string, string> */
    private function configuration(string $provider): array
    {
        $settings = SiteSetting::valuesFor('courier');
        $secrets = SiteSetting::secretsFor('courier');

        if (! filter_var($settings[$provider.'_enabled'] ?? false, FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException(Str::headline($provider).' Courier API enabled নয়।');
        }

        if ($provider === 'steadfast') {
            $configuration = [
                'endpoint' => rtrim((string) (($settings['steadfast_endpoint'] ?? '') ?: 'https://portal.packzy.com/api/v1'), '/'),
                'api_key' => trim((string) ($secrets['steadfast_api_key'] ?? '')),
                'secret_key' => trim((string) ($secrets['steadfast_secret_key'] ?? '')),
            ];

            if (blank($configuration['api_key']) || blank($configuration['secret_key'])) {
                throw new RuntimeException('Steadfast API credentials অসম্পূর্ণ।');
            }

            return $configuration;
        }

        $endpoint = trim((string) ($settings['pathao_endpoint'] ?? ''));
        if ($endpoint === '') {
            $endpoint = ($settings['pathao_environment'] ?? 'sandbox') === 'production'
                ? 'https://api-hermes.pathao.com'
                : 'https://courier-api-sandbox.pathao.com';
        }

        return [
            'endpoint' => rtrim($endpoint, '/'),
            'client_id' => trim((string) ($settings['pathao_client_id'] ?? '')),
            'client_secret' => trim((string) ($secrets['pathao_client_secret'] ?? '')),
            'username' => trim((string) ($settings['pathao_username'] ?? '')),
            'password' => trim((string) ($secrets['pathao_password'] ?? '')),
            'access_token' => trim((string) ($secrets['pathao_access_token'] ?? '')),
            'store_id' => trim((string) ($settings['pathao_store_id'] ?? '')),
        ];
    }

    private function providerFor(ShippingPartner $partner): string
    {
        $value = Str::lower(trim((string) ($partner->api_provider ?: $partner->code)));

        return match (true) {
            Str::contains($value, 'steadfast') => 'steadfast',
            Str::contains($value, 'pathao') => 'pathao',
            Str::contains($value, 'redx') => 'redx',
            default => $value,
        };
    }

    private function phone(Order $order): string
    {
        $phone = preg_replace('/\D+/', '', (string) $order->customer_phone) ?: '';

        if (strlen($phone) === 13 && str_starts_with($phone, '8801')) {
            $phone = '0'.substr($phone, 3);
        }

        if (strlen($phone) !== 11 || ! str_starts_with($phone, '01')) {
            throw new RuntimeException('Customer-এর ১১ সংখ্যার delivery phone প্রয়োজন।');
        }

        return $phone;
    }

    private function address(Order $order): string
    {
        $address = collect(Arr::only($order->shipping_address ?? [], [
            'address_line', 'upazila', 'district', 'division', 'postal_code',
        ]))->filter(fn ($value): bool => filled($value))->join(', ');

        if ($address === '') {
            throw new RuntimeException('Customer-এর delivery address পাওয়া যায়নি।');
        }

        return Str::limit($address, 250, '');
    }

    private function cashToCollect(Order $order): float
    {
        return $order->payment_method === 'cod' ? round((float) $order->total_amount, 2) : 0.0;
    }

    private function itemQuantity(Order $order): int
    {
        $order->loadMissing('items');

        return max(1, (int) $order->items->sum('quantity'));
    }

    private function itemDescription(Order $order): string
    {
        $order->loadMissing('items');

        return Str::limit($order->items->pluck('product_name')->filter()->unique()->join(', '), 250, '');
    }

    private function normalizeStatus(string $status): string
    {
        return Str::of($status)->trim()->lower()->replace([' ', '-'], '_')->limit(80, '')->toString();
    }

    /** @param array<string, mixed>|null $payload */
    private function failureMessage(?array $payload, string $fallback): string
    {
        $message = data_get($payload, 'message')
            ?: data_get($payload, 'error')
            ?: data_get($payload, 'errors.0.message');

        return filled($message) && is_scalar($message) ? (string) $message : $fallback;
    }
}
