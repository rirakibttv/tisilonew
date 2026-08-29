<div class="bg-slate-950 text-slate-300">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-2 text-xs sm:px-6 lg:px-8">
        <p>{{ $generalSettings['top_headline'] ?? 'সারাদেশে দ্রুত ডেলিভারি' }}</p>
        <div class="hidden items-center gap-5 sm:flex">
            <span>নিরাপদ পেমেন্ট</span>
            <span>সহায়তা: ২৪/৭</span>
            <a href="/admin" class="transition hover:text-white">Seller Center</a>
        </div>
    </div>
</div>

<header class="sticky top-0 z-40 border-b border-orange-100 bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-4 sm:px-6 lg:px-8">
        <a href="{{ route('store.home') }}" class="shrink-0" aria-label="{{ $generalSettings['site_name'] ?? 'Tisilo' }} homepage">
            @if(filled($generalSettings['dark_logo'] ?? null))
                @php($headerLogo = $generalSettings['dark_logo'])
                <img src="{{ asset('storage/'.ltrim($headerLogo, '/')) }}?v={{ substr(sha1($headerLogo), 0, 12) }}" alt="{{ $generalSettings['site_name'] ?? 'Tisilo' }}" class="h-10 w-auto" width="180" height="40">
            @else
            <span class="text-2xl font-black tracking-tight text-orange-600 sm:text-3xl">{{ strtoupper($generalSettings['site_name'] ?? 'Tisilo') }}</span>
            <span class="block text-[9px] font-semibold uppercase tracking-[0.24em] text-slate-500">Marketplace</span>
            @endif
        </a>

        <form action="{{ route('store.products.index') }}" method="GET" class="relative hidden flex-1 md:block">
            <label for="desktop-search" class="sr-only">পণ্য খুঁজুন</label>
            <input
                id="desktop-search"
                name="q"
                value="{{ $search ?? request('q') }}"
                type="search"
                placeholder="পণ্য, ব্র্যান্ড বা ক্যাটাগরি খুঁজুন..."
                class="h-12 w-full rounded-xl border border-slate-200 bg-slate-50 pl-5 pr-14 text-sm outline-none transition focus:border-orange-400 focus:bg-white focus:ring-4 focus:ring-orange-100"
            >
            <button type="submit" class="absolute right-1.5 top-1.5 grid size-9 place-items-center rounded-lg bg-orange-500 text-white transition hover:bg-orange-600" aria-label="Search">
                @svg('heroicon-o-magnifying-glass', 'size-5')
            </button>
        </form>

        <nav class="ml-auto flex items-center gap-1 sm:gap-2" aria-label="Customer actions">
            <a href="#" class="store-action-link">
                @svg('heroicon-o-user', 'size-5')
                <span class="hidden lg:inline">অ্যাকাউন্ট</span>
            </a>
            <a href="#" class="store-action-link hidden sm:flex">
                @svg('heroicon-o-heart', 'size-5')
                <span class="hidden lg:inline">উইশলিস্ট</span>
            </a>
            <a href="{{ route('store.cart.index') }}" class="store-action-link relative">
                @svg('heroicon-o-shopping-cart', 'size-5')
                <span class="hidden lg:inline">কার্ট</span>
                @php($cartCount = collect(session('store_cart', []))->sum('quantity'))
                <span class="absolute -right-1 -top-1 grid size-5 place-items-center rounded-full bg-orange-500 text-[10px] font-bold text-white">{{ $cartCount }}</span>
            </a>
            <button type="button" class="store-action-link md:hidden" data-mobile-menu-button aria-label="Menu" aria-expanded="false">
                @svg('heroicon-o-bars-3', 'size-6')
            </button>
        </nav>
    </div>

    <div class="border-t border-slate-100 md:hidden">
        <form action="{{ route('store.products.index') }}" method="GET" class="mx-auto max-w-7xl px-4 py-3">
            <label for="mobile-search" class="sr-only">পণ্য খুঁজুন</label>
            <div class="relative">
                <input id="mobile-search" name="q" value="{{ $search ?? request('q') }}" type="search" placeholder="পণ্য খুঁজুন..." class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 pl-4 pr-12 text-sm outline-none focus:border-orange-400">
                <button type="submit" class="absolute right-1 top-1 grid size-9 place-items-center rounded-lg bg-orange-500 text-white" aria-label="Search">
                    @svg('heroicon-o-magnifying-glass', 'size-5')
                </button>
            </div>
        </form>
    </div>

    <div class="hidden border-t border-slate-100 bg-white" data-mobile-menu>
        <nav class="mx-auto grid max-w-7xl gap-1 px-4 py-3 text-sm font-semibold text-slate-700">
            <a href="#categories" class="rounded-lg px-3 py-2 hover:bg-orange-50 hover:text-orange-600">ক্যাটাগরি</a>
            <a href="#featured" class="rounded-lg px-3 py-2 hover:bg-orange-50 hover:text-orange-600">আজকের অফার</a>
            <a href="#brands" class="rounded-lg px-3 py-2 hover:bg-orange-50 hover:text-orange-600">ব্র্যান্ড</a>
        </nav>
    </div>
</header>

<nav class="hidden border-b border-slate-200 bg-white md:block" aria-label="Main categories">
    <div class="mx-auto flex max-w-7xl items-center gap-7 overflow-x-auto px-6 py-3 text-sm font-semibold text-slate-700 lg:px-8">
        <a href="#categories" class="flex items-center gap-2 text-orange-600">
            @svg('heroicon-o-squares-2x2', 'size-5')
            সব ক্যাটাগরি
        </a>
        <a href="#featured" class="whitespace-nowrap hover:text-orange-600">আজকের ডিল</a>
        <a href="#featured" class="whitespace-nowrap hover:text-orange-600">ইলেকট্রনিক্স</a>
        <a href="#featured" class="whitespace-nowrap hover:text-orange-600">ফ্যাশন</a>
        <a href="#featured" class="whitespace-nowrap hover:text-orange-600">হোম ও লাইফস্টাইল</a>
        <a href="#featured" class="whitespace-nowrap hover:text-orange-600">বিউটি ও কেয়ার</a>
        <a href="#featured" class="ml-auto whitespace-nowrap rounded-full bg-orange-50 px-4 py-1.5 text-orange-700">Flash Sale</a>
    </div>
</nav>
