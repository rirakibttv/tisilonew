@extends('layouts.storefront')

@section('title', 'পণ্যসমূহ — Tisilo')

@section('content')
    <section class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-orange-600">Tisilo Marketplace</p>
            <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-slate-950 sm:text-4xl">সব পণ্য</h1>
                    <p class="mt-2 text-sm text-slate-500">{{ number_format($products->total()) }}টি পণ্য পাওয়া গেছে</p>
                </div>
                <a href="{{ route('store.home') }}" class="text-sm font-bold text-orange-600">← হোমপেজ</a>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <form method="GET" action="{{ route('store.products.index') }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[1fr_220px_190px_auto]">
            <label class="sr-only" for="catalog-search">পণ্য খুঁজুন</label>
            <input id="catalog-search" type="search" name="q" value="{{ request('q') }}" placeholder="পণ্য, ব্র্যান্ড বা SKU খুঁজুন" class="h-12 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-orange-400 focus:ring-4 focus:ring-orange-100">

            <label class="sr-only" for="category-filter">ক্যাটাগরি</label>
            <select id="category-filter" name="category" class="h-12 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-orange-400">
                <option value="">সব ক্যাটাগরি</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }}</option>
                @endforeach
            </select>

            <label class="sr-only" for="sort-filter">সাজান</label>
            <select id="sort-filter" name="sort" class="h-12 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-orange-400">
                <option value="latest" @selected($sort === 'latest')>সর্বশেষ</option>
                <option value="price_low" @selected($sort === 'price_low')>দাম: কম থেকে বেশি</option>
                <option value="price_high" @selected($sort === 'price_high')>দাম: বেশি থেকে কম</option>
                <option value="name" @selected($sort === 'name')>নাম অনুযায়ী</option>
            </select>

            <button class="h-12 rounded-xl bg-orange-500 px-6 text-sm font-black text-white transition hover:bg-orange-600">খুঁজুন</button>
        </form>

        @if (request()->hasAny(['q', 'category']))
            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                <span class="text-slate-500">সক্রিয় ফিল্টার:</span>
                @if (request('q'))<span class="rounded-full bg-orange-50 px-3 py-1 font-semibold text-orange-700">“{{ request('q') }}”</span>@endif
                @if (request('category'))<span class="rounded-full bg-orange-50 px-3 py-1 font-semibold text-orange-700">{{ $categories->firstWhere('slug', request('category'))?->name }}</span>@endif
                <a href="{{ route('store.products.index') }}" class="font-bold text-rose-600">মুছুন</a>
            </div>
        @endif

        <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @forelse ($products as $card)
                @include('storefront.components.product-card', ['card' => $card])
            @empty
                <div class="col-span-full rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-20 text-center">
                    @svg('heroicon-o-magnifying-glass', 'mx-auto size-12 text-slate-300')
                    <h2 class="mt-4 text-lg font-black text-slate-800">কোনো পণ্য পাওয়া যায়নি</h2>
                    <p class="mt-2 text-sm text-slate-500">অন্য শব্দ বা ক্যাটাগরি দিয়ে চেষ্টা করুন।</p>
                    <a href="{{ route('store.products.index') }}" class="mt-5 inline-flex rounded-xl bg-orange-500 px-5 py-3 text-sm font-bold text-white">সব পণ্য দেখুন</a>
                </div>
            @endforelse
        </div>

        @if ($products->hasPages())
            <div class="mt-10">{{ $products->links() }}</div>
        @endif
    </section>
@endsection
