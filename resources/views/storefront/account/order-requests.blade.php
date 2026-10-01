@extends('storefront.account.layout')

@php
    $isReturn = $type === 'return';
    $heading = $mode === 'all' ? ($isReturn ? 'All Return' : 'All Cancellation') : ($isReturn ? 'Return' : 'Cancel');
@endphp
@section('title', __($heading).' — Tisilo')

@section('account-content')
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-5 sm:p-6">
            <p class="text-xs font-black uppercase tracking-[0.15em] text-violet-600">{{ __('Return & Cancellation') }}</p>
            <h1 class="mt-1 text-2xl font-black text-slate-950">{{ __($heading) }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $mode === 'new' ? ($isReturn ? __('Request a return for a delivered order.') : __('Request cancellation before processing begins.')) : __('View the latest status of your submitted requests.') }}</p>
        </div>

        @if($mode === 'new')
            <div class="divide-y divide-slate-100">
                @forelse($eligibleOrders as $order)
                    <article class="p-5 sm:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div><p class="font-black text-slate-900">{{ $order->order_number }}</p><p class="mt-1 text-xs text-slate-500">{{ __(':count items', ['count' => $order->items_count]) }} · {{ $order->placed_at?->format('d M Y') }}</p></div>
                            <p class="text-lg font-black text-slate-950">৳{{ number_format((float) $order->total_amount, 0) }}</p>
                        </div>
                        <form method="POST" action="{{ route('store.account.requests.store', [$type, $order]) }}" class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)_auto] sm:items-end">
                            @csrf
                            <label class="text-xs font-bold text-slate-600">{{ __('Reason') }}
                                <select name="reason" required class="mt-1.5 w-full rounded-xl border-slate-200 text-sm focus:border-violet-500 focus:ring-violet-500">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach($isReturn ? ['Product damaged', 'Received wrong product', 'Not as described', 'Other reason'] : ['Ordered by mistake', 'Address change', 'Payment issue', 'Other reason'] as $reason)
                                        <option value="{{ $reason }}">{{ __($reason) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs font-bold text-slate-600">{{ __('Details') }}
                                <input name="details" maxlength="2000" placeholder="{{ __('Enter the required details') }}" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm focus:border-violet-500 focus:ring-violet-500">
                            </label>
                            <button class="rounded-xl bg-violet-600 px-5 py-3 text-sm font-black text-white">{{ __('Submit') }}</button>
                        </form>
                    </article>
                @empty
                    <div class="py-16 text-center">@svg('heroicon-o-document-check', 'mx-auto size-12 text-slate-300')<p class="mt-4 font-black text-slate-800">{{ __('No eligible orders found') }}</p></div>
                @endforelse
            </div>
            @if($eligibleOrders->hasPages())<div class="border-t border-slate-100 p-5">{{ $eligibleOrders->links() }}</div>@endif
        @else
            <div class="divide-y divide-slate-100">
                @forelse($requests as $customerRequest)
                    <article class="grid gap-3 p-5 sm:grid-cols-[1fr_auto] sm:p-6">
                        <div>
                            <p class="font-black text-slate-900">{{ $customerRequest->order?->order_number }}</p>
                            <p class="mt-1 text-sm font-bold text-slate-700">{{ $customerRequest->reason }}</p>
                            @if($customerRequest->details)<p class="mt-1 text-sm text-slate-500">{{ $customerRequest->details }}</p>@endif
                            <p class="mt-2 text-xs text-slate-400">{{ __('Submitted') }} {{ $customerRequest->created_at->format('d M Y, h:i A') }}</p>
                        </div>
                        <span class="h-fit w-fit rounded-full bg-violet-50 px-3 py-1 text-xs font-black text-violet-700">{{ __($customerRequest->statusLabel()) }}</span>
                    </article>
                @empty
                    <div class="py-16 text-center">@svg('heroicon-o-inbox', 'mx-auto size-12 text-slate-300')<p class="mt-4 font-black text-slate-800">{{ __('No requests found') }}</p></div>
                @endforelse
            </div>
            @if($requests->hasPages())<div class="border-t border-slate-100 p-5">{{ $requests->links() }}</div>@endif
        @endif
    </div>
@endsection
