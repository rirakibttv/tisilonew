@extends('layouts.storefront')

@section('title', 'অর্ডার ট্র্যাকিং ও অ্যাকাউন্ট — Tisilo')

@section('content')
    <section class="bg-slate-950 py-14 text-white">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6">
            <p class="text-xs font-bold uppercase tracking-[.2em] text-orange-300">Customer account</p>
            <h1 class="mt-3 text-3xl font-black sm:text-4xl">আপনার অর্ডার ট্র্যাক করুন</h1>
            <p class="mx-auto mt-3 max-w-2xl text-sm leading-6 text-slate-300">নিরাপত্তার জন্য ইনভয়েস নম্বর এবং অর্ডারে ব্যবহৃত মোবাইল নম্বর দুটিই দিন।</p>
        </div>
    </section>

    <section class="mx-auto grid max-w-5xl gap-7 px-4 py-10 sm:px-6 lg:grid-cols-[.8fr_1.2fr] lg:px-8">
        <form method="GET" action="{{ route('store.account.index') }}" class="h-fit rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <label for="order-number" class="text-sm font-black text-slate-800">ইনভয়েস / অর্ডার নম্বর</label>
            <input id="order-number" name="order_number" value="{{ request('order_number') }}" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-orange-400" placeholder="TIS-XXXXXX-XXXXXX">
            <label for="phone" class="mt-5 block text-sm font-black text-slate-800">মোবাইল নম্বর</label>
            <input id="phone" name="phone" value="{{ request('phone') }}" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-orange-400" placeholder="01XXXXXXXXX">
            <button class="mt-5 h-12 w-full rounded-xl bg-orange-500 text-sm font-black text-white shadow-lg shadow-orange-500/20 hover:bg-orange-600">অর্ডার খুঁজুন</button>
        </form>

        <div>
            @if ($order)
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 pb-5">
                        <div><p class="text-xs font-bold text-slate-400">ORDER</p><h2 class="mt-1 text-xl font-black text-slate-950">{{ $order->order_number }}</h2></div>
                        <span class="rounded-full bg-orange-50 px-4 py-2 text-sm font-black text-orange-700">{{ str($order->status->value)->replace('_', ' ')->title() }}</span>
                    </div>
                    <div class="mt-5 grid gap-4 sm:grid-cols-3">
                        <div><p class="text-xs text-slate-400">ক্রেতা</p><p class="mt-1 text-sm font-bold">{{ $order->customer_name }}</p></div>
                        <div><p class="text-xs text-slate-400">মোট</p><p class="mt-1 text-sm font-black text-orange-600">৳{{ number_format((float) $order->total_amount, 2) }}</p></div>
                        <div><p class="text-xs text-slate-400">অর্ডারের তারিখ</p><p class="mt-1 text-sm font-bold">{{ $order->placed_at?->format('d M Y, h:i A') }}</p></div>
                    </div>
                    <div class="mt-6 space-y-3">
                        @foreach ($order->items as $item)
                            <div class="flex items-center justify-between gap-4 rounded-xl bg-slate-50 p-4 text-sm">
                                <div><p class="font-bold text-slate-800">{{ $item->product_name }}</p><p class="mt-1 text-xs text-slate-500">পরিমাণ: {{ $item->quantity }}</p></div>
                                <p class="font-black">৳{{ number_format((float) $item->total_amount, 2) }}</p>
                            </div>
                        @endforeach
                    </div>
                    @if ($order->tracking_number)
                        <p class="mt-5 rounded-xl bg-emerald-50 p-4 text-sm font-bold text-emerald-700">Tracking number: {{ $order->tracking_number }}</p>
                    @endif
                </div>
            @elseif ($searched)
                <div class="rounded-3xl border border-rose-200 bg-rose-50 px-6 py-14 text-center">
                    @svg('heroicon-o-magnifying-glass', 'mx-auto size-12 text-rose-300')
                    <h2 class="mt-4 text-lg font-black text-rose-800">কোনো অর্ডার পাওয়া যায়নি</h2>
                    <p class="mt-2 text-sm text-rose-600">ইনভয়েস ও মোবাইল নম্বর অর্ডারের তথ্যের সঙ্গে ঠিকমতো মিলিয়ে আবার চেষ্টা করুন।</p>
                </div>
            @else
                <div class="rounded-3xl border border-slate-200 bg-white px-6 py-14 text-center shadow-sm">
                    @svg('heroicon-o-truck', 'mx-auto size-14 text-orange-300')
                    <h2 class="mt-4 text-xl font-black text-slate-800">লাইভ অর্ডার স্ট্যাটাস</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Pending থেকে Delivered পর্যন্ত সর্বশেষ অবস্থা এবং কুরিয়ার ট্র্যাকিং নম্বর এখানে দেখা যাবে।</p>
                </div>
            @endif
        </div>
    </section>
@endsection
