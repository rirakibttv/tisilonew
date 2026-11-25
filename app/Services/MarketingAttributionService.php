<?php

namespace App\Services;

use App\Models\VisitorEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MarketingAttributionService
{
    /** @return array<string, string>|null */
    public function capture(Request $request): ?array
    {
        $decoded = json_decode(urldecode((string) $request->cookie('tisilo_attr')), true);
        $values = is_array($decoded) ? $decoded : [];

        foreach (['source', 'medium', 'campaign', 'content', 'term'] as $key) {
            $queryValue = $request->query('utm_'.$key);
            if (is_scalar($queryValue) && trim((string) $queryValue) !== '') {
                $values[$key] = trim((string) $queryValue);
            }
        }

        if (filled($request->query('fbclid'))) {
            $values['source'] = $values['source'] ?? 'facebook';
            $values['medium'] = $values['medium'] ?? 'paid_social';
            $values['click_source'] = 'facebook';
        } elseif (filled($request->query('gclid'))) {
            $values['source'] = $values['source'] ?? 'google';
            $values['medium'] = $values['medium'] ?? 'cpc';
            $values['click_source'] = 'google';
        }

        $visitorId = trim((string) $request->cookie('tisilo_vid'));
        if (! Str::isUuid($visitorId)) {
            $visitorId = '';
        }

        if (blank($values['source'] ?? null) && blank($values['click_source'] ?? null) && $visitorId !== '') {
            $lastTrackedSource = VisitorEvent::query()
                ->where('visitor_id', $visitorId)
                ->where(function ($query): void {
                    $query->whereNotNull('traffic_source')->orWhereNotNull('click_source');
                })
                ->latest('occurred_at')
                ->first(['traffic_source', 'traffic_medium', 'traffic_campaign', 'traffic_content', 'traffic_term', 'click_source']);

            if ($lastTrackedSource) {
                $values = array_merge($values, array_filter([
                    'source' => $lastTrackedSource->traffic_source,
                    'medium' => $lastTrackedSource->traffic_medium,
                    'campaign' => $lastTrackedSource->traffic_campaign,
                    'content' => $lastTrackedSource->traffic_content,
                    'term' => $lastTrackedSource->traffic_term,
                    'click_source' => $lastTrackedSource->click_source,
                ], fn (mixed $value): bool => filled($value)));
            }
        }

        if (blank($values['source'] ?? null) && blank($values['click_source'] ?? null)) {
            $host = strtolower((string) parse_url((string) $request->headers->get('referer'), PHP_URL_HOST));
            if (str_contains($host, 'facebook.') || str_contains($host, 'instagram.') || str_contains($host, 'fb.com')) {
                $values['source'] = 'facebook';
                $values['medium'] = 'referral';
                $values['click_source'] = 'facebook';
            } elseif (str_contains($host, 'google.') || str_contains($host, 'googleadservices.')) {
                $values['source'] = 'google';
                $values['medium'] = 'organic';
                $values['click_source'] = 'google';
            }
        }

        $safe = [];
        foreach (['source' => 100, 'medium' => 100, 'campaign' => 191, 'content' => 191, 'term' => 191] as $key => $limit) {
            if (isset($values[$key]) && is_scalar($values[$key])) {
                $value = trim(strip_tags((string) $values[$key]));
                if ($value !== '') {
                    $safe[$key] = Str::limit($key === 'source' || $key === 'medium' ? strtolower($value) : $value, $limit, '');
                }
            }
        }

        if (in_array($values['click_source'] ?? null, ['facebook', 'google'], true)) {
            $safe['click_source'] = $values['click_source'];
        }

        foreach (['fbp' => '_fbp', 'fbc' => '_fbc'] as $key => $cookie) {
            if (filled($request->cookie($cookie))) {
                $safe[$key] = Str::limit(strip_tags((string) $request->cookie($cookie)), 255, '');
            }
        }
        if ($visitorId !== '') {
            $safe['visitor_id'] = $visitorId;
        }
        if (filled($request->cookie('tisilo_ga_cid'))) {
            $safe['ga_client_id'] = Str::limit(strip_tags((string) $request->cookie('tisilo_ga_cid')), 255, '');
        }

        return $safe ?: null;
    }
}
