@php
    $cartItems = session('store_cart', []);
    $cartCount = collect($cartItems)->sum('quantity');
    $cartSubtotal = collect($cartItems)->sum(fn ($line) => ($line['price'] ?? 0) * ($line['quantity'] ?? 1));
    $hotline = $contactSettings['phone'] ?? $contactSettings['hotline'] ?? '01794313455';
    $primaryColor = $generalSettings['primary_color'] ?? '#7b12af';
@endphp

<!-- Top notification & contact bar -->
<div class="border-b border-slate-800 bg-slate-950 text-slate-300 text-xs">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-2 sm:px-6 lg:px-8">
        <div class="flex items-center gap-4">
            <a href="tel:{{ $hotline }}" class="flex items-center gap-1.5 font-medium text-slate-300 hover:text-white transition">
                @svg('heroicon-o-phone', 'size-3.5 text-orange-400')
                <span>{{ $hotline }}</span>
            </a>
            <span class="hidden sm:inline text-slate-600">|</span>
            <p class="hidden sm:inline text-slate-400">{{ $generalSettings['top_headline'] ?? 'Biggest Online Shopping Zone with Million Of Products at Special Discounts' }}</p>
        </div>
        <div class="flex items-center gap-4 text-slate-400 font-medium">
            <a href="{{ auth()->check() ? route('store.account.dashboard') : route('store.account.login') }}" class="flex items-center gap-1 hover:text-white transition">
                @svg('heroicon-o-truck', 'size-3.5')
                <span>Track Order</span>
            </a>
            <span class="text-slate-700">|</span>
            <a href="/admin" class="hover:text-white transition">Seller Center</a>
        </div>
    </div>
</div>

<!-- Main Header -->
<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/98 shadow-xs backdrop-blur-md">
    <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3.5 sm:gap-6 sm:px-6 lg:px-8">
        <!-- Mobile Menu Trigger -->
        <button type="button" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-700 md:hidden hover:bg-slate-50 transition" data-mobile-menu-button aria-label="Open Navigation Menu">
            @svg('heroicon-o-bars-3', 'size-6')
        </button>

        <!-- Brand Logo -->
        <a href="{{ route('store.home') }}" class="shrink-0 flex items-center gap-2" aria-label="{{ $generalSettings['site_name'] ?? 'Tisilo' }}">
            @if(filled($generalSettings['dark_logo'] ?? null))
                @php($headerLogo = $generalSettings['dark_logo'])
                <img src="{{ asset('storage/'.ltrim($headerLogo, '/')) }}?v={{ substr(sha1($headerLogo), 0, 12) }}" alt="{{ $generalSettings['site_name'] ?? 'Tisilo' }}" class="h-9 sm:h-11 w-auto object-contain">
            @else
                <div class="flex items-center gap-2">
                    <span class="grid size-10 place-items-center rounded-xl bg-gradient-to-br from-purple-700 to-indigo-800 text-xl font-black text-white shadow-sm">T</span>
                    <div class="leading-none">
                        <span class="text-xl sm:text-2xl font-black tracking-tight text-slate-900">{{ strtoupper($generalSettings['site_name'] ?? 'Tisilo') }}</span>
                        <span class="block text-[9px] font-bold uppercase tracking-[0.25em] text-purple-700">Supermarket</span>
                    </div>
                </div>
            @endif
        </a>

        <!-- Desktop Search Bar -->
        <form action="{{ route('store.products.index') }}" method="GET" class="relative hidden flex-1 max-w-2xl mx-auto md:block">
            <label for="desktop-search" class="sr-only">Search Product</label>
            <div class="relative flex items-center">
                <input
                    id="desktop-search"
                    name="q"
                    value="{{ $search ?? request('q') }}"
                    type="search"
                    placeholder="Search Product..."
                    class="h-11 w-full rounded-full border-2 border-purple-600/80 bg-slate-50/70 pl-5 pr-14 text-sm font-medium outline-none transition focus:border-purple-700 focus:bg-white focus:ring-4 focus:ring-purple-100"
                >
                <button type="submit" class="absolute right-1 top-1 bottom-1 px-5 rounded-full bg-purple-700 text-white font-semibold transition hover:bg-purple-800 flex items-center justify-center" aria-label="Search">
                    @svg('heroicon-o-magnifying-glass', 'size-4')
                </button>
            </div>
        </form>

        <!-- Right Header Items -->
        <div class="ml-auto flex items-center gap-2 sm:gap-4">
            <!-- Track Order Button (Desktop) -->
            <a href="{{ auth()->check() ? route('store.account.dashboard') : route('store.account.login') }}" class="hidden lg:flex items-center gap-2 rounded-xl border border-slate-200 px-3.5 py-2 text-xs font-bold text-slate-700 hover:border-purple-300 hover:bg-purple-50/50 hover:text-purple-700 transition">
                @svg('heroicon-o-truck', 'size-4 text-purple-700')
                <span>Track Order</span>
            </a>

            <!-- Cart Dialog with Live Preview -->
            <div class="relative group" id="cart-qty">
                <a href="{{ route('store.cart.index') }}" class="relative flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 text-slate-700 transition hover:border-purple-300 hover:bg-purple-50/50 hover:text-purple-700" aria-label="কার্ট">
                    <span class="relative">
                        @svg('heroicon-o-shopping-bag', 'size-5 sm:size-6 text-purple-700')
                        @if($cartCount > 0)
                            <span class="absolute -right-2 -top-2 grid size-5 place-items-center rounded-full bg-purple-700 text-[10px] font-black text-white shadow-xs">{{ $cartCount }}</span>
                        @endif
                    </span>
                    <div class="hidden xl:block text-left text-xs leading-tight">
                        <span class="block text-[10px] uppercase font-semibold text-slate-400">আপনার কার্ট</span>
                        <span class="font-black text-slate-900">৳{{ number_format($cartSubtotal, 2) }}</span>
                    </div>
                </a>

                <!-- Hover Cart Popup -->
                <div class="invisible absolute right-0 top-full mt-2 w-72 rounded-2xl border border-slate-100 bg-white p-4 shadow-xl opacity-0 transition-all duration-200 group-hover:visible group-hover:opacity-100 z-50">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">অর্ডার সামারি</span>
                        <span class="text-xs font-bold text-purple-700">{{ $cartCount }} টি পণ্য</span>
                    </div>
                    <div class="py-4 text-center">
                        <p class="text-base font-black text-slate-900">সর্বমোট : ৳{{ number_format($cartSubtotal, 2) }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 pt-2">
                        <a href="{{ route('store.cart.index') }}" class="rounded-xl border border-slate-200 py-2.5 text-center text-xs font-bold text-slate-700 hover:bg-slate-50 transition">কার্ট দেখুন</a>
                        <a href="{{ route('store.checkout.index') }}" class="rounded-xl bg-purple-700 py-2.5 text-center text-xs font-bold text-white hover:bg-purple-800 shadow-sm transition">অর্ডার করুন</a>
                    </div>
                </div>
            </div>

            <!-- Login / Account Button -->
            <a href="{{ auth()->check() ? route('store.account.dashboard') : route('store.account.login') }}" class="hidden sm:flex items-center gap-1.5 rounded-xl border border-transparent px-3 py-2 text-xs font-bold text-slate-700 hover:border-slate-200 hover:bg-slate-50 hover:text-purple-700 transition" aria-label="Account">
                @svg('heroicon-o-user', 'size-5 text-slate-600')
                <span class="hidden lg:inline">{{ auth()->check() ? 'আমার অ্যাকাউন্ট' : 'Login / Sign Up' }}</span>
            </a>
        </div>
    </div>

    <!-- Mobile Search Bar -->
    <div class="border-t border-slate-100 px-4 py-2.5 md:hidden bg-slate-50/50">
        <form action="{{ route('store.products.index') }}" method="GET" class="relative">
            <input
                name="q"
                value="{{ $search ?? request('q') }}"
                type="search"
                placeholder="Search Product ... "
                class="h-10 w-full rounded-full border border-purple-400/60 bg-white pl-4 pr-12 text-sm outline-none focus:border-purple-700 focus:ring-2 focus:ring-purple-100"
            >
            <button type="submit" class="absolute right-1 top-1 bottom-1 px-3.5 rounded-full bg-purple-700 text-white flex items-center justify-center" aria-label="Search">
                @svg('heroicon-o-magnifying-glass', 'size-4')
            </button>
        </form>
    </div>
</header>

<!-- Supermarket Navigation Menu Bar -->
<nav class="hidden border-b border-slate-200 bg-white md:block shadow-xs" aria-label="Store navigation">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-6">
            <!-- ALL CATEGORIES Dropdown Trigger -->
            <div class="relative group">
                <button type="button" class="flex items-center gap-2.5 bg-purple-700 px-5 py-3.5 text-xs font-black uppercase tracking-wider text-white transition hover:bg-purple-800 rounded-t-xl" id="all-categories-button">
                    @svg('heroicon-o-bars-3', 'size-4')
                    <span>ALL CATEGORIES</span>
                    @svg('heroicon-o-chevron-down', 'size-3.5 ml-1')
                </button>
            </div>

            <!-- Horizontal Navigation Links -->
            <div class="flex items-center gap-6 text-sm font-bold text-slate-700">
                <a href="{{ route('store.home') }}" class="py-3.5 transition hover:text-purple-700 {{ request()->routeIs('store.home') ? 'text-purple-700 border-b-2 border-purple-700 font-extrabold' : '' }}">Home</a>
                <a href="{{ route('store.products.index') }}" class="py-3.5 transition hover:text-purple-700 {{ request()->routeIs('store.products.index') ? 'text-purple-700 border-b-2 border-purple-700 font-extrabold' : '' }}">Shop</a>
                <a href="/admin" class="py-3.5 transition hover:text-purple-700">Sellers</a>
                <a href="{{ route('store.pages.show', ['slug' => 'contact-us']) }}" class="py-3.5 transition hover:text-purple-700">Contact</a>
            </div>
        </div>

        <div class="flex items-center gap-4 text-xs font-bold text-slate-600">
            <a href="{{ auth()->check() ? route('store.account.dashboard') : route('store.account.login') }}" class="flex items-center gap-1.5 py-3.5 hover:text-purple-700 transition">
                @svg('heroicon-o-user', 'size-4 text-purple-700')
                <span>{{ auth()->check() ? 'আমার অ্যাকাউন্ট' : 'Login / Sign Up' }}</span>
            </a>
        </div>
    </div>
</nav>

<!-- Mobile Navigation & Category Drawer Backdrop -->
<div class="fixed inset-0 z-50 bg-black/60 opacity-0 pointer-events-none transition-opacity duration-300 backdrop-blur-xs" id="mobile-drawer-backdrop" data-drawer-close></div>

<!-- Mobile Navigation Offcanvas Drawer -->
<aside class="fixed inset-y-0 left-0 z-50 w-80 max-w-[85vw] bg-white shadow-2xl transform -translate-x-full transition-transform duration-300 ease-in-out flex flex-col" id="mobile-drawer">
    <div class="flex items-center justify-between border-b border-slate-100 bg-purple-700 px-5 py-4 text-white">
        <div class="flex items-center gap-2">
            <span class="grid size-8 place-items-center rounded-lg bg-white/15 text-lg font-black">T</span>
            <span class="font-black tracking-tight text-lg">{{ strtoupper($generalSettings['site_name'] ?? 'Tisilo') }}</span>
        </div>
        <button type="button" class="grid size-8 place-items-center rounded-full bg-white/10 hover:bg-white/20 transition" data-drawer-close aria-label="Close menu">
            @svg('heroicon-o-x-mark', 'size-5')
        </button>
    </div>

    <!-- Quick Tabs / Links -->
    <div class="flex border-b border-slate-100 bg-slate-50 text-xs font-bold text-slate-600">
        <a href="{{ route('store.home') }}" class="flex-1 py-3 text-center border-r border-slate-200 hover:text-purple-700">Home</a>
        <a href="{{ route('store.products.index') }}" class="flex-1 py-3 text-center border-r border-slate-200 hover:text-purple-700">Shop</a>
        <a href="/admin" class="flex-1 py-3 text-center hover:text-purple-700">Sellers</a>
    </div>

    <!-- Multi-level Categories in Drawer -->
    <div class="flex-1 overflow-y-auto p-4 space-y-1" id="mobile-drawer-categories">
        <p class="px-2 py-1.5 text-[11px] font-black uppercase tracking-wider text-purple-700">Categories</p>
        @foreach($categories ?? [] as $category)
            <div class="rounded-xl border border-slate-100 overflow-hidden bg-white" data-drawer-parent>
                <div class="flex items-center justify-between p-3 hover:bg-purple-50/50 transition">
                    <a href="{{ route('store.products.index', ['category' => $category->slug]) }}" class="flex items-center gap-2.5 text-xs font-bold text-slate-800 hover:text-purple-700">
                        @if($category->image)
                            <img src="{{ asset('storage/'.ltrim($category->image, '/')) }}" alt="{{ $category->name }}" class="size-6 rounded-md object-cover">
                        @else
                            <span class="grid size-6 place-items-center rounded-md bg-purple-100 text-xs font-black text-purple-700">{{ mb_substr($category->name, 0, 1) }}</span>
                        @endif
                        <span>{{ $category->name }}</span>
                    </a>
                    @if($category->children && $category->children->count() > 0)
                        <button type="button" class="p-1 text-slate-400 hover:text-purple-700" data-drawer-toggle aria-label="Toggle subcategories">
                            @svg('heroicon-o-chevron-down', 'size-4 transition-transform duration-200')
                        </button>
                    @endif
                </div>

                @if($category->children && $category->children->count() > 0)
                    <div class="hidden border-t border-slate-100 bg-slate-50/70 px-4 py-2 space-y-1.5 text-xs font-medium text-slate-600" data-drawer-children>
                        @foreach($category->children as $subcat)
                            <div>
                                <a href="{{ route('store.products.index', ['category' => $subcat->slug]) }}" class="block py-1 text-slate-700 hover:text-purple-700 font-semibold">
                                    • {{ $subcat->name }}
                                </a>
                                @if($subcat->children && $subcat->children->count() > 0)
                                    <div class="pl-4 py-1 space-y-1 border-l border-purple-200">
                                        @foreach($subcat->children as $child)
                                            <a href="{{ route('store.products.index', ['category' => $child->slug]) }}" class="block text-[11px] text-slate-500 hover:text-purple-700">
                                                - {{ $child->name }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <!-- Drawer Footer -->
    <div class="border-t border-slate-200 p-4 bg-slate-50">
        <a href="{{ auth()->check() ? route('store.account.dashboard') : route('store.account.login') }}" class="flex items-center justify-center gap-2 rounded-xl bg-purple-700 py-3 text-xs font-bold text-white shadow-sm hover:bg-purple-800 transition">
            @svg('heroicon-o-user', 'size-4')
            <span>{{ auth()->check() ? 'আমার প্রোফাইল' : 'লগইন / সাইন আপ' }}</span>
        </a>
    </div>
</aside>
