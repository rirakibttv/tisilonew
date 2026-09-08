<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Jobs\SendGoogleAnalyticsEvent;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\VisitorEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleAnalyticsService
{
    /** @var array<string, string> */
    private const VISITOR_EVENTS = [
        'page_view' => 'page_view',
        'product_view' => 'view_item',
        'add_to_cart' => 'add_to_cart',
        'initiate_checkout' => 'begin_checkout',
        'add_payment_info' => 'add_payment_info',
        'purchase' => 'purchase',
    ];

    /** @var array<string, string> */
    private const ORDER_STATUS_EVENTS = [
        OrderStatus::Confirmed->value => 'order_confirmed',
        OrderStatus::Processing->value => 'order_processing',
        OrderStatus::Shipped->value => 'order_shipped',
        OrderStatus::Delivered->value => 'order_delivered',
        OrderStatus::Cancelled->value => 'order_cancelled',
        OrderStatus::Refunded->value => 'refund',
    ];

    public function __construct(private GoogleServiceAccountTokenService $tokens) {}

    public function queueVisitorEvent(Request $request, VisitorEvent $visitorEvent): void
    {
        $eventName = self::VISITOR_EVENTS[$visitorEvent->event_type] ?? null;
        if (! $eventName || ! $this->measurementConfiguration()) {
            return;
        }

        $order = $visitorEvent->order_id
            ? Order::query()->with('items')->find($visitorEvent->order_id)
            : null;

        SendGoogleAnalyticsEvent::dispatch(array_filter([
            'client_id' => $visitorEvent->visitor_id,
            'user_id' => $visitorEvent->customer_id ? (string) $visitorEvent->customer_id : null,
            'timestamp_micros' => (int) (($visitorEvent->occurred_at ?? now())->getTimestamp() * 1000000),
            'events' => [[
                'name' => $eventName,
                'params' => $this->eventParameters($request, $visitorEvent, $order),
            ]],
        ], fn (mixed $value): bool => $value !== null));
    }

    public function queueOrderStatus(Order $order): void
    {
        $status = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
        $eventName = self::ORDER_STATUS_EVENTS[$status] ?? null;
        if (! $eventName || ! $this->measurementConfiguration()) {
            return;
        }

        $order->loadMissing(['items', 'landingPage']);
        SendGoogleAnalyticsEvent::dispatch([
            'client_id' => $this->orderClientId($order),
            'user_id' => $order->user_id ? (string) $order->user_id : null,
            'timestamp_micros' => now()->getTimestamp() * 1000000,
            'events' => [[
                'name' => $eventName,
                'params' => [
                    ...$this->orderParameters($order),
                    'order_status' => $status,
                    'page_location' => $order->landingPage?->public_url ?: url('/'),
                    'engagement_time_msec' => 1,
                    'session_id' => $this->numericId('order-session:'.$order->order_number),
                ],
            ]],
        ]);
    }

    /** @param array<string, mixed> $payload */
    public function sendNow(array $payload): array
    {
        $configuration = $this->measurementConfiguration();
        if (! $configuration) {
            return ['skipped' => true];
        }

        $response = Http::acceptJson()->asJson()
            ->timeout(15)->retry(3, 500, throw: false)
            ->post('https://www.google-analytics.com/mp/collect?'.http_build_query([
                'measurement_id' => $configuration['measurement_id'],
                'api_secret' => $configuration['api_secret'],
            ]), $payload);

        if (! $response->successful()) {
            throw new RuntimeException("Google Analytics event delivery failed (HTTP {$response->status()}).");
        }

        return ['delivered' => true, 'status' => $response->status()];
    }

    /** @return array<string, mixed> */
    public function validateMeasurementProtocol(): array
    {
        $configuration = $this->measurementConfiguration(true);
        $payload = [
            'client_id' => 'tisilo.integration.test',
            'events' => [[
                'name' => 'tisilo_connection_test',
                'params' => ['engagement_time_msec' => 1, 'session_id' => (string) now()->getTimestamp()],
            ]],
        ];
        $response = Http::acceptJson()->asJson()->timeout(15)->retry(2, 250, throw: false)
            ->post('https://www.google-analytics.com/debug/mp/collect?'.http_build_query([
                'measurement_id' => $configuration['measurement_id'],
                'api_secret' => $configuration['api_secret'],
            ]), $payload);

        if (! $response->successful()) {
            throw new RuntimeException("Google Analytics validation failed (HTTP {$response->status()}).");
        }

        $messages = $response->json('validationMessages');
        if (is_array($messages) && $messages !== []) {
            $details = collect($messages)->pluck('description')->filter()->implode('; ');
            throw new RuntimeException('Google Analytics rejected the test event'.($details ? ': '.$details : '.'));
        }

        return ['valid' => true];
    }

    /** @return array{summary: array<string, float|int>, rows: array<int, array<string, mixed>>} */
    public function report(int $days = 28): array
    {
        $values = SiteSetting::valuesFor('google_analytics');
        $secrets = SiteSetting::secretsFor('google_analytics');
        $propertyId = preg_replace('/^properties\//', '', trim((string) ($values['property_id'] ?? ''))) ?? '';
        $serviceAccount = trim((string) ($secrets['service_account_json'] ?? ''));
        if (! preg_match('/^[0-9]{4,30}$/', $propertyId) || $serviceAccount === '') {
            throw new RuntimeException('Save the numeric GA4 Property ID and service-account JSON first.');
        }

        $client = Http::acceptJson()->asJson()
            ->withToken($this->tokens->accessToken($serviceAccount, ['https://www.googleapis.com/auth/analytics.readonly']))
            ->timeout(20)->retry(2, 250, throw: false);
        $metrics = [
            ['name' => 'activeUsers'],
            ['name' => 'sessions'],
            ['name' => 'eventCount'],
            ['name' => 'totalRevenue'],
        ];
        $endpoint = "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport";
        $startDate = (max(3, $days) - 1).'daysAgo';
        $summaryResponse = $client->post($endpoint, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => 'today']],
            'metrics' => $metrics,
            'limit' => '1',
        ]);
        $dailyResponse = $client->post($endpoint, [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => 'today']],
            'dimensions' => [['name' => 'date']],
            'metrics' => $metrics,
            'orderBys' => [['dimension' => ['dimensionName' => 'date']]],
            'limit' => '100',
        ]);

        foreach ([$summaryResponse, $dailyResponse] as $response) {
            if (! $response->successful()) {
                $message = trim((string) $response->json('error.message'));
                throw new RuntimeException('Google Analytics report sync failed'.($message ? ': '.$message : " (HTTP {$response->status()})"));
            }
        }

        $rows = collect($dailyResponse->json('rows') ?? [])->map(function (array $row): array {
            $metrics = $row['metricValues'] ?? [];

            return [
                'date' => (string) ($row['dimensionValues'][0]['value'] ?? ''),
                'active_users' => (int) ($metrics[0]['value'] ?? 0),
                'sessions' => (int) ($metrics[1]['value'] ?? 0),
                'event_count' => (int) ($metrics[2]['value'] ?? 0),
                'revenue' => round((float) ($metrics[3]['value'] ?? 0), 2),
            ];
        })->values();

        $summaryMetrics = $summaryResponse->json('rows.0.metricValues') ?? [];

        return [
            'summary' => [
                'active_users' => (int) ($summaryMetrics[0]['value'] ?? 0),
                'sessions' => (int) ($summaryMetrics[1]['value'] ?? 0),
                'event_count' => (int) ($summaryMetrics[2]['value'] ?? 0),
                'revenue' => round((float) ($summaryMetrics[3]['value'] ?? 0), 2),
            ],
            'rows' => $rows->all(),
        ];
    }

    /** @return array{measurement_id: string, api_secret: string}|null */
    private function measurementConfiguration(bool $required = false): ?array
    {
        $values = SiteSetting::valuesFor('google_analytics');
        $secrets = SiteSetting::secretsFor('google_analytics');
        $measurementId = trim((string) ($values['measurement_id'] ?? ''));
        $secret = trim((string) ($secrets['measurement_protocol_secret'] ?? ''));
        $enabled = filter_var($values['enabled'] ?? false, FILTER_VALIDATE_BOOL);

        if (! $enabled || ! preg_match('/^G-[A-Z0-9]{4,20}$/', $measurementId) || $secret === '') {
            if ($required) {
                throw new RuntimeException('Enable Google Analytics and save a valid Measurement ID and Measurement Protocol API Secret first.');
            }

            return null;
        }

        return ['measurement_id' => $measurementId, 'api_secret' => $secret];
    }

    /** @return array<string, mixed> */
    private function eventParameters(Request $request, VisitorEvent $event, ?Order $order): array
    {
        $metadata = $event->metadata ?? [];
        $parameters = [
            'page_location' => Str::limit($request->fullUrl(), 1000, ''),
            'page_referrer' => Str::limit((string) $request->headers->get('referer'), 1000, ''),
            'engagement_time_msec' => 1,
            'session_id' => $this->numericId((string) ($event->session_id ?: $event->visitor_id)),
            'currency' => $metadata['currency'] ?? 'BDT',
            'value' => $event->value !== null ? (float) $event->value : null,
            'transaction_id' => $metadata['invoice_id'] ?? null,
            'payment_type' => $metadata['payment_method'] ?? null,
            'source' => $event->traffic_source,
            'medium' => $event->traffic_medium,
            'campaign' => $event->traffic_campaign,
        ];

        if ($order) {
            $parameters = [...$parameters, ...$this->orderParameters($order)];
        } elseif ($event->product_id) {
            $parameters['items'] = [[
                'item_id' => (string) $event->product_id,
                'item_name' => (string) ($metadata['product_name'] ?? 'Product '.$event->product_id),
                'quantity' => (int) ($metadata['quantity'] ?? 1),
                'price' => $event->value !== null ? (float) $event->value : null,
            ]];
        }

        return array_filter($parameters, fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @return array<string, mixed> */
    private function orderParameters(Order $order): array
    {
        return [
            'transaction_id' => $order->order_number,
            'currency' => $order->currency ?: 'BDT',
            'value' => (float) $order->total_amount,
            'shipping' => (float) $order->shipping_amount,
            'tax' => (float) $order->tax_amount,
            'items' => $order->items->map(fn ($item): array => array_filter([
                'item_id' => (string) ($item->product_id ?: $item->sku ?: $item->product_name),
                'item_name' => $item->product_name,
                'item_variant' => $item->variation_name,
                'price' => (float) $item->unit_price,
                'quantity' => (int) $item->quantity,
            ], fn (mixed $value): bool => $value !== null && $value !== ''))->values()->all(),
        ];
    }

    private function orderClientId(Order $order): string
    {
        return $order->marketing_attribution['ga_client_id'] ?? 'order-'.$this->numericId($order->order_number);
    }

    private function numericId(string $value): string
    {
        return (string) sprintf('%u', crc32($value));
    }
}
