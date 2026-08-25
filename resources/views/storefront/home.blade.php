@extends('layouts.storefront')

@section('title', 'Tisilo — আপনার বিশ্বস্ত অনলাইন মার্কেটপ্লেস')

@section('content')
    <section class="overflow-hidden bg-slate-950">
        <div class="relative mx-auto grid min-h-[460px] max-w-7xl items-center gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[1.1fr_.9fr] lg:px-8 lg:py-20">
            <div class="absolute -left-20 top-16 size-64 rounded-full bg-orange-500/20 blur-3xl"></div>
            <div class="absolute -right-24 bottom-0 size-80 rounded-full bg-amber-400/15 blur-3xl"></div>

            <div class="relative z-10 max-w-2xl">
                <span class="inline-flex items-center gap-2 rounded-full border border-orange-400/30 bg-orange-500/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-orange-300">
                    <span class="size-2 rounded-full bg-orange-400"></span>
                    Welcome to Tisilo
                </span>
                <h1 class="mt-6 text-4xl font-black leading-tight text-white sm:text-5xl lg:text-6xl">
                    আপনার প্রয়োজনের সবকিছু,
                    <span class="text-orange-400">এক মার্কেটপ্লেসে</span>
                </h1>
                <p class="mt-6 max-w-xl text-base leading-7 text-slate-300 sm:text-lg">বিশ্বস্ত বিক্রেতা, প্রতিযোগিতামূলক দাম এবং সারাদেশে দ্রুত ডেলিভারি—নিরাপদ অনলাইন কেনাকাটার নতুন ঠিকানা।</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('store.products.index') }}" class="rounded-xl bg-orange-500 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-orange-500/25 transition hover:bg-orange-600">শপিং শুরু করুন</a>
                    <a href="#categories" class="rounded-xl border border-white/20 bg-white/5 px-6 py-3.5 text-sm font-bold text-white transition hover:bg-white/10">ক্যাটাগরি দেখুন</a>
                </div>
                <div class="mt-10 flex flex-wrap gap-x-7 gap-y-3 text-xs font-semibold text-slate-400">
                    <span class="flex items-center gap-2">✓ যাচাইকৃত বিক্রেতা</span>
                    <span class="flex items-center gap-2">✓ সহজ রিটার্ন</span>
                    <span class="flex items-center gap-2">✓ নিরাপদ পেমেন্ট</span>
                </div>
            </div>

            <div class="relative z-10 hidden lg:block">
                <div class="relative mx-auto aspect-square max-w-md rounded-[2.5rem] border border-white/10 bg-gradient-to-br from-white/10 to-white/5 p-7 shadow-2xl backdrop-blur">
                    <div class="absolute -left-7 top-14 rounded-2xl bg-white p-4 shadow-2xl">
                        <p class="text-xs font-semibold text-slate-500">আজকের সেরা ছাড়</p>
                        <p class="mt-1 text-2xl font-black text-orange-600">৫০% পর্যন্ত</p>
                    </div>
                    <div class="grid size-full place-items-center rounded-[2rem] bg-gradient-to-br from-orange-400 to-rose-500 p-10 text-center text-white">
                        @svg('heroicon-o-shopping-bag', 'mx-auto size-28 opacity-90')
                        <p class="mt-5 text-3xl font-black">TISILO DEALS</p>
                        <p class="mt-2 text-sm font-semibold text-orange-50">প্রতিদিন নতুন অফার</p>
                    </div>
                    <div class="absolute -bottom-5 right-5 rounded-2xl bg-slate-900 px-5 py-4 text-white shadow-2xl">
                        <p class="text-xs text-slate-400">Delivery</p>
                        <p class="mt-1 font-black">সারাদেশে</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="border-b border-slate-200 bg-white">
        <div class="mx-auto grid max-w-7xl grid-cols-2 gap-px bg-slate-200 sm:grid-cols-4">
            @foreach ([
                ['heroicon-o-truck', 'দ্রুত ডেলিভারি', 'সারাদেশে নির্ভরযোগ্য সেবা'],
                ['heroicon-o-shield-check', 'নিরাপদ পেমেন্ট', 'সুরক্ষিত লেনদেন'],
                ['heroicon-o-arrow-path', 'সহজ রিটার্ন', 'সহজ ও স্বচ্ছ প্রক্রিয়া'],
                ['heroicon-o-chat-bubble-left-right', '২৪/৭ সাপোর্ট', 'সবসময় আপনার পাশে'],
            ] as [$icon, $title, $description])
                <div class="flex items-center gap-3 bg-white px-4 py-6 sm:px-6">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-orange-50 text-orange-600">@svg($icon, 'size-6')</span>
                    <div>
                        <p class="text-sm font-bold text-slate-900">{{ $title }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $description }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section id="categories" class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-orange-600">Explore</p>
                <h2 class="mt-2 text-2xl font-black text-slate-950 sm:text-3xl">জনপ্রিয় ক্যাটাগরি</h2>
            </div>
                <a href="{{ route('store.products.index') }}" class="text-sm font-bold text-orange-600 hover:text-orange-700">সব দেখুন →</a>
        </div>

        <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-6">
            @forelse ($categories as $category)
                <a href="{{ route('store.products.index', ['category' => $category->slug]) }}" class="group rounded-2xl border border-slate-200 bg-white p-5 text-center transition hover:-translate-y-1 hover:border-orange-200 hover:shadow-lg">
                    <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-gradient-to-br from-orange-50 to-amber-100 text-xl font-black text-orange-600 transition group-hover:scale-105">{{ mb_strtoupper(mb_substr($category->name, 0, 1)) }}</span>
                    <p class="mt-4 text-sm font-bold text-slate-800 group-hover:text-orange-600">{{ $category->name }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $category->products_count }} পণ্য</p>
                </a>
            @empty
                @foreach (['ইলেকট্রনিক্স', 'ফ্যাশন', 'হোম', 'বিউটি', 'গ্রোসারি', 'স্পোর্টস'] as $category)
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 text-center">
                        <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-orange-50 text-xl font-black text-orange-600">{{ mb_substr($category, 0, 1) }}</span>
                        <p class="mt-4 text-sm font-bold text-slate-800">{{ $category }}</p>
                    </div>
                @endforeach
            @endforelse
        </div>
    </section>

    <section id="featured" class="bg-white py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-orange-600">Best picks</p>
                    <h2 class="mt-2 text-2xl font-black text-slate-950 sm:text-3xl">আপনার জন্য নির্বাচিত</h2>
                </div>
                @if ($search)
                    <p class="rounded-full bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-700">“{{ $search }}” এর ফলাফল</p>
                @endif
            </div>

            <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                @forelse ($products as $card)
                    @include('storefront.components.product-card', ['card' => $card])
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-16 text-center">
                        <p class="font-bold text-slate-700">কোনো পণ্য পাওয়া যায়নি</p>
                        <a href="{{ route('store.home') }}#featured" class="mt-3 inline-block text-sm font-bold text-orange-600">সব পণ্য দেখুন</a>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="overflow-hidden rounded-3xl bg-gradient-to-r from-orange-500 to-rose-500 px-6 py-10 text-white sm:px-10 lg:flex lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-bold text-orange-100">Tisilo Seller Network</p>
                <h2 class="mt-2 text-3xl font-black">আপনার ব্যবসা অনলাইনে নিয়ে আসুন</h2>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-orange-50">নিজস্ব স্টোর, inventory management এবং সারাদেশের ক্রেতাদের কাছে পৌঁছানোর সুযোগ।</p>
            </div>
            <a href="/admin" class="mt-6 inline-flex rounded-xl bg-white px-6 py-3.5 text-sm font-black text-orange-600 shadow-lg transition hover:bg-orange-50 lg:mt-0">Seller Center</a>
        </div>
    </section>
@endsection
