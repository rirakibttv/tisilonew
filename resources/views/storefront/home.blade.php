@extends('layouts.storefront')

@php
    $homePageTitle = ($generalSettings['site_name'] ?? 'Tisilo').' — আপনার প্রয়োজনের সবকিছু, এক সুপারমার্কেটে';
    $homeMetaDescription = \App\Support\SeoMetadata::description(
        $generalSettings['footer_about_text'] ?? null,
        $generalSettings['top_headline'] ?? null,
        'বিশ্বস্ত বিক্রেতা, মানসম্মত পণ্য, নিরাপদ পেমেন্ট এবং সারাদেশে দ্রুত ডেলিভারির আধুনিক অনলাইন মার্কেটপ্লেস।',
    );
@endphp
@section('title', $homePageTitle)
@section('meta_description', $homeMetaDescription)

@section('content')
<div class="bg-slate-50 pb-12">
    <!-- 1. Hero Slider & Vertical Category Menu Section -->
    <section class="storefront-shell">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[270px_minmax(0,1fr)] lg:items-stretch">
            <!-- Desktop Vertical Category Sidebar -->
            <div id="categories" class="relative z-30 hidden lg:block">
                <div class="overflow-visible rounded-b-2xl border-x border-b border-slate-200 bg-white shadow-sm">
                    <ul class="relative divide-y divide-slate-100 text-sm font-semibold text-slate-700">
                        @forelse($categories->take(11) as $category)
                            <li class="group/cat relative">
                                <a href="{{ $category->permalink }}" class="flex min-h-[45px] items-center justify-between px-4 py-2 transition hover:bg-purple-50 hover:text-purple-700">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @if($category->image)
                                            <span class="grid size-7 shrink-0 place-items-center overflow-hidden rounded-md p-0">
                                                <img src="{{ asset('storage/'.ltrim($category->image, '/')) }}" alt="{{ $category->name }}" class="block size-full scale-125 object-cover" loading="lazy">
                                            </span>
                                        @else
                                            <span class="grid size-6 shrink-0 place-items-center rounded-md bg-purple-100 text-xs font-black uppercase text-purple-700">{{ mb_substr($category->name, 0, 1) }}</span>
                                        @endif
                                        <span class="truncate">{{ $category->name }}</span>
                                    </div>
                                    @if($category->children && $category->children->count() > 0)
                                        @svg('heroicon-o-chevron-right', 'size-3.5 text-slate-400 group-hover/cat:text-purple-700 transition shrink-0')
                                    @endif
                                </a>

                                <!-- Flyout Multi-Level Submenu -->
                                @if($category->children && $category->children->count() > 0)
                                    <div class="invisible absolute left-full top-0 ml-1.5 hidden w-72 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl opacity-0 transition-all duration-200 group-hover/cat:visible group-hover/cat:block group-hover/cat:opacity-100 z-50">
                                        <p class="border-b border-slate-100 px-2 pb-2 text-xs font-black uppercase tracking-wider text-purple-700">{{ $category->name }}</p>
                                        <div class="mt-2 space-y-2 max-h-[360px] overflow-y-auto pr-1">
                                            @foreach($category->children as $subcat)
                                                <div class="rounded-lg p-2 hover:bg-purple-50/50 transition">
                                                    <a href="{{ $subcat->permalink }}" class="block text-sm font-bold text-slate-800 hover:text-purple-700">
                                                        {{ $subcat->name }}
                                                    </a>
                                                    @if($subcat->children && $subcat->children->count() > 0)
                                                        <div class="mt-1 flex flex-wrap gap-1.5 pl-2 border-l border-purple-200">
                                                            @foreach($subcat->children as $child)
                                                                <a href="{{ $child->permalink }}" class="text-xs text-slate-500 hover:text-purple-700 hover:underline">
                                                                    {{ $child->name }}
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </li>
                        @empty
                            <li class="px-4 py-3 text-center text-sm text-slate-400">কোনো ক্যাটাগরি নেই</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <!-- Main Hero Banner Carousel Slider -->
            <div
                class="group relative mt-4 overflow-hidden rounded-2xl shadow-sm {{ $sliders->isNotEmpty() ? 'bg-white' : 'min-h-[330px] bg-slate-900 sm:min-h-[400px] lg:min-h-[482px]' }}"
                id="hero-slider"
                @if($sliders->isNotEmpty()) data-responsive-banner-slider @endif
            >
                @if($sliders->isNotEmpty())
                    @foreach($sliders as $slider)
                        @if($slider->destination_href)
                            <a
                                href="{{ $slider->destination_href }}"
                                class="hero-slide w-full lg:absolute lg:inset-0 lg:h-full {{ $loop->first ? 'active block' : 'hidden' }}"
                                aria-label="{{ $slider->name }}"
                            >
                                <img
                                    src="{{ $slider->image_url }}"
                                    alt="{{ $slider->name }}"
                                    class="block h-auto w-full lg:h-full lg:object-fill 2xl:object-cover"
                                    loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                                    @if($loop->first) fetchpriority="high" @endif
                                >
                            </a>
                        @else
                            <div class="hero-slide w-full lg:absolute lg:inset-0 lg:h-full {{ $loop->first ? 'active block' : 'hidden' }}">
                                <img
                                    src="{{ $slider->image_url }}"
                                    alt="{{ $slider->name }}"
                                    class="block h-auto w-full lg:h-full lg:object-fill 2xl:object-cover"
                                    loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                                    @if($loop->first) fetchpriority="high" @endif
                                >
                            </div>
                        @endif
                    @endforeach
                @else
                <!-- Slide 1 -->
                <div class="hero-slide active absolute inset-0 flex items-center bg-gradient-to-br from-purple-950 via-purple-800 to-indigo-800 p-7 text-white transition-opacity duration-700 ease-in-out sm:p-12">
                    <div class="z-10 max-w-2xl">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-purple-300/30 bg-fuchsia-500/25 px-3 py-1 text-xs font-black uppercase tracking-wider text-purple-100">
                            ⭐ Biggest Online Supermarket
                        </span>
                        <h1 class="mt-5 max-w-[720px] text-4xl font-black leading-[1.15] sm:text-5xl lg:text-6xl">
                            আপনার প্রয়োজনের সবকিছু, এক সুপারমার্কেটে
                        </h1>
                        <p class="mt-4 max-w-xl text-sm leading-relaxed text-purple-100/90 sm:text-base">
                            বিশেষ ছাড়, ক্যাশ অন ডেলিভারি এবং সারাদেশে দ্রুত ডেলিভারি সুবিধা উপভোগ করুন।
                        </p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="{{ route('store.products.index') }}" class="rounded-xl bg-fuchsia-600 px-7 py-3.5 text-xs font-black text-white shadow-lg shadow-fuchsia-800/30 transition hover:bg-fuchsia-500 sm:text-sm">
                                এখনই কিনুন
                            </a>
                            <a href="#hot-deal" class="rounded-xl border border-white/25 bg-white/10 px-6 py-3.5 text-xs font-bold text-white transition hover:bg-white/20 sm:text-sm">
                                আজকের ডিল
                            </a>
                        </div>
                    </div>
                    <div class="absolute -right-16 -bottom-16 size-80 rounded-full bg-purple-500/20 blur-3xl pointer-events-none"></div>
                </div>

                <!-- Slide 2 -->
                <div class="hero-slide opacity-0 pointer-events-none absolute inset-0 transition-opacity duration-700 ease-in-out bg-gradient-to-r from-indigo-950 via-purple-900 to-rose-900 flex items-center p-6 sm:p-12 text-white">
                    <div class="max-w-xl z-10">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-500/30 px-3 py-1 text-xs font-black uppercase tracking-wider text-rose-200 border border-rose-400/30">
                            🔥 Hot Offer - 25% Discount
                        </span>
                        <h2 class="mt-4 text-3xl font-black leading-tight sm:text-5xl">
                            প্রিমিয়াম কোয়ালিটি কালেকশন
                        </h2>
                        <p class="mt-3 text-sm sm:text-base text-rose-100/90 leading-relaxed">
                            সেরা মানের বেডশীট, ফ্যাশন ও হোম অ্যাপ্লায়েন্স অবিশ্বাস্য মূল্যে।
                        </p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <a href="{{ route('store.products.index') }}" class="rounded-xl bg-rose-600 px-6 py-3 text-xs sm:text-sm font-black text-white shadow-lg shadow-rose-600/30 transition hover:bg-rose-700">
                                অফার দেখুন
                            </a>
                        </div>
                    </div>
                    <div class="absolute -right-16 -bottom-16 size-80 rounded-full bg-rose-500/20 blur-3xl pointer-events-none"></div>
                </div>
                @endif

                <!-- Slider Arrows -->
                @if($sliders->isEmpty() || $sliders->count() > 1)
                    <button type="button" class="absolute left-3 top-1/2 grid size-12 -translate-y-1/2 place-items-center rounded-full bg-purple-950/65 text-white transition hover:bg-purple-950" id="hero-prev" aria-label="Previous Slide">
                        @svg('heroicon-o-chevron-left', 'size-5')
                    </button>
                    <button type="button" class="absolute right-3 top-1/2 grid size-12 -translate-y-1/2 place-items-center rounded-full bg-purple-950/65 text-white transition hover:bg-purple-950" id="hero-next" aria-label="Next Slide">
                        @svg('heroicon-o-chevron-right', 'size-5')
                    </button>
                @endif

                <!-- Slider Indicators -->
                @if($sliders->isEmpty() || $sliders->count() > 1)
                    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-2 z-20" id="hero-indicators">
                        @foreach($sliders->isEmpty() ? collect([1, 2]) : $sliders as $indicator)
                            <button
                                type="button"
                                class="size-2.5 rounded-full transition-all {{ $loop->first ? 'w-6 bg-white' : 'bg-white/50' }}"
                                data-slide-index="{{ $loop->index }}"
                                aria-label="Slide {{ $loop->iteration }}"
                            ></button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>

    <!-- Marketplace Service Highlights -->
    <section class="storefront-shell pt-2">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
            @foreach ([
                ['heroicon-o-truck', 'দ্রুত ডেলিভারি', 'সারাদেশে বিশ্বস্ত কুরিয়ারে ডেলিভারি'],
                ['heroicon-o-shield-check', 'ক্যাশ অন ডেলিভারি', 'পণ্য দেখে টাকা পরিশোধের সুবিধা'],
                ['heroicon-o-arrow-path', 'সহজ রিটার্ন', 'ত্রুটিপূর্ণ পণ্যে দ্রুত এক্সচেঞ্জ'],
                ['heroicon-o-chat-bubble-left-right', '২৪/৭ গ্রাহক সেবা', 'হোয়াটসঅ্যাপ ও ফোনে সহায়তা'],
            ] as [$icon, $title, $description])
                <div class="flex items-center gap-3.5 rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-purple-50 text-purple-700">@svg($icon, 'size-6')</span>
                    <div>
                        <p class="text-sm font-bold text-slate-900 sm:text-base">{{ $title }}</p>
                        <p class="mt-0.5 line-clamp-1 text-xs text-slate-500 sm:text-sm">{{ $description }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    @foreach($additionalSliderGroups->get('after_services', collect()) as $sliderGroup)
        @include('storefront.partials.managed-slider', ['sliderGroup' => $sliderGroup])
    @endforeach

    @if($flashSale && $flashSaleProducts->isNotEmpty())
        <!-- Flash Sale Product Flow -->
        <section id="flash-sale" class="storefront-shell pt-2">
            <div class="overflow-hidden rounded-2xl border border-rose-200 bg-white shadow-xs">
                <div class="flex flex-wrap items-center justify-between gap-4 bg-gradient-to-r from-rose-600 via-orange-500 to-amber-400 px-4 py-4 text-white sm:px-6">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-xl bg-white/20 shadow-inner">
                            @svg('heroicon-o-bolt', 'size-6')
                        </span>
                        <div>
                            <p class="text-xs font-black uppercase tracking-[.18em] text-rose-50">Limited Time Offer</p>
                            <h2 class="text-xl font-black sm:text-2xl">Flash Sale</h2>
                        </div>
                    </div>

                    <div class="flex items-center gap-2" data-countdown-timer data-end-time="{{ $flashSale->ends_at->toIso8601String() }}">
                        <span class="hidden text-sm font-bold text-white/90 sm:inline">অফার শেষ হতে বাকি:</span>
                        <div class="flex items-center gap-1 text-sm font-black text-slate-900">
                            <span class="grid min-w-8 place-items-center rounded-lg bg-white px-2 py-1 shadow-xs" data-countdown-days>00</span>
                            <span class="font-bold text-white">:</span>
                            <span class="grid min-w-8 place-items-center rounded-lg bg-white px-2 py-1 shadow-xs" data-countdown-hours>00</span>
                            <span class="font-bold text-white">:</span>
                            <span class="grid min-w-8 place-items-center rounded-lg bg-white px-2 py-1 shadow-xs" data-countdown-minutes>00</span>
                            <span class="font-bold text-white">:</span>
                            <span class="grid min-w-8 place-items-center rounded-lg bg-slate-950 px-2 py-1 text-white shadow-xs" data-countdown-seconds>00</span>
                        </div>
                    </div>
                </div>

                <div data-product-grid class="storefront-product-grid grid-cols-2 p-[5px] sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                    @foreach($flashSaleProducts as $card)
                        @include('storefront.components.product-card', ['card' => $card])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <!-- 2. Bottom Ads Banner Area (Side-by-Side Promotional Banners) -->
    <section class="storefront-shell pt-2">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
            <a href="{{ route('store.products.index') }}" class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-r from-purple-800 to-indigo-900 p-6 sm:p-8 text-white shadow-xs transition hover:shadow-md">
                <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-black uppercase tracking-wider text-purple-100">Special Promo</span>
                <h3 class="mt-3 text-xl sm:text-2xl font-black">সুপার ডিসকাউন্ট ডিল</h3>
                <p class="mt-1 text-sm text-purple-200 sm:text-base">সারাদেশে ক্যাশ অন ডেলিভারি সুবিধা</p>
                <span class="mt-4 inline-flex items-center gap-1 text-sm font-black text-amber-300 group-hover:underline">শপ করুন →</span>
            </a>

            <a href="{{ route('store.products.index') }}" class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-r from-slate-900 to-slate-800 p-6 sm:p-8 text-white shadow-xs transition hover:shadow-md">
                <span class="rounded-full bg-amber-400/20 px-3 py-1 text-xs font-black uppercase tracking-wider text-amber-300">New Arrivals</span>
                <h3 class="mt-3 text-xl sm:text-2xl font-black">নতুন ট্রেন্ডি কালেকশন</h3>
                <p class="mt-1 text-sm text-slate-300 sm:text-base">শতভাগ কোয়ালিটি ও কালার গ্যারান্টি</p>
                <span class="mt-4 inline-flex items-center gap-1 text-sm font-black text-amber-300 group-hover:underline">সব কালেকশন →</span>
            </a>
        </div>
    </section>

    <!-- 3. Categories: single-row, right-to-left auto slider -->
    <section class="storefront-shell pt-2">
        <div class="border-b-2 border-purple-700 pb-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="grid size-6 place-items-center rounded-md bg-purple-700 text-white text-xs">@svg('heroicon-o-squares-2x2', 'size-3.5')</span>
                <h2 class="text-lg sm:text-xl font-black text-slate-900">Categories</h2>
            </div>
            <a href="{{ route('store.products.index') }}" class="text-sm font-bold text-purple-700 hover:text-purple-800">সব ক্যাটাগরি দেখুন →</a>
        </div>

        <div data-category-carousel class="storefront-category-carousel mt-6" aria-label="Product categories">
            <div class="storefront-category-track">
                @foreach([false, true] as $isDuplicate)
                    <div class="storefront-category-group" @if($isDuplicate) aria-hidden="true" @endif>
                        @foreach($categories as $cat)
                            <a
                                href="{{ $cat->permalink }}"
                                class="storefront-category-item group flex flex-col items-center rounded-2xl text-center transition hover:bg-white hover:shadow-sm"
                                @if($isDuplicate) tabindex="-1" @endif
                            >
                                <div class="relative grid size-16 place-items-center overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xs transition group-hover:border-purple-300 group-hover:shadow-md sm:size-20">
                                    @if($cat->image)
                                        <img src="{{ asset('storage/'.ltrim($cat->image, '/')) }}" alt="{{ $cat->name }}" class="size-full rounded-xl object-contain transition duration-300 group-hover:scale-105" loading="lazy">
                                    @else
                                        <span class="grid size-full place-items-center rounded-xl bg-purple-50 text-xl font-black text-purple-700">{{ mb_substr($cat->name, 0, 1) }}</span>
                                    @endif
                                </div>
                                <span class="mt-2 line-clamp-2 text-sm font-bold leading-snug text-slate-800 group-hover:text-purple-700">
                                    {{ $cat->name }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @foreach($additionalSliderGroups->get('after_categories', collect()) as $sliderGroup)
        @include('storefront.partials.managed-slider', ['sliderGroup' => $sliderGroup])
    @endforeach

    <!-- 4. Full Width Mid Promo Banner -->
    <section class="storefront-shell pt-2">
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-purple-700 via-indigo-700 to-purple-900 p-6 sm:p-10 text-white shadow-md flex flex-col sm:flex-row items-center justify-between gap-6">
            <div class="max-w-xl">
                <span class="rounded-full bg-amber-400 px-3 py-1 text-xs font-black uppercase tracking-wider text-slate-950">Special Deals</span>
                <h3 class="mt-3 text-2xl sm:text-3xl font-black">উৎসবের বিশেষ ডিসকাউন্ট অফার</h3>
                <p class="mt-1 text-sm text-purple-100">সেরা পণ্য সেরা মূল্যে আপনার হাতের মুঠোয়। স্টক সীমিত!</p>
            </div>
            <a href="{{ route('store.products.index') }}" class="shrink-0 rounded-xl bg-amber-400 px-6 py-3.5 text-sm font-black text-slate-950 shadow-md transition hover:bg-amber-300 sm:text-base">
                অর্ডার করতে ক্লিক করুন
            </a>
        </div>
    </section>

    @foreach($additionalSliderGroups->get('before_hot_deals', collect()) as $sliderGroup)
        @include('storefront.partials.managed-slider', ['sliderGroup' => $sliderGroup])
    @endforeach

    <!-- 5. Hot Deal Section with Live Countdown Timer -->
    <section id="hot-deal" class="storefront-shell pt-2">
        <div class="rounded-2xl border border-purple-200/80 bg-white p-[5px] shadow-xs">
            <div class="border-b border-slate-100 pb-[5px] flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <span class="grid size-7 place-items-center rounded-lg bg-rose-500 text-white">
                        @svg('heroicon-o-fire', 'size-4.5')
                    </span>
                    <h2 class="text-xl font-black text-slate-950">Hot Deal</h2>
                </div>

                <!-- Live Countdown Timer -->
                <div class="flex items-center gap-2" id="hot-deal-timer" data-end-time="{{ $hotDealEndDate }}">
                    <span class="hidden text-sm font-bold text-slate-500 sm:inline">অফার শেষ হতে বাকি:</span>
                    <div class="flex items-center gap-1 text-sm font-black text-white">
                        <span class="grid min-w-8 place-items-center rounded-lg bg-purple-700 px-2 py-1 shadow-xs" id="timer-days">00</span>
                        <span class="text-purple-700 font-bold">:</span>
                        <span class="grid min-w-8 place-items-center rounded-lg bg-purple-700 px-2 py-1 shadow-xs" id="timer-hours">00</span>
                        <span class="text-purple-700 font-bold">:</span>
                        <span class="grid min-w-8 place-items-center rounded-lg bg-purple-700 px-2 py-1 shadow-xs" id="timer-mins">00</span>
                        <span class="text-purple-700 font-bold">:</span>
                        <span class="grid min-w-8 place-items-center rounded-lg bg-rose-600 px-2 py-1 shadow-xs animate-pulse" id="timer-secs">00</span>
                    </div>
                </div>
            </div>

            <!-- Hot Deal Products Grid -->
            <div data-product-grid class="storefront-product-grid mt-[5px] grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                @forelse($hotDealProducts as $card)
                    @include('storefront.components.product-card', ['card' => $card])
                @empty
                    <div class="col-span-full py-12 text-center text-slate-400">
                        কোনো হট ডিল প্রোডাক্ট পাওয়া যায়নি।
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    @foreach($additionalSliderGroups->get('after_hot_deals', collect()) as $sliderGroup)
        @include('storefront.partials.managed-slider', ['sliderGroup' => $sliderGroup])
    @endforeach

    <!-- 6. Category-wise Product Sections -->
    @foreach($categorySections as $section)
        @php
            $cat = $section['category'];
            $prods = $section['products'];
        @endphp
        <section class="storefront-shell pt-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-[5px] shadow-xs">
                <div class="border-b border-slate-100 pb-[5px] flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="grid size-6 place-items-center rounded-md bg-purple-700 text-white text-xs">@svg('heroicon-o-shopping-bag', 'size-3.5')</span>
                        <h2 class="text-lg sm:text-xl font-black text-slate-900">{{ $cat->name }}</h2>
                    </div>
                    <a href="{{ $cat->permalink }}" class="inline-flex items-center gap-1 rounded-lg bg-purple-50 px-3 py-1.5 text-sm font-bold text-purple-700 transition hover:bg-purple-100">
                        View More →
                    </a>
                </div>

                <div data-product-grid class="storefront-product-grid mt-[5px] grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                    @foreach($prods as $card)
                        @include('storefront.components.product-card', ['card' => $card])
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach

    <!-- 7. Brands Showcase -->
    @if($brands->isNotEmpty())
        <section class="storefront-shell pt-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-black text-slate-900">Top Brands</h2>
                </div>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-4 sm:gap-8">
                    @foreach($brands as $brand)
                        <a href="{{ route('store.products.index', ['q' => $brand->name]) }}" class="group flex items-center justify-center rounded-xl border border-slate-100 bg-slate-50/50 px-5 py-3 transition hover:border-purple-200 hover:bg-purple-50/30 hover:shadow-xs">
                            @if($brand->logo)
                                <img src="{{ asset('storage/'.ltrim($brand->logo, '/')) }}" alt="{{ $brand->name }}" class="h-8 w-auto object-contain grayscale group-hover:grayscale-0 transition" loading="lazy">
                            @else
                                <span class="text-sm font-black text-slate-700 group-hover:text-purple-700">{{ $brand->name }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @foreach($additionalSliderGroups->get('before_footer', collect()) as $sliderGroup)
        @include('storefront.partials.managed-slider', ['sliderGroup' => $sliderGroup])
    @endforeach

</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Hero Slider Logic
        const slides = document.querySelectorAll('.hero-slide');
        const sliderContainer = document.getElementById('hero-slider');
        const usesResponsiveImages = sliderContainer?.hasAttribute('data-responsive-banner-slider') ?? false;
        const indicators = document.querySelectorAll('#hero-indicators button');
        const prevBtn = document.getElementById('hero-prev');
        const nextBtn = document.getElementById('hero-next');
        let currentSlide = 0;
        let slideInterval;

        function showSlide(index) {
            slides.forEach((slide, i) => {
                if (usesResponsiveImages) {
                    slide.classList.toggle('hidden', i !== index);
                    slide.classList.toggle('block', i === index);
                    slide.classList.toggle('active', i === index);
                    return;
                }

                if (i === index) {
                    slide.classList.remove('opacity-0', 'pointer-events-none');
                    slide.classList.add('active', 'opacity-100');
                } else {
                    slide.classList.remove('active', 'opacity-100');
                    slide.classList.add('opacity-0', 'pointer-events-none');
                }
            });

            indicators.forEach((ind, i) => {
                if (i === index) {
                    ind.classList.add('w-6', 'bg-white');
                    ind.classList.remove('bg-white/50');
                } else {
                    ind.classList.remove('w-6', 'bg-white');
                    ind.classList.add('bg-white/50');
                }
            });
            currentSlide = index;
        }

        function nextSlide() {
            let next = (currentSlide + 1) % slides.length;
            showSlide(next);
        }

        function prevSlide() {
            let prev = (currentSlide - 1 + slides.length) % slides.length;
            showSlide(prev);
        }

        if (slides.length > 1) {
            slideInterval = setInterval(nextSlide, 5000);

            if (nextBtn) nextBtn.addEventListener('click', () => {
                clearInterval(slideInterval);
                nextSlide();
                slideInterval = setInterval(nextSlide, 5000);
            });

            if (prevBtn) prevBtn.addEventListener('click', () => {
                clearInterval(slideInterval);
                prevSlide();
                slideInterval = setInterval(nextSlide, 5000);
            });

            indicators.forEach((ind, idx) => {
                ind.addEventListener('click', () => {
                    clearInterval(slideInterval);
                    showSlide(idx);
                    slideInterval = setInterval(nextSlide, 5000);
                });
            });
        }

        // Additional homepage sliders managed from their master slider panels.
        document.querySelectorAll('[data-managed-slider]').forEach((container) => {
            const managedSlides = Array.from(container.querySelectorAll('[data-managed-slider-slide]'));
            const managedIndicators = Array.from(container.querySelectorAll('[data-managed-slider-indicator]'));
            const managedPrev = container.querySelector('[data-managed-slider-prev]');
            const managedNext = container.querySelector('[data-managed-slider-next]');
            let managedIndex = 0;
            let managedInterval;

            const showManagedSlide = (index) => {
                managedSlides.forEach((slide, slideIndex) => {
                    slide.classList.toggle('hidden', slideIndex !== index);
                    slide.classList.toggle('block', slideIndex === index);
                });

                managedIndicators.forEach((indicator, indicatorIndex) => {
                    indicator.classList.toggle('w-6', indicatorIndex === index);
                    indicator.classList.toggle('bg-white', indicatorIndex === index);
                    indicator.classList.toggle('bg-white/50', indicatorIndex !== index);
                });

                managedIndex = index;
            };

            const restartManagedInterval = () => {
                if (managedSlides.length < 2) return;
                clearInterval(managedInterval);
                managedInterval = setInterval(() => {
                    showManagedSlide((managedIndex + 1) % managedSlides.length);
                }, 5000);
            };

            managedPrev?.addEventListener('click', () => {
                showManagedSlide((managedIndex - 1 + managedSlides.length) % managedSlides.length);
                restartManagedInterval();
            });

            managedNext?.addEventListener('click', () => {
                showManagedSlide((managedIndex + 1) % managedSlides.length);
                restartManagedInterval();
            });

            managedIndicators.forEach((indicator, index) => {
                indicator.addEventListener('click', () => {
                    showManagedSlide(index);
                    restartManagedInterval();
                });
            });

            restartManagedInterval();
        });

        // Live countdown timers
        document.querySelectorAll('[data-countdown-timer], #hot-deal-timer').forEach((timerContainer) => {
            const targetDate = new Date(timerContainer.getAttribute('data-end-time')).getTime();
            const isHotDealTimer = timerContainer.id === 'hot-deal-timer';
            const daysEl = timerContainer.querySelector('[data-countdown-days]') || (isHotDealTimer ? document.getElementById('timer-days') : null);
            const hoursEl = timerContainer.querySelector('[data-countdown-hours]') || (isHotDealTimer ? document.getElementById('timer-hours') : null);
            const minsEl = timerContainer.querySelector('[data-countdown-minutes]') || (isHotDealTimer ? document.getElementById('timer-mins') : null);
            const secsEl = timerContainer.querySelector('[data-countdown-seconds]') || (isHotDealTimer ? document.getElementById('timer-secs') : null);

            const updateTimer = () => {
                const now = new Date().getTime();
                const diff = targetDate - now;

                if (diff <= 0) {
                    if (daysEl) daysEl.innerText = '00';
                    if (hoursEl) hoursEl.innerText = '00';
                    if (minsEl) minsEl.innerText = '00';
                    if (secsEl) secsEl.innerText = '00';
                    return;
                }

                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const secs = Math.floor((diff % (1000 * 60)) / 1000);

                if (daysEl) daysEl.innerText = String(days).padStart(2, '0');
                if (hoursEl) hoursEl.innerText = String(hours).padStart(2, '0');
                if (minsEl) minsEl.innerText = String(mins).padStart(2, '0');
                if (secsEl) secsEl.innerText = String(secs).padStart(2, '0');
            };

            updateTimer();
            setInterval(updateTimer, 1000);
        });
    });
</script>
@endpush
@endsection
