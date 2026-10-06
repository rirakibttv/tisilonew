<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Jobs\SendMetaConversionEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\VisitorEvent;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MetaConversionsApiService
{
    /** @var array<string, string> */
    private const VISITOR_EVENTS = [
        'page_view' => 'PageView',
        'product_view' => 'ViewContent',
        'add_to_cart' => 'AddToCart',
        'initiate_checkout' => 'InitiateCheckout',
        'add_payment_info' => 'AddPaymentInfo',
        'purchase' => 'Purchase',
    ];

    /** @var array<string, string> */
    private const ORDER_STATUS_EVENTS = [
        OrderStatus::Confirmed->value => 'OrderConfirmed',
        OrderStatus::Processing->value => 'OrderProcessing',
        OrderStatus::Shipped->value => 'OrderShipped',
        OrderStatus::Delivered->value => 'OrderDelivered',
        OrderStatus::Cancelled->value => 'OrderCancelled',
        OrderStatus::Refunded->value => 'OrderRefunded',
    ];

    public function queueVisitorEvent(Request $request, VisitorEvent $visitorEvent): void
    {
        $eventName = self::VISITOR_EVENTS[$visitorEvent->event_type] ?? null;
        if (! $eventName || ! $this->isEnabledFor($eventName)) {
            return;
        }

        $order = $visitorEvent->order_id
            ? Order::query()->with('items')->find($visitorEvent->order_id)
            : null;

        SendMetaConversionEvent::dispatch($eventName, array_filter([
            'event_name' => $eventName,
            'event_time' => ($visitorEvent->occurred_at ?? now())->getTimestamp(),
            'event_id' => $visitorEvent->event_key ?: 'visitor-event-'.$visitorEvent->getKey(),
            'event_source_url' => $request->fullUrl(),
            'action_source' => 'website',
            'user_data' => $this->userData($request, $visitorEvent, $order),
            'custom_data' => $this->customData($visitorEvent, $order),
        ], fn (mixed $value): bool => $value !== null && $value !== []));
    }

    public function queueOrderStatus(Order $order): void
    {
        $status = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
        $eventName = self::ORDER_STATUS_EVENTS[$status] ?? null;
        if (! $eventName || ! $this->isEnabledFor($eventName)) {
            return;
        }

        $order->loadMissing(['items', 'landingPage']);
        SendMetaConversionEvent::dispatch($eventName, [
            'event_name' => $eventName,
            'event_time' => now()->getTimestamp(),
            'event_id' => Str::lower($eventName).'-'.$order->order_number,
            'event_source_url' => $order->landingPage?->public_url ?: url('/'),
            'action_source' => 'website',
            'user_data' => $this->userData(null, null, $order),
            'custom_data' => $this->orderCustomData($order, $status),
        ]);
    }

    /** @param array<string, mixed> $event */
    public function sendNow(string $eventName, array $event): array
    {
        $configuration = $this->configuration($eventName);
        if (! $configuration) {
            return ['skipped' => true];
        }

        $payload = [
            'data' => [[...$event, 'event_name' => $eventName]],
            'partner_agent' => 'tisilo_laravel_capi',
        ];
        if (filled($configuration['test_event_code'])) {
            $payload['test_event_code'] = $configuration['test_event_code'];
        }

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($configuration['access_token'])
            ->timeout(15)
            ->retry(3, 500, throw: false)
            ->post("https://graph.facebook.com/{$configuration['api_version']}/{$configuration['pixel_id']}/events", $payload);

        return $this->result($response, $eventName);
    }

    private function isEnabledFor(string $eventName): bool
    {
        return $this->configuration($eventName) !== null;
    }

    /** @return array{pixel_id:string,api_version:string,test_event_code:?string,access_token:string}|null */
    private function configuration(string $eventName): ?array
    {
        $values = SiteSetting::valuesFor('facebook_capi');
        $secrets = SiteSetting::secretsFor('facebook_capi');
        $events = is_array($values['events'] ?? null) ? $values['events'] : [];
        $pixelId = trim((string) ($values['pixel_id'] ?? ''));
        $version = trim((string) ($values['api_version'] ?? 'v23.0'));
        $token = trim((string) ($secrets['access_token'] ?? ''));

        if (! filter_var($values['enabled'] ?? false, FILTER_VALIDATE_BOOL)
            || ! in_array($eventName, $events, true)
            || ! preg_match('/^[0-9]{5,32}$/', $pixelId)
            || ! preg_match('/^v[0-9]{1,2}\.0$/', $version)
            || $token === '') {
            return null;
        }

        return [
            'pixel_id' => $pixelId,
            'api_version' => $version,
            'test_event_code' => filled($values['test_event_code'] ?? null) ? trim((string) $values['test_event_code']) : null,
            'access_token' => $token,
        ];
    }

    /** @return array<string, mixed> */
    private function userData(?Request $request, ?VisitorEvent $visitorEvent, ?Order $order): array
    {
        $attribution = $order?->marketing_attribution ?? [];
        $name = trim((string) $order?->customer_name);
        $parts = preg_split('/\s+/u', $name, 2) ?: [];
        $email = $this->hash($order?->customer_email, fn (string $value): string => strtolower(trim($value)));
        $normalizedPhone = $order?->customer_phone ? $this->phone((string) $order->customer_phone) : '';
        $phone = $normalizedPhone !== '' ? hash('sha256', $normalizedPhone) : null;
        $firstName = $this->hash($parts[0] ?? null, fn (string $value): string => $this->text($value));
        $lastName = $this->hash($parts[1] ?? null, fn (string $value): string => $this->text($value));
        $address = $order?->shipping_address ?? [];
        $externalId = $order?->user_id
            ? 'user:'.$order->user_id
            : ($visitorEvent?->visitor_id ?: ($normalizedPhone !== '' ? 'phone:'.$normalizedPhone : 'order:'.$order?->order_number));

        return array_filter([
            'em' => $email ? [$email] : null,
            'ph' => $phone ? [$phone] : null,
            'fn' => $firstName ? [$firstName] : null,
            'ln' => $lastName ? [$lastName] : null,
            'ct' => ($city = $this->hash($address['district'] ?? null, fn (string $value): string => $this->text($value))) ? [$city] : null,
            'st' => ($state = $this->hash($address['division'] ?? null, fn (string $value): string => $this->text($value))) ? [$state] : null,
            'country' => $order ? [hash('sha256', 'bd')] : null,
            'external_id' => filled($externalId) ? [hash('sha256', (string) $externalId)] : null,
            'client_ip_address' => $request?->ip(),
            'client_user_agent' => $request?->userAgent(),
            'fbp' => $request?->cookie('_fbp') ?: ($attribution['fbp'] ?? null),
            'fbc' => $request?->cookie('_fbc') ?: ($attribution['fbc'] ?? null),
        ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /** @return array<string, mixed>|null */
    private function customData(VisitorEvent $visitorEvent, ?Order $order): ?array
    {
        if ($order) {
            return $this->orderCustomData($order, $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status);
        }

        $metadata = $visitorEvent->metadata ?? [];
        $product = $visitorEvent->product_id
            ? Product::query()->with('category.parent.parent')->find($visitorEvent->product_id)
            : null;
        $data = array_filter([
            'currency' => $metadata['currency'] ?? ($visitorEvent->value !== null ? 'BDT' : null),
            'value' => $visitorEvent->value !== null ? (float) $visitorEvent->value : null,
            'content_ids' => $visitorEvent->product_id ? [(string) $visitorEvent->product_id] : null,
            'content_type' => $visitorEvent->product_id ? 'product' : null,
            'content_name' => $metadata['product_name'] ?? $product?->name,
            'content_category' => $product?->category?->hierarchicalName(),
            'num_items' => isset($metadata['quantity']) ? (int) $metadata['quantity'] : null,
        ], fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');

        return $data ?: null;
    }

    /** @return array<string, mixed> */
    private function orderCustomData(Order $order, string $status): array
    {
        $contents = $order->items->map(fn ($item): array => [
            'id' => (string) ($item->product_id ?: $item->sku ?: $item->product_name),
            'quantity' => (int) $item->quantity,
            'item_price' => (float) $item->unit_price,
        ])->values()->all();

        return [
            'currency' => $order->currency ?: 'BDT',
            'value' => (float) $order->total_amount,
            'order_id' => $order->order_number,
            'status' => $status,
            'content_ids' => collect($contents)->pluck('id')->all(),
            'contents' => $contents,
            'content_type' => 'product',
            'num_items' => collect($contents)->sum('quantity'),
        ];
    }

    private function text(string $value): string
    {
        return preg_replace('/[^\pL\pN]/u', '', mb_strtolower(trim($value))) ?? '';
    }

    private function phone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (str_starts_with($digits, '01')) {
            return '88'.$digits;
        }

        return $digits;
    }

    private function hash(mixed $value, callable $normalizer): ?string
    {
        $value = is_scalar($value) ? $normalizer((string) $value) : '';

        return $value !== '' ? hash('sha256', $value) : null;
    }

    /** @return array<string, mixed> */
    private function result(Response $response, string $eventName): array
    {
        $result = $response->json();
        if (! $response->successful() || ! is_array($result) || isset($result['error'])) {
            $message = is_array($result) ? Arr::get($result, 'error.message') : null;
            throw new RuntimeException("Meta CAPI {$eventName} failed".($message ? ': '.$message : " (HTTP {$response->status()})"));
        }

        return $result;
    }
}
