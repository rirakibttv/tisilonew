@php
    $configuration = $this->configuration();
    $riskLabels = [
        'very_safe' => ['খুব নিরাপদ', 'emerald'],
        'safe' => ['নিরাপদ', 'green'],
        'caution' => ['সতর্ক থাকুন', 'amber'],
        'high_risk' => ['উচ্চ ঝুঁকি', 'red'],
        'no_history' => ['কোনো ইতিহাস নেই', 'gray'],
    ];
    $statusLabels = [
        'success' => ['সম্পন্ন', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
        'blocked' => ['Provider blocked', 'bg-amber-50 text-amber-800 ring-amber-600/20'],
        'failed' => ['ব্যর্থ', 'bg-red-50 text-red-700 ring-red-600/20'],
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="bg-gradient-to-r from-indigo-950 via-indigo-900 to-violet-800 px-6 py-7 text-white sm:px-8">
                <div class="flex flex-col justify-between gap-5 md:flex-row md:items-center">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-indigo-200">Manual courier risk check</p>
                        <h2 class="mt-2 text-2xl font-black">মোবাইল নম্বর দিয়ে customer যাচাই করুন</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-indigo-100">সংযুক্ত courier provider থেকে delivery, return ও cancellation history এক জায়গায় দেখুন।</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs font-bold">
                        <span class="rounded-full bg-white/10 px-3 py-2">{{ $configuration['provider'] }}</span>
                        <span class="rounded-full px-3 py-2 {{ $configuration['enabled'] && $configuration['endpoint_ready'] && $configuration['api_key_ready'] ? 'bg-emerald-400/20 text-emerald-100' : 'bg-amber-400/20 text-amber-100' }}">
                            {{ $configuration['enabled'] && $configuration['endpoint_ready'] && $configuration['api_key_ready'] ? 'API প্রস্তুত' : 'Setup অসম্পূর্ণ' }}
                        </span>
                    </div>
                </div>
            </div>

            <form wire:submit="check" class="px-6 py-8 sm:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <label for="fraud-mobile" class="block text-base font-black text-gray-950 dark:text-white">আপনার যাচাই করতে চাওয়া মোবাইল নম্বরটি লিখুন</label>
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                        <input id="fraud-mobile" type="tel" inputmode="numeric" autocomplete="tel" wire:model="mobile"
                               placeholder="017XXXXXXXX" maxlength="30"
                               class="block min-w-0 flex-1 rounded-xl border-gray-300 bg-white text-center text-base shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-white" />
                        <button type="submit" wire:loading.attr="disabled" wire:target="check"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary-600 px-7 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-primary-500 disabled:cursor-wait disabled:opacity-60">
                            <span wire:loading.remove wire:target="check">@svg('heroicon-o-magnifying-glass', 'size-5') সার্চ দিন</span>
                            <span wire:loading.flex wire:target="check" class="items-center gap-2">@svg('heroicon-o-arrow-path', 'size-5 animate-spin') যাচাই হচ্ছে</span>
                        </button>
                    </div>
                    @error('mobile') <p class="mt-2 text-left text-sm font-semibold text-red-600">{{ $message }}</p> @enderror
                </div>
            </form>
        </section>

        @if ($errorMessage)
            <section class="rounded-2xl border p-5 {{ $errorType === 'blocked' ? 'border-amber-300 bg-amber-50 text-amber-950' : 'border-red-300 bg-red-50 text-red-950' }}">
                <div class="flex items-start gap-3">
                    @svg($errorType === 'blocked' ? 'heroicon-o-shield-exclamation' : 'heroicon-o-exclamation-triangle', 'mt-0.5 size-6 shrink-0')
                    <div>
                        <h3 class="font-black">{{ $errorType === 'blocked' ? 'Provider protection request বন্ধ করেছে' : 'Fraud check সম্পন্ন হয়নি' }}</h3>
                        <p class="mt-1 text-sm leading-6">{{ $errorMessage }}</p>
                        @if ($errorType === 'blocked')
                            <p class="mt-2 text-xs font-semibold">API provider-এর support-কে production hosting IP allowlist করতে বলুন। Browser protection bypass করার চেষ্টা করা হবে না।</p>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        @if ($result)
            @php
                $summary = $result['summary'];
                [$riskLabel, $riskTone] = $riskLabels[$summary['risk_level']] ?? ['পর্যালোচনা করুন', 'gray'];
                $riskClasses = [
                    'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                    'green' => 'bg-green-50 text-green-700 ring-green-600/20',
                    'amber' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
                    'red' => 'bg-red-50 text-red-700 ring-red-600/20',
                    'gray' => 'bg-gray-50 text-gray-700 ring-gray-600/20',
                ];
            @endphp
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex flex-col justify-between gap-4 border-b border-gray-100 p-6 sm:flex-row sm:items-center dark:border-white/10">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Check result · {{ $result['provider'] }}</p>
                        <h3 class="mt-1 text-xl font-black text-gray-950 dark:text-white">{{ $result['phone'] }}</h3>
                    </div>
                    <span class="inline-flex w-fit items-center rounded-full px-3 py-1.5 text-xs font-black ring-1 ring-inset {{ $riskClasses[$riskTone] }}">{{ $riskLabel }}</span>
                </div>

                <div class="grid gap-4 p-6 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ([
                        ['Success Rate', number_format((float) $summary['success_ratio'], 2).'%', 'heroicon-o-chart-pie', 'text-indigo-600 bg-indigo-50'],
                        ['Total Parcel', number_format($summary['total_parcel']), 'heroicon-o-cube', 'text-sky-600 bg-sky-50'],
                        ['Delivered', number_format($summary['success_parcel']), 'heroicon-o-check-circle', 'text-emerald-600 bg-emerald-50'],
                        ['Cancelled / Return', number_format($summary['cancelled_parcel']), 'heroicon-o-x-circle', 'text-red-600 bg-red-50'],
                    ] as [$label, $value, $icon, $tone])
                        <div class="flex items-start justify-between rounded-2xl border border-gray-100 p-5 dark:border-white/10">
                            <div><p class="text-xs font-bold uppercase tracking-wide text-gray-500">{{ $label }}</p><p class="mt-2 text-2xl font-black text-gray-950 dark:text-white">{{ $value }}</p></div>
                            <span class="grid size-11 place-items-center rounded-xl {{ $tone }}">@svg($icon, 'size-6')</span>
                        </div>
                    @endforeach
                </div>

                @if ($result['couriers'] !== [])
                    <div class="overflow-x-auto border-t border-gray-100 dark:border-white/10">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5"><tr><th class="px-6 py-3">Courier</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-right">Delivered</th><th class="px-4 py-3 text-right">Cancelled</th><th class="px-6 py-3 text-right">Success</th></tr></thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                @foreach ($result['couriers'] as $courier)
                                    <tr><td class="px-6 py-4 font-bold text-gray-950 dark:text-white">{{ $courier['name'] }}</td><td class="px-4 py-4 text-right">{{ number_format($courier['total_parcel']) }}</td><td class="px-4 py-4 text-right text-emerald-700">{{ number_format($courier['success_parcel']) }}</td><td class="px-4 py-4 text-right text-red-700">{{ number_format($courier['cancelled_parcel']) }}</td><td class="px-6 py-4 text-right font-black">{{ number_format((float) $courier['success_ratio'], 2) }}%</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-100 p-5 dark:border-white/10">
                <h3 class="font-black text-gray-950 dark:text-white">সাম্প্রতিক Check History</h3>
                <p class="mt-1 text-xs text-gray-500">নিরাপত্তার জন্য মোবাইল নম্বর masked দেখানো হয় এবং API secret কখনো history-তে রাখা হয় না।</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5"><tr><th class="px-5 py-3">Time</th><th class="px-4 py-3">Mobile</th><th class="px-4 py-3">Provider</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Parcel</th><th class="px-4 py-3 text-right">Success</th><th class="px-5 py-3">Checked by</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse ($history as $check)
                            @php([$historyStatus, $historyStatusClass] = $statusLabels[$check['status']] ?? [$check['status'], 'bg-gray-50 text-gray-700 ring-gray-600/20'])
                            <tr title="{{ $check['message'] }}">
                                <td class="whitespace-nowrap px-5 py-4 text-xs text-gray-500">{{ $check['checked_at'] }}</td>
                                <td class="whitespace-nowrap px-4 py-4 font-bold text-gray-950 dark:text-white">{{ $check['mobile'] }}</td>
                                <td class="px-4 py-4">{{ $check['provider'] }}</td>
                                <td class="px-4 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset {{ $historyStatusClass }}">{{ $historyStatus }}</span></td>
                                <td class="px-4 py-4 text-right">{{ number_format($check['total_parcel']) }}</td>
                                <td class="px-4 py-4 text-right font-black">{{ $check['success_ratio'] !== null ? number_format($check['success_ratio'], 2).'%' : '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-gray-600 dark:text-gray-300">{{ $check['checker'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-14 text-center text-sm text-gray-500">এখনো কোনো mobile check করা হয়নি।</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
