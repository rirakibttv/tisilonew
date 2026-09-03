@extends('layouts.storefront')

@section('title', 'আমার অ্যাকাউন্ট — Tisilo')

@section('content')
    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        @if(session('status'))
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-700">{{ session('status') }}</div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[280px_1fr]">
            <aside class="h-fit rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <span class="grid size-14 place-items-center rounded-2xl bg-orange-50 text-xl font-black text-orange-600">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                <h1 class="mt-4 text-xl font-black text-slate-950">{{ auth()->user()->name }}</h1>
                <p class="mt-1 break-all text-sm text-slate-500">{{ auth()->user()->email }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ auth()->user()->phone }}</p>
                <div class="mt-6 grid gap-2">
                    <a href="{{ route('store.wishlist.index') }}" class="rounded-xl bg-orange-50 px-4 py-3 text-sm font-bold text-orange-700">আমার উইশলিস্ট</a>
                    <a href="{{ route('store.products.index') }}" class="rounded-xl px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50">কেনাকাটা চালিয়ে যান</a>
                    <form method="POST" action="{{ route('store.account.logout') }}">
                        @csrf
                        <button class="w-full rounded-xl px-4 py-3 text-left text-sm font-bold text-rose-600 hover:bg-rose-50">লগআউট</button>
                    </form>
                </div>
            </aside>

            <div>
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-orange-600">My account</p>
                        <h2 class="mt-2 text-3xl font-black text-slate-950">সাম্প্রতিক অর্ডার</h2>
                    </div>
                    <a href="{{ route('store.cart.index') }}" class="text-sm font-black text-orange-600">কার্ট দেখুন →</a>
                </div>

                <div class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    @forelse($orders as $order)
                        <div class="grid gap-3 border-b border-slate-100 p-5 last:border-0 sm:grid-cols-[1fr_auto_auto] sm:items-center">
                            <div>
                                <p class="font-black text-slate-900">{{ $order->order_number }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $order->items_count }}টি পণ্য · {{ $order->placed_at?->format('d M Y') }}</p>
                            </div>
                            <span class="w-fit rounded-full bg-orange-50 px-3 py-1 text-xs font-bold text-orange-700">{{ $order->status->label() }}</span>
                            <p class="font-black text-slate-950">৳{{ number_format((float) $order->total_amount, 0) }}</p>
                        </div>
                    @empty
                        <div class="px-6 py-16 text-center">
                            @svg('heroicon-o-shopping-bag', 'mx-auto size-12 text-slate-300')
                            <p class="mt-4 font-black text-slate-800">এখনো কোনো অর্ডার নেই</p>
                            <a href="{{ route('store.products.index') }}" class="mt-4 inline-flex rounded-xl bg-orange-500 px-5 py-3 text-sm font-bold text-white">পণ্য দেখুন</a>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection
