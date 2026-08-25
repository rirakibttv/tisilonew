@php
    $report = $this->report();
    $metrics = $report['metrics'];
    $platform = $report['platform'];
    $period = $report['period'];
    $daily = $report['daily'];
    $maxDaily = max(1, (int) $daily->max('events'));
    $platformLabels = ['all' => 'Visitor Analytics', 'facebook' => 'Facebook Overview', 'google' => 'Google Overview'];
    $platformColors = ['all' => 'indigo', 'facebook' => 'blue', 'google' => 'emerald'];
    $accent = $platformColors[$platform];
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-slate-950 via-indigo-950 to-indigo-800 p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-indigo-200">Tisilo intelligence</p>
                    <h2 class="mt-2 text-2xl font-black sm:text-3xl">{{ $platformLabels[$platform] }}</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-indigo-100">First-party traffic, UTM attribution, campaign performance and conversion reporting. Raw IP addresses are never stored.</p>
                </div>
                <div class="grid grid-cols-2 gap-3 text-center sm:grid-cols-4">
                    @foreach ([1 => 'Today', 7 => '7 Days', 30 => '30 Days', 90 => '90 Days'] as $days => $label)
                        <a href="{{ \App\Filament\Pages\SeoOverview::getUrl(['platform' => $platform, 'period' => $days]) }}"
                           class="rounded-xl px-4 py-2.5 text-xs font-bold transition {{ $period === $days ? 'bg-white text-indigo-950 shadow-lg' : 'bg-white/10 text-white hover:bg-white/20' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <nav class="grid gap-3 sm:grid-cols-3">
            @foreach ($platformLabels as $key => $label)
                <a href="{{ \App\Filament\Pages\SeoOverview::getUrl(['platform' => $key, 'period' => $period]) }}"
                   class="flex items-center justify-between rounded-2xl border px-5 py-4 text-sm font-bold shadow-sm transition {{ $platform === $key ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' : 'border-gray-200 bg-white text-gray-700 hover:border-primary-300 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200' }}">
                    <span>{{ $label }}</span>
                    @svg($key === 'facebook' ? 'heroicon-o-share' : ($key === 'google' ? 'heroicon-o-magnifying-glass' : 'heroicon-o-chart-bar-square'), 'size-5')
                </a>
            @endforeach
        </nav>

        @if ($platform === 'all')
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach (['facebook' => ['Facebook', 'bg-blue-500'], 'google' => ['Google', 'bg-emerald-500']] as $source => [$label, $color])
                    @php($summary = $report['sourceSummaries'][$source])
                    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-black text-gray-950 dark:text-white">{{ $label }} attributed traffic</p>
                                <p class="mt-1 text-xs text-gray-500">Current reporting period</p>
                            </div>
                            <span class="size-3 rounded-full {{ $color }}"></span>
                        </div>
                        <div class="mt-5 grid grid-cols-4 gap-3 text-center">
                            <div><p class="text-xl font-black text-gray-950 dark:text-white">{{ number_format($summary['visitors']) }}</p><p class="mt-1 text-xs text-gray-500">Visitors</p></div>
                            <div><p class="text-xl font-black text-gray-950 dark:text-white">{{ number_format($summary['purchases']) }}</p><p class="mt-1 text-xs text-gray-500">Purchases</p></div>
                            <div><p class="text-xl font-black text-gray-950 dark:text-white">৳{{ number_format($summary['revenue'], 0) }}</p><p class="mt-1 text-xs text-gray-500">Revenue</p></div>
                            <div><p class="text-xl font-black text-gray-950 dark:text-white">{{ number_format($summary['conversion_rate'], 2) }}%</p><p class="mt-1 text-xs text-gray-500">Conversion</p></div>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Unique Visitors', number_format($metrics['visitors']), 'heroicon-o-users', 'text-indigo-600 bg-indigo-50'],
                ['Page Views', number_format($metrics['page_views']), 'heroicon-o-eye', 'text-sky-600 bg-sky-50'],
                ['Product Views', number_format($metrics['product_views']), 'heroicon-o-shopping-bag', 'text-violet-600 bg-violet-50'],
                ['Add to Cart', number_format($metrics['add_to_cart']), 'heroicon-o-shopping-cart', 'text-orange-600 bg-orange-50'],
                ['Checkouts', number_format($metrics['checkouts']), 'heroicon-o-credit-card', 'text-amber-600 bg-amber-50'],
                ['Purchases', number_format($metrics['purchases']), 'heroicon-o-check-circle', 'text-emerald-600 bg-emerald-50'],
                ['Revenue', '৳'.number_format($metrics['revenue'], 2), 'heroicon-o-banknotes', 'text-teal-600 bg-teal-50'],
                ['Conversion', number_format($metrics['conversion_rate'], 2).'%', 'heroicon-o-arrow-trending-up', 'text-rose-600 bg-rose-50'],
            ] as [$label, $value, $icon, $color])
                <section class="flex items-start justify-between rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div><p class="text-xs font-bold uppercase tracking-wide text-gray-500">{{ $label }}</p><p class="mt-2 text-2xl font-black text-gray-950 dark:text-white">{{ $value }}</p></div>
                    <span class="grid size-11 place-items-center rounded-xl {{ $color }}">@svg($icon, 'size-6')</span>
                </section>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.6fr_.8fr]">
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-center justify-between"><div><h3 class="font-black text-gray-950 dark:text-white">Daily activity</h3><p class="mt-1 text-xs text-gray-500">Events and unique visitors</p></div><span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-bold text-primary-700">{{ number_format($metrics['events']) }} events</span></div>
                <div class="mt-8 flex h-56 items-end gap-2 overflow-x-auto border-b border-gray-100 pb-1 dark:border-white/10">
                    @foreach ($daily as $day)
                        <div class="flex min-w-9 flex-1 flex-col items-center justify-end" title="{{ $day['events'] }} events · {{ $day['visitors'] }} visitors">
                            <span class="mb-1 text-[10px] font-bold text-gray-500">{{ $day['events'] }}</span>
                            <div class="w-full max-w-9 rounded-t-lg bg-gradient-to-t from-indigo-600 to-violet-400" style="height: {{ max(4, round(($day['events'] / $maxDaily) * 160)) }}px"></div>
                            <span class="mt-2 whitespace-nowrap text-[10px] text-gray-500">{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <h3 class="font-black text-gray-950 dark:text-white">Conversion funnel</h3>
                <p class="mt-1 text-xs text-gray-500">From discovery to purchase</p>
                @php($funnelMax = max(1, $metrics['page_views'], $metrics['product_views']))
                <div class="mt-6 space-y-5">
                    @foreach (['Page views' => $metrics['page_views'], 'Product views' => $metrics['product_views'], 'Add to cart' => $metrics['add_to_cart'], 'Checkout' => $metrics['checkouts'], 'Purchase' => $metrics['purchases']] as $label => $value)
                        <div>
                            <div class="mb-1.5 flex justify-between text-xs"><span class="font-medium text-gray-600 dark:text-gray-300">{{ $label }}</span><strong class="text-gray-950 dark:text-white">{{ number_format($value) }}</strong></div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10"><span class="block h-full rounded-full bg-gradient-to-r from-indigo-600 to-violet-500" style="width: {{ min(100, round(($value / $funnelMax) * 100, 2)) }}%"></span></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-7 grid grid-cols-3 gap-2 border-t border-gray-100 pt-5 text-center dark:border-white/10">
                    @foreach (['desktop' => 'Desktop', 'mobile' => 'Mobile', 'tablet' => 'Tablet'] as $key => $label)
                        <div><p class="font-black text-gray-950 dark:text-white">{{ number_format($report['devices']->get($key, 0)) }}</p><p class="mt-1 text-[10px] text-gray-500">{{ $label }}</p></div>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-100 p-5 dark:border-white/10"><h3 class="font-black text-gray-950 dark:text-white">Campaign performance</h3><p class="mt-1 text-xs text-gray-500">UTM-attributed results</p></div>
                <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5"><tr><th class="px-5 py-3">Campaign</th><th class="px-4 py-3 text-right">Visitors</th><th class="px-4 py-3 text-right">Purchases</th><th class="px-5 py-3 text-right">Revenue</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($report['campaigns'] as $campaign)
                        <tr><td class="max-w-56 truncate px-5 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $campaign->campaign }}</td><td class="px-4 py-3 text-right">{{ number_format($campaign->visitors) }}</td><td class="px-4 py-3 text-right">{{ number_format($campaign->purchases) }}</td><td class="px-5 py-3 text-right font-bold">৳{{ number_format((float) $campaign->revenue, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-12 text-center text-sm text-gray-500">Campaign data will appear when UTM-tagged traffic arrives.</td></tr>
                    @endforelse
                </tbody></table></div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-100 p-5 dark:border-white/10"><h3 class="font-black text-gray-950 dark:text-white">Top landing pages</h3><p class="mt-1 text-xs text-gray-500">Pages that attract visitors</p></div>
                <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5"><tr><th class="px-5 py-3">Page</th><th class="px-4 py-3 text-right">Views</th><th class="px-5 py-3 text-right">Visitors</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($report['topPages'] as $page)
                        <tr><td class="max-w-80 truncate px-5 py-3 font-medium text-gray-800 dark:text-gray-100" title="{{ $page->path }}">{{ $page->path }}</td><td class="px-4 py-3 text-right">{{ number_format($page->views) }}</td><td class="px-5 py-3 text-right font-bold">{{ number_format($page->visitors) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-12 text-center text-sm text-gray-500">Visitor tracking data has not arrived yet.</td></tr>
                    @endforelse
                </tbody></table></div>
            </section>
        </div>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-100 p-5 dark:border-white/10"><h3 class="font-black text-gray-950 dark:text-white">Recent visitor events</h3><p class="mt-1 text-xs text-gray-500">Latest first-party activity for this report</p></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5"><tr><th class="px-5 py-3">Time</th><th class="px-4 py-3">Event</th><th class="px-4 py-3">Source</th><th class="px-4 py-3">Page / Product</th><th class="px-4 py-3">Device</th><th class="px-5 py-3 text-right">Value</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @forelse ($report['recentEvents'] as $event)
                    <tr><td class="whitespace-nowrap px-5 py-3 text-gray-500">{{ $event->occurred_at?->format('d M, h:i A') }}</td><td class="px-4 py-3"><span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700">{{ str($event->event_type)->replace('_', ' ')->title() }}</span></td><td class="px-4 py-3 font-medium">{{ $event->sourceLabel() }}</td><td class="max-w-72 truncate px-4 py-3" title="{{ $event->path }}">{{ $event->product?->name ?: $event->path ?: '—' }}</td><td class="whitespace-nowrap px-4 py-3 text-gray-500">{{ ucfirst($event->device_type ?: 'unknown') }} · {{ $event->browser ?: 'Other' }}</td><td class="px-5 py-3 text-right font-bold">{{ $event->value !== null ? '৳'.number_format((float) $event->value, 2) : '—' }}</td></tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-14 text-center text-sm text-gray-500">Waiting for the first visitor event.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
    </div>
</x-filament-panels::page>
