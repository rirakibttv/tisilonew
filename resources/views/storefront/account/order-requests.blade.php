@extends('storefront.account.layout')

@php
    $isReturn = $type === 'return';
    $heading = $mode === 'all' ? ($isReturn ? 'All Return' : 'All Cancellation') : ($isReturn ? 'Return' : 'Cancel');
@endphp
@section('title', $heading.' — Tisilo')

@section('account-content')
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-5 sm:p-6">
            <p class="text-xs font-black uppercase tracking-[0.15em] text-violet-600">Return &amp; Cancellation</p>
            <h1 class="mt-1 text-2xl font-black text-slate-950">{{ $heading }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $mode === 'new' ? ($isReturn ? 'ডেলিভারি সম্পন্ন অর্ডারের জন্য রিটার্ন অনুরোধ করুন।' : 'প্রসেসিং শুরু হওয়ার আগে অর্ডার বাতিলের অনুরোধ করুন।') : 'আপনার জমা দেওয়া অনুরোধের সর্বশেষ অবস্থা দেখুন।' }}</p>
        </div>

        @if($mode === 'new')
            <div class="divide-y divide-slate-100">
                @forelse($eligibleOrders as $order)
                    <article class="p-5 sm:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div><p class="font-black text-slate-900">{{ $order->order_number }}</p><p class="mt-1 text-xs text-slate-500">{{ $order->items_count }}টি পণ্য · {{ $order->placed_at?->format('d M Y') }}</p></div>
                            <p class="text-lg font-black text-slate-950">৳{{ number_format((float) $order->total_amount, 0) }}</p>
                        </div>
                        <form method="POST" action="{{ route('store.account.requests.store', [$type, $order]) }}" class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)_auto] sm:items-end">
                            @csrf
                            <label class="text-xs font-bold text-slate-600">কারণ
                                <select name="reason" required class="mt-1.5 w-full rounded-xl border-slate-200 text-sm focus:border-violet-500 focus:ring-violet-500">
                                    <option value="">নির্বাচন করুন</option>
                                    @foreach($isReturn ? ['পণ্য ক্ষতিগ্রস্ত', 'ভুল পণ্য পেয়েছি', 'বর্ণনার সাথে মিল নেই', 'অন্য কারণ'] : ['ভুল করে অর্ডার করেছি', 'ঠিকানা পরিবর্তন', 'পেমেন্ট সমস্যা', 'অন্য কারণ'] as $reason)
                                        <option value="{{ $reason }}">{{ $reason }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs font-bold text-slate-600">বিস্তারিত
                                <input name="details" maxlength="2000" placeholder="প্রয়োজনীয় বিস্তারিত লিখুন" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm focus:border-violet-500 focus:ring-violet-500">
                            </label>
                            <button class="rounded-xl bg-violet-600 px-5 py-3 text-sm font-black text-white">Submit</button>
                        </form>
                    </article>
                @empty
                    <div class="py-16 text-center">@svg('heroicon-o-document-check', 'mx-auto size-12 text-slate-300')<p class="mt-4 font-black text-slate-800">যোগ্য কোনো অর্ডার পাওয়া যায়নি</p></div>
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
                            <p class="mt-2 text-xs text-slate-400">Submitted {{ $customerRequest->created_at->format('d M Y, h:i A') }}</p>
                        </div>
                        <span class="h-fit w-fit rounded-full bg-violet-50 px-3 py-1 text-xs font-black text-violet-700">{{ $customerRequest->statusLabel() }}</span>
                    </article>
                @empty
                    <div class="py-16 text-center">@svg('heroicon-o-inbox', 'mx-auto size-12 text-slate-300')<p class="mt-4 font-black text-slate-800">কোনো অনুরোধ পাওয়া যায়নি</p></div>
                @endforelse
            </div>
            @if($requests->hasPages())<div class="border-t border-slate-100 p-5">{{ $requests->links() }}</div>@endif
        @endif
    </div>
@endsection
