@extends('layouts.storefront')

@section('title', 'আমার উইশলিস্ট — Tisilo')

@section('content')
    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.18em] text-orange-600">Saved for later</p>
                <h1 class="mt-2 text-3xl font-black text-slate-950">আমার উইশলিস্ট</h1>
                <p class="mt-2 text-sm text-slate-500">পছন্দের পণ্যগুলো এখান থেকে সহজে দেখুন ও কিনুন।</p>
            </div>
            <a href="{{ route('store.products.index') }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 hover:border-orange-300 hover:text-orange-600">আরও পণ্য দেখুন</a>
        </div>

        @if (session('success'))
            <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('success') }}</div>
        @endif

        <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @forelse ($products as $card)
                @include('storefront.components.product-card', ['card' => $card])
            @empty
                <div class="col-span-full rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-20 text-center">
                    @svg('heroicon-o-heart', 'mx-auto size-14 text-slate-300')
                    <h2 class="mt-5 text-xl font-black text-slate-800">উইশলিস্ট এখনো খালি</h2>
                    <a href="{{ route('store.products.index') }}" class="mt-5 inline-flex rounded-xl bg-orange-500 px-6 py-3 text-sm font-black text-white">শপিং শুরু করুন</a>
                </div>
            @endforelse
        </div>
    </section>
@endsection
