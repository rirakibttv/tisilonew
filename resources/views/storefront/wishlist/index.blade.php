@extends('layouts.storefront')

@section('title', 'আমার উইশলিস্ট — Tisilo')

@section('content')
    <section class="storefront-shell py-10">
        @if(session('status'))
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-700">{{ session('status') }}</div>
        @endif
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-orange-600">Saved products</p>
                <h1 class="mt-2 text-3xl font-black text-slate-950">আমার উইশলিস্ট</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $products->count() }}টি পণ্য সংরক্ষিত আছে</p>
            </div>
            <a href="{{ route('store.products.index') }}" class="text-sm font-black text-orange-600">আরও পণ্য দেখুন →</a>
        </div>

        <div data-product-grid class="storefront-product-grid mt-8 grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @forelse($products as $card)
                @include('storefront.components.product-card', ['card' => $card, 'wishlistMode' => true])
            @empty
                <div class="col-span-full rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-20 text-center">
                    @svg('heroicon-o-heart', 'mx-auto size-12 text-slate-300')
                    <h2 class="mt-4 text-lg font-black text-slate-800">আপনার উইশলিস্ট খালি</h2>
                    <p class="mt-2 text-sm text-slate-500">পছন্দের পণ্যগুলো এখানে সংরক্ষণ করুন।</p>
                    <a href="{{ route('store.products.index') }}" class="mt-5 inline-flex rounded-xl bg-orange-500 px-5 py-3 text-sm font-bold text-white">পণ্য খুঁজুন</a>
                </div>
            @endforelse
        </div>
    </section>
@endsection
