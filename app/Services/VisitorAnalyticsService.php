<?php

namespace App\Services;

use App\Models\Order;
use App\Models\VisitorEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Throwable;

class VisitorAnalyticsService
{
    public const EVENT_TYPES = [
        'page_view',
        'product_view',
        'add_to_cart',
        'initiate_checkout',
        'add_payment_info',
        'purchase',
    ];

    public function __construct(
        private MetaConversionsApiService $meta,
        private GoogleAnalyticsService $googleAnalytics,
        private BotTrafficService $botTraffic,
        private ClientIpFingerprintService $fingerprints,
    ) {}

    /** @param array<string, mixed> $data */
    public function record(Request $request, array $data): VisitorEvent
    {
        $visitorId = $this->visitorId($request, $data['visitor_id'] ?? null);
        $attribution = $this->attribution($request, $data['attribution'] ?? null, $data['referrer'] ?? null);
        $attributes = [
            'visitor_id' => $visitorId,
            'session_id' => $request->hasSession() && $request->session()->getId()
                ? hash('sha256', $request->session()->getId())
                : null,
            'customer_id' => auth()->id(),
            'event_type' => $data['event_type'],
            'event_key' => $this->eventKey($data['event_id'] ?? null),
            'path' => $this->cleanPath($data['path'] ?? $request->path()),
            'referrer_host' => $this->referrerHost($data['referrer'] ?? $request->headers->get('referer')),
            'traffic_source' => $attribution['source'] ?? null,
            'traffic_medium' => $attribution['medium'] ?? null,
            'traffic_campaign' => $attribution['campaign'] ?? null,
            'traffic_content' => $attribution['content'] ?? null,
            'traffic_term' => $attribution['term'] ?? null,
            'click_source' => $attribution['click_source'] ?? null,
            'product_id' => isset($data['product_id']) ? (int) $data['product_id'] : null,
            'order_id' => isset($data['order_id']) ? (int) $data['order_id'] : null,
            'value' => isset($data['value']) ? round((float) $data['value'], 2) : null,
            'device_type' => $this->deviceType((string) $request->userAgent()),
            'browser' => $this->browser((string) $request->userAgent()),
            'ip_hash' => $this->fingerprints->fingerprint($request),
            'metadata' => $this->safeMetadata($data['metadata'] ?? []),
            'occurred_at' => now(),
        ];

        $event = $attributes['event_key']
            ? VisitorEvent::updateOrCreate(['event_key' => $attributes['event_key']], $attributes)
            : VisitorEvent::create($attributes);

        try {
            $this->meta->queueVisitorEvent($request, $event);
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            $this->googleAnalytics->queueVisitorEvent($request, $event);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $event;
    }

    /** @return array<string, mixed> */
    public function purchaseEvent(Order $order): array
    {
        $order->loadMissing('items');
        $contents = $order->items->map(fn ($item): array => [
            'id' => (string) ($item->product_id ?: $item->sku ?: $item->product_name),
            'quantity' => max(1, (int) $item->quantity),
            'item_price' => round((float) $item->unit_price, 2),
        ])->values();

        return [
            'event_type' => 'purchase',
            'event_id' => 'purchase-'.$order->order_number,
            'order_id' => $order->getKey(),
            'value' => round((float) $order->total_amount, 2),
            'metadata' => [
                'currency' => strtoupper((string) ($order->currency ?: 'BDT')),
                'invoice_id' => $order->order_number,
                'payment_method' => $order->payment_method,
                'coupon_code' => $order->coupon_code,
                'content_ids' => $contents->pluck('id')->unique()->values()->all(),
                'contents' => $contents->all(),
                'num_items' => $contents->sum('quantity'),
            ],
        ];
    }

    public function recordBotRequest(Request $request): ?VisitorEvent
    {
        $bot = $this->botTraffic->identify($request->userAgent());
        if ($bot === null) {
            return null;
        }

        $path = $this->cleanPath($request->getRequestUri());
        $ipHash = $this->fingerprints->fingerprint($request);
        $fingerprint = hash_hmac('sha256', implode('|', [
            (string) $ipHash,
            strtolower((string) $request->userAgent()),
        ]), $this->hashKey());
        $visitorId = Uuid::uuid5(Uuid::NAMESPACE_URL, $fingerprint)->toString();
        $rateLimitKey = 'bot-traffic:'.hash('sha256', $fingerprint);
        if (RateLimiter::tooManyAttempts($rateLimitKey, 120)) {
            return null;
        }
        RateLimiter::hit($rateLimitKey, 60);

        return VisitorEvent::create([
            'visitor_id' => $visitorId,
            'session_id' => null,
            'customer_id' => null,
            'event_type' => 'bot_visit',
            'event_key' => null,
            'path' => $path,
            'referrer_host' => $this->referrerHost($request->headers->get('referer')),
            'traffic_source' => 'bot',
            'traffic_medium' => $bot['category'],
            'traffic_campaign' => $bot['name'],
            'traffic_content' => null,
            'traffic_term' => null,
            'click_source' => null,
            'product_id' => null,
            'order_id' => null,
            'value' => null,
            'device_type' => 'bot',
            'browser' => Str::limit($bot['name'], 40, ''),
            'ip_hash' => $ipHash,
            'metadata' => [
                'bot_name' => $bot['name'],
                'bot_category' => $bot['category'],
            ],
            'occurred_at' => now(),
        ]);
    }

    public function visitorId(Request $request, mixed $candidate = null): string
    {
        foreach ([$candidate, $request->cookie('tisilo_vid')] as $value) {
            $value = trim((string) $value);

            if ($value !== '' && Str::isUuid($value)) {
                return $value;
            }
        }

        return (string) Str::uuid();
    }

    public function isBot(?string $userAgent): bool
    {
        return $this->botTraffic->isAutomatedAnalyticsTraffic($userAgent);
    }

    protected function cleanPath(mixed $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        $parsed = parse_url($path, PHP_URL_PATH);

        return Str::limit('/'.ltrim((string) ($parsed ?: $path), '/'), 500, '');
    }

    protected function referrerHost(mixed $referrer): ?string
    {
        $host = strtolower((string) parse_url((string) $referrer, PHP_URL_HOST));

        return $host !== '' ? Str::limit($host, 255, '') : null;
    }

    /** @return array<string, string> */
    protected function attribution(Request $request, mixed $candidate, mixed $referrer): array
    {
        $values = is_array($candidate) ? $candidate : [];
        if ($values === []) {
            $decoded = json_decode(urldecode((string) $request->cookie('tisilo_attr')), true);
            $values = is_array($decoded) ? $decoded : [];
        }

        $source = $this->cleanAttribution($values['source'] ?? $request->query('utm_source'), 100, true);
        $medium = $this->cleanAttribution($values['medium'] ?? $request->query('utm_medium'), 100, true);
        $clickSource = $values['click_source'] ?? null;

        if ($request->query('fbclid')) {
            $clickSource = 'facebook';
        } elseif ($request->query('gclid')) {
            $clickSource = 'google';
        }

        $host = $this->referrerHost($referrer ?: $request->headers->get('referer'));
        if (! $source && ($clickSource === 'facebook' || $this->isFacebookHost($host))) {
            $source = 'facebook';
            $medium ??= $clickSource === 'facebook' ? 'paid_social' : 'referral';
        } elseif (! $source && ($clickSource === 'google' || $this->isGoogleHost($host))) {
            $source = 'google';
            $medium ??= $clickSource === 'google' ? 'cpc' : 'organic';
        }

        return array_filter([
            'source' => $source,
            'medium' => $medium,
            'campaign' => $this->cleanAttribution($values['campaign'] ?? $request->query('utm_campaign'), 191),
            'content' => $this->cleanAttribution($values['content'] ?? $request->query('utm_content'), 191),
            'term' => $this->cleanAttribution($values['term'] ?? $request->query('utm_term'), 191),
            'click_source' => in_array($clickSource, ['facebook', 'google'], true) ? $clickSource : null,
        ], fn ($value): bool => filled($value));
    }

    protected function cleanAttribution(mixed $value, int $limit, bool $lowercase = false): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : Str::limit($lowercase ? strtolower($value) : $value, $limit, '');
    }

    protected function isFacebookHost(?string $host): bool
    {
        return $host && (str_contains($host, 'facebook.') || str_contains($host, 'instagram.') || str_contains($host, 'fb.com'));
    }

    protected function isGoogleHost(?string $host): bool
    {
        return $host && (str_contains($host, 'google.') || str_contains($host, 'googleadservices.'));
    }

    protected function deviceType(string $userAgent): string
    {
        return match (true) {
            (bool) preg_match('/ipad|tablet/i', $userAgent) => 'tablet',
            (bool) preg_match('/mobile|android|iphone|ipod/i', $userAgent) => 'mobile',
            default => 'desktop',
        };
    }

    protected function browser(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Other',
        };
    }

    protected function eventKey(mixed $eventId): ?string
    {
        $eventId = trim((string) $eventId);

        return $eventId !== '' ? Str::limit($eventId, 100, '') : null;
    }

    /** @return array<string, mixed>|null */
    protected function safeMetadata(mixed $metadata): ?array
    {
        if (! is_array($metadata)) {
            return null;
        }

        $safe = [];
        foreach (['product_name', 'quantity', 'currency', 'invoice_id', 'payment_method', 'coupon_code'] as $key) {
            if (array_key_exists($key, $metadata) && is_scalar($metadata[$key])) {
                $safe[$key] = is_string($metadata[$key])
                    ? Str::limit(strip_tags($metadata[$key]), 200, '')
                    : $metadata[$key];
            }
        }

        if (isset($metadata['num_items']) && is_numeric($metadata['num_items'])) {
            $safe['num_items'] = max(1, min(999, (int) $metadata['num_items']));
        }

        if (is_array($metadata['content_ids'] ?? null)) {
            $safe['content_ids'] = collect($metadata['content_ids'])
                ->filter(fn (mixed $id): bool => is_scalar($id) && trim((string) $id) !== '')
                ->map(fn (mixed $id): string => Str::limit(trim((string) $id), 100, ''))
                ->take(100)
                ->values()
                ->all();
        }

        if (is_array($metadata['contents'] ?? null)) {
            $safe['contents'] = collect($metadata['contents'])
                ->filter(fn (mixed $item): bool => is_array($item) && filled($item['id'] ?? null))
                ->map(fn (array $item): array => array_filter([
                    'id' => Str::limit(trim((string) $item['id']), 100, ''),
                    'quantity' => max(1, min(99, (int) ($item['quantity'] ?? 1))),
                    'item_price' => isset($item['item_price']) && is_numeric($item['item_price'])
                        ? round((float) $item['item_price'], 2)
                        : null,
                ], fn (mixed $value): bool => $value !== null))
                ->take(100)
                ->values()
                ->all();
        }

        return $safe ?: null;
    }

    protected function hashKey(): string
    {
        return (string) (config('app.key') ?: 'tisilo-visitor-analytics');
    }
}
