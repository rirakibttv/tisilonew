<div class="divide-y divide-slate-100">
    @forelse($orders as $order)
        <article class="grid gap-4 p-5 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center sm:px-6">
            <div class="min-w-0">
                <p class="truncate font-black text-slate-900">{{ $order->order_number }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ __(':count items', ['count' => $order->items_count]) }} · {{ $order->placed_at?->format('d M Y, h:i A') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-bold text-violet-700">{{ __($order->status->label()) }}</span>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ __($order->payment_status->label()) }}</span>
            </div>
            <p class="text-lg font-black text-slate-950">৳{{ number_format((float) $order->total_amount, 0) }}</p>
        </article>
    @empty
        <div class="px-6 py-16 text-center">
            @svg('heroicon-o-shopping-bag', 'mx-auto size-12 text-slate-300')
            <p class="mt-4 font-black text-slate-800">{{ __('No orders here yet') }}</p>
            <a href="{{ route('store.products.index') }}" class="mt-4 inline-flex rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white">{{ __('View Products') }}</a>
        </div>
    @endforelse
</div>
