@extends('layouts.storefront')

@php
    $catalogPageTitle = $selectedCategory
        ? $selectedCategory->name.' — '.($generalSettings['site_name'] ?? 'Tisilo')
        : 'Shop — '.($generalSettings['site_name'] ?? 'Tisilo');
    $catalogMetaDescription = \App\Support\SeoMetadata::description(
        $selectedCategory?->description,
        $selectedCategory ? $selectedCategory->name.' ক্যাটাগরির পণ্য দেখুন এবং নিরাপদে অর্ডার করুন।' : null,
        'ক্যাটাগরি, ব্র্যান্ড ও মূল্য অনুযায়ী পণ্য খুঁজুন এবং নিরাপদে অর্ডার করুন।',
    );
@endphp
@section('title', $catalogPageTitle)
@section('meta_description', $catalogMetaDescription)
@section('canonical', $selectedCategory?->permalink ?? url()->current())

@section('content')
    <section class="border-b border-purple-100 bg-gradient-to-r from-purple-950 via-purple-800 to-indigo-800 text-white">
        <div class="storefront-shell py-10">
            <nav class="text-xs font-semibold text-purple-200" aria-label="Breadcrumb">
                <a href="{{ route('store.home') }}" class="hover:text-white">Home</a>
                <span class="mx-2">/</span>
                <a href="{{ route('store.shop.index') }}" class="hover:text-white">Shop</a>
                @if ($selectedCategory)
                    <span class="mx-2">/</span>
                    <span class="text-white">{{ $selectedCategory->name }}</span>
                @endif
            </nav>
            <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black sm:text-4xl">{{ $selectedCategory?->name ?? 'Tisilo Shop' }}</h1>
                    <p class="mt-2 text-sm text-purple-100">{{ number_format($products->total()) }}টি পণ্য থেকে আপনার পছন্দের পণ্যটি খুঁজুন</p>
                </div>
                <span class="rounded-full border border-white/20 bg-white/10 px-4 py-2 text-xs font-bold">নিরাপদ কেনাকাটা · সারাদেশে ডেলিভারি</span>
            </div>
        </div>
    </section>

    <section class="storefront-shell py-10">
        <form method="GET" action="{{ route('store.shop.index') }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[1fr_220px_190px_auto]">
            <label class="sr-only" for="catalog-search">পণ্য খুঁজুন</label>
            <input id="catalog-search" type="search" name="q" value="{{ request('q') }}" placeholder="পণ্য, ব্র্যান্ড বা SKU খুঁজুন" class="h-12 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100">

            <label class="sr-only" for="category-filter">ক্যাটাগরি</label>
            <select id="category-filter" name="category" class="h-12 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-orange-400">
                <option value="">সব ক্যাটাগরি</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->hierarchicalPath() }}" @selected(($selectedCategory?->hierarchicalPath() ?? request('category')) === $category->hierarchicalPath())>{{ $category->hierarchicalName() }}</option>
                @endforeach
            </select>

            <label class="sr-only" for="sort-filter">সাজান</label>
            <select id="sort-filter" name="sort" class="h-12 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-orange-400">
                <option value="latest" @selected($sort === 'latest')>সর্বশেষ</option>
                <option value="price_low" @selected($sort === 'price_low')>দাম: কম থেকে বেশি</option>
                <option value="price_high" @selected($sort === 'price_high')>দাম: বেশি থেকে কম</option>
                <option value="name" @selected($sort === 'name')>নাম অনুযায়ী</option>
            </select>

            <button class="h-12 rounded-xl bg-purple-700 px-6 text-sm font-black text-white transition hover:bg-purple-800">খুঁজুন</button>
        </form>

        @if ($selectedCategory || request()->hasAny(['q', 'category']))
            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                <span class="text-slate-500">সক্রিয় ফিল্টার:</span>
                @if (request('q'))<span class="rounded-full bg-orange-50 px-3 py-1 font-semibold text-orange-700">“{{ request('q') }}”</span>@endif
                @if ($selectedCategory)<span class="rounded-full bg-orange-50 px-3 py-1 font-semibold text-orange-700">{{ $selectedCategory->name }}</span>@endif
                <a href="{{ route('store.shop.index') }}" class="font-bold text-rose-600">মুছুন</a>
            </div>
        @endif

        <div data-product-grid class="storefront-product-grid mt-8 grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @forelse ($products as $card)
                @include('storefront.components.product-card', ['card' => $card])
            @empty
                <div class="col-span-full rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-20 text-center">
                    @svg('heroicon-o-magnifying-glass', 'mx-auto size-12 text-slate-300')
                    <h2 class="mt-4 text-lg font-black text-slate-800">কোনো পণ্য পাওয়া যায়নি</h2>
                    <p class="mt-2 text-sm text-slate-500">অন্য শব্দ বা ক্যাটাগরি দিয়ে চেষ্টা করুন।</p>
                    <a href="{{ route('store.shop.index') }}" class="mt-5 inline-flex rounded-xl bg-purple-700 px-5 py-3 text-sm font-bold text-white">সব পণ্য দেখুন</a>
                </div>
            @endforelse
        </div>

        @if ($products->hasPages())
            <div class="mt-10">{{ $products->links() }}</div>
        @endif
    </section>
@endsection
