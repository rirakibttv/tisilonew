<?php

namespace App\Services;

use App\Models\VisitorEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SeoOverviewReportService
{
    /** @return array<string, mixed> */
    public function report(int $period, string $platform = 'all'): array
    {
        $period = in_array($period, [1, 7, 30, 90], true) ? $period : 7;
        $platform = in_array($platform, ['all', 'facebook', 'google'], true) ? $platform : 'all';
        $from = now()->subDays($period - 1)->startOfDay();
        $to = now()->endOfDay();
        $base = VisitorEvent::query()->whereBetween('occurred_at', [$from, $to]);

        if ($platform !== 'all') {
            $base = $this->platformQuery($base, $platform);
        }

        $counts = (clone $base)->selectRaw('event_type, COUNT(*) as aggregate')
            ->groupBy('event_type')
            ->pluck('aggregate', 'event_type')
            ->map(fn ($value): int => (int) $value);
        $visitors = (clone $base)->distinct()->count('visitor_id');
        $purchases = $counts->get('purchase', 0);
        $metrics = [
            'events' => (clone $base)->count(),
            'visitors' => $visitors,
            'page_views' => $counts->get('page_view', 0),
            'product_views' => $counts->get('product_view', 0),
            'add_to_cart' => $counts->get('add_to_cart', 0),
            'checkouts' => $counts->get('initiate_checkout', 0),
            'purchases' => $purchases,
            'revenue' => (float) (clone $base)->where('event_type', 'purchase')->sum('value'),
            'conversion_rate' => $visitors > 0 ? round(($purchases / $visitors) * 100, 2) : 0,
        ];

        $dailyRaw = (clone $base)
            ->selectRaw("DATE(occurred_at) as event_date, COUNT(*) as events, COUNT(DISTINCT visitor_id) as visitors, SUM(CASE WHEN event_type = 'purchase' THEN 1 ELSE 0 END) as purchases, SUM(CASE WHEN event_type = 'purchase' THEN value ELSE 0 END) as revenue")
            ->groupBy(DB::raw('DATE(occurred_at)'))
            ->orderBy('event_date')
            ->get()
            ->keyBy('event_date');
        $daily = collect(range(0, $period - 1))->map(function (int $offset) use ($from, $dailyRaw): array {
            $date = $from->copy()->addDays($offset)->toDateString();
            $row = $dailyRaw->get($date);

            return [
                'date' => $date,
                'label' => $from->copy()->addDays($offset)->format('d M'),
                'events' => (int) ($row->events ?? 0),
                'visitors' => (int) ($row->visitors ?? 0),
                'purchases' => (int) ($row->purchases ?? 0),
                'revenue' => (float) ($row->revenue ?? 0),
            ];
        });

        $campaigns = (clone $base)
            ->selectRaw("COALESCE(NULLIF(traffic_campaign, ''), '(not set)') as campaign, COUNT(DISTINCT visitor_id) as visitors, SUM(CASE WHEN event_type = 'purchase' THEN 1 ELSE 0 END) as purchases, SUM(CASE WHEN event_type = 'purchase' THEN value ELSE 0 END) as revenue")
            ->groupBy('campaign')
            ->orderByDesc('visitors')
            ->limit(12)
            ->get();
        $mediums = (clone $base)
            ->selectRaw("COALESCE(NULLIF(traffic_medium, ''), 'direct / unknown') as medium, COUNT(DISTINCT visitor_id) as visitors")
            ->groupBy('medium')
            ->orderByDesc('visitors')
            ->limit(8)
            ->get();
        $topPages = (clone $base)->whereIn('event_type', ['page_view', 'product_view'])
            ->whereNotNull('path')
            ->selectRaw('path, COUNT(*) as views, COUNT(DISTINCT visitor_id) as visitors')
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(10)
            ->get();
        $devices = (clone $base)
            ->selectRaw("COALESCE(device_type, 'unknown') as device, COUNT(DISTINCT visitor_id) as visitors")
            ->groupBy('device')
            ->pluck('visitors', 'device')
            ->map(fn ($value): int => (int) $value);
        $recentEvents = (clone $base)->with('product:id,name')->latest('occurred_at')->limit(20)->get();
        $sourceSummaries = $this->sourceSummaries(VisitorEvent::query()->whereBetween('occurred_at', [$from, $to]));

        return compact(
            'period', 'from', 'to', 'platform', 'metrics', 'counts', 'daily',
            'campaigns', 'mediums', 'topPages', 'devices', 'recentEvents', 'sourceSummaries',
        );
    }

    /** @return array<string, array<string, float|int>> */
    public function sourceSummaries(Builder $base): array
    {
        $summaries = [];
        foreach (['facebook', 'google'] as $platform) {
            $query = $this->platformQuery(clone $base, $platform);
            $visitors = (clone $query)->distinct()->count('visitor_id');
            $purchases = (clone $query)->where('event_type', 'purchase')->count();
            $summaries[$platform] = [
                'visitors' => $visitors,
                'purchases' => $purchases,
                'revenue' => (float) (clone $query)->where('event_type', 'purchase')->sum('value'),
                'conversion_rate' => $visitors > 0 ? round(($purchases / $visitors) * 100, 2) : 0,
            ];
        }

        return $summaries;
    }

    public function platformQuery(Builder $query, string $platform): Builder
    {
        return $query->where(function (Builder $filter) use ($platform): void {
            if ($platform === 'facebook') {
                $filter->whereIn(DB::raw('LOWER(traffic_source)'), ['facebook', 'fb', 'instagram', 'meta', 'facebook_ads'])
                    ->orWhere('click_source', 'facebook')
                    ->orWhere('referrer_host', 'like', '%facebook.%')
                    ->orWhere('referrer_host', 'like', '%instagram.%')
                    ->orWhere('referrer_host', 'like', '%fb.com%');
            } else {
                $filter->whereIn(DB::raw('LOWER(traffic_source)'), ['google', 'google_ads', 'adwords'])
                    ->orWhere('click_source', 'google')
                    ->orWhere('referrer_host', 'like', '%google.%')
                    ->orWhere('referrer_host', 'like', '%googleadservices.%');
            }
        });
    }
}
