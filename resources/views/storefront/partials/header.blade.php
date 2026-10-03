@php
    $cartItems = session('store_cart', []);
    $cartCount = collect($cartItems)->sum('quantity');
    $cartSubtotal = collect($cartItems)->sum(fn ($line) => ($line['price'] ?? 0) * ($line['quantity'] ?? 1));
    $hotline = $contactSettings['phone'] ?? $contactSettings['hotline'] ?? '01794313455';
    $whatsapp = $contactSettings['whatsapp'] ?? $hotline;
    $whatsappLink = 'https://wa.me/88' . ltrim(preg_replace('/[^0-9]/', '', $whatsapp), '88');
    $activeSocialLinks = collect($socialLinks ?? [])->where('status', true)->take(4);
    $menuCategories = $navigationCategories ?? collect();
@endphp

<!-- Top Info Bar -->
<div class="hidden border-b border-blue-500 bg-blue-600 py-1.5 text-sm text-white md:block">
    <div class="storefront-shell grid grid-cols-3 items-center gap-4">
        <div class="flex items-center gap-3">
            @forelse($activeSocialLinks as $social)
                <a href="{{ $social['link'] }}" target="_blank" rel="noopener" class="font-semibold transition hover:text-blue-100">{{ $social['title'] }}</a>
            @empty
                <span>Facebook</span><span>LinkedIn</span><span>Instagram</span><span>YouTube</span>
            @endforelse
        </div>
        <div class="flex items-center justify-center gap-3 text-center font-semibold">
            <span class="hidden xl:inline">{{ __($generalSettings['top_headline'] ?? 'Fast delivery nationwide') }}</span>
            <a href="tel:{{ $hotline }}" class="transition hover:text-blue-100">
                {{ __('Phone') }}: {{ $hotline }}
            </a>
        </div>
        <div class="flex items-center justify-end gap-3 font-semibold">
            <a href="{{ url('/seller') }}" class="transition hover:text-blue-100">{{ __('Seller Central') }}</a>
            <form action="{{ route('store.language.update') }}" method="POST" class="relative flex items-center" data-language-form>
                @csrf
                <label for="desktop-language" class="sr-only">{{ __('Language') }}</label>
                <span class="pointer-events-none absolute left-2.5 text-blue-100">@svg('heroicon-o-language', 'size-4')</span>
                <select
                    id="desktop-language"
                    name="locale"
                    class="h-7 cursor-pointer appearance-none rounded-lg border border-white/30 bg-white/10 py-0 pl-8 pr-7 text-xs font-bold text-white outline-none transition hover:bg-white/20 focus:border-white focus:ring-2 focus:ring-white/30"
                    data-language-select
                    aria-label="{{ __('Language') }}"
                >
                    <option value="en" class="text-slate-900" @selected(app()->isLocale('en'))>English</option>
                    <option value="bn" class="text-slate-900" @selected(app()->isLocale('bn'))>বাংলা</option>
                </select>
                <span class="pointer-events-none absolute right-2 text-blue-100">@svg('heroicon-o-chevron-down', 'size-3')</span>
                <noscript><button type="submit" class="ml-1 underline">OK</button></noscript>
            </form>
            <a href="{{ route('store.blog') }}" class="transition hover:text-blue-100">{{ __('Blog') }}</a>
            <a href="{{ route('store.about') }}" class="transition hover:text-blue-100">{{ __('About Us') }}</a>
        </div>
    </div>
</div>

<!-- Main Header -->
<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/98 shadow-xs backdrop-blur-md">
    <div class="storefront-shell flex min-h-[74px] items-center gap-3 lg:grid lg:grid-cols-[250px_minmax(320px,1fr)_auto] lg:gap-3">
        <!-- Mobile Menu Trigger -->
        <button type="button" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-700 md:hidden hover:bg-slate-50 transition" data-mobile-menu-button aria-label="{{ __('Open Navigation Menu') }}">
            @svg('heroicon-o-bars-3', 'size-6')
        </button>

        <!-- Brand Logo -->
        <a href="{{ route('store.home') }}" class="flex min-w-0 shrink-0 items-center lg:w-[250px]" aria-label="{{ $generalSettings['site_name'] ?? 'Tisilo' }}">
            @if(filled($generalSettings['dark_logo'] ?? null))
                @php
                    $headerLogo = $generalSettings['dark_logo'];
                @endphp
                <img src="{{ asset('storage/'.ltrim($headerLogo, '/')) }}?v={{ substr(sha1($headerLogo), 0, 12) }}" alt="{{ $generalSettings['site_name'] ?? 'Tisilo' }}" class="h-11 w-[190px] object-fill sm:h-[58px] sm:w-[245px]">
            @else
                <span class="font-serif text-4xl font-bold italic leading-none tracking-tight text-red-700 sm:text-6xl">{{ $generalSettings['site_name'] ?? 'Tisilo' }}</span>
            @endif
        </a>

        <!-- Desktop Search Bar -->
        <form action="{{ route('store.shop.index') }}" method="GET" class="relative hidden min-w-0 flex-1 md:block">
            <label for="desktop-search" class="sr-only">{{ __('Search Product') }}</label>
            <div class="relative flex items-center">
                <input
                    id="desktop-search"
                    name="q"
                    value="{{ $search ?? request('q') }}"
                    type="search"
                    placeholder="{{ __('Search Product...') }}"
                    class="h-11 w-full rounded-full border-2 border-purple-600 bg-white pl-5 pr-16 text-sm font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-purple-700 focus:ring-4 focus:ring-purple-100"
                >
                <button type="submit" class="absolute bottom-1 right-1 top-1 grid w-14 place-items-center rounded-full bg-purple-700 text-white transition hover:bg-purple-800" aria-label="{{ __('Search') }}">
                    @svg('heroicon-o-magnifying-glass', 'size-5')
                </button>
            </div>
        </form>

        <!-- Right Header Items -->
        <div class="ml-auto flex items-center gap-2 sm:gap-3">
            <a href="{{ $whatsappLink }}" target="_blank" rel="noopener" class="hidden items-center gap-2 px-2 text-sm font-bold leading-tight text-slate-700 transition hover:text-green-600 lg:flex">
                <span class="grid size-9 place-items-center rounded-full bg-green-50 text-green-600">
                    <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" data-whatsapp-brand-icon>
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479s1.065 2.875 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.262.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.981.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.002-5.45 4.436-9.893 9.891-9.893a9.82 9.82 0 0 1 7.021 2.91 9.82 9.82 0 0 1 2.898 7.027c-.003 5.45-4.435 9.893-9.895 9.893m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.3-1.652a11.86 11.86 0 0 0 5.69 1.449h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/>
                    </svg>
                </span>
                <span><span class="block text-xs text-slate-400">WhatsApp</span>{{ $whatsapp }}</span>
            </a>

            <!-- Cart Dialog with Live Preview -->
            <div class="relative group" id="cart-qty">
                <a href="{{ route('store.cart.index') }}" class="relative flex min-h-11 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50/80 px-3 text-slate-700 transition hover:border-purple-300 hover:bg-purple-50 hover:text-purple-700" aria-label="{{ __('Cart') }}">
                    <span class="relative">
                        @svg('heroicon-o-shopping-bag', 'size-6 text-purple-700')
                        @if($cartCount > 0)
                            <span class="absolute -right-2 -top-2 grid size-5 place-items-center rounded-full bg-purple-700 text-[10px] font-black text-white shadow-xs">{{ $cartCount }}</span>
                        @endif
                    </span>
                    <div class="hidden text-left text-sm leading-tight xl:block">
                        <span class="block text-xs font-semibold text-slate-400">{{ __('Your Cart') }}</span>
                        <span class="font-black text-slate-900">৳{{ number_format($cartSubtotal, 2) }}</span>
                    </div>
                </a>

                <!-- Hover Cart Popup -->
                <div class="invisible absolute right-0 top-full mt-2 w-72 rounded-2xl border border-slate-100 bg-white p-4 shadow-xl opacity-0 transition-all duration-200 group-hover:visible group-hover:opacity-100 z-50">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span class="text-sm font-bold uppercase tracking-wider text-slate-500">{{ __('Order Summary') }}</span>
                        <span class="text-sm font-bold text-purple-700">{{ __(':count items', ['count' => $cartCount]) }}</span>
                    </div>
                    <div class="py-4 text-center">
                        <p class="text-base font-black text-slate-900">{{ __('Total') }}: ৳{{ number_format($cartSubtotal, 2) }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 pt-2">
                        <a href="{{ route('store.cart.index') }}" class="rounded-xl border border-slate-200 py-2.5 text-center text-sm font-bold text-slate-700 transition hover:bg-slate-50">{{ __('View Cart') }}</a>
                        <a href="{{ route('store.checkout.index') }}" class="rounded-xl bg-purple-700 py-2.5 text-center text-sm font-bold text-white shadow-sm transition hover:bg-purple-800">{{ __('Order Now') }}</a>
                    </div>
                </div>
            </div>

            <!-- Login / Account Button -->
            <a href="{{ auth()->check() ? route('store.account.dashboard') : route('store.account.login') }}" class="hidden items-center gap-2 rounded-xl px-2 py-2 text-sm font-bold text-slate-700 transition hover:bg-purple-50 hover:text-purple-700 sm:flex" aria-label="{{ __('Account') }}">
                @svg('heroicon-o-user', 'size-5 text-slate-600')
                <span class="hidden whitespace-nowrap xl:inline">{{ __('My Account') }}</span>
            </a>
        </div>
    </div>

    <!-- Mobile Search Bar -->
    <div class="border-t border-slate-100 px-4 py-2.5 md:hidden bg-slate-50/50">
        <form action="{{ route('store.shop.index') }}" method="GET" class="relative">
            <input
                name="q"
                value="{{ $search ?? request('q') }}"
                type="search"
                placeholder="{{ __('Search Product...') }}"
                class="h-10 w-full rounded-full border border-purple-400/60 bg-white pl-4 pr-12 text-sm outline-none focus:border-purple-700 focus:ring-2 focus:ring-purple-100"
            >
            <button type="submit" class="absolute right-1 top-1 bottom-1 px-3.5 rounded-full bg-purple-700 text-white flex items-center justify-center" aria-label="{{ __('Search') }}">
                @svg('heroicon-o-magnifying-glass', 'size-4')
            </button>
        </form>
    </div>
</header>

<!-- Supermarket Navigation Menu Bar -->
<nav class="sticky top-[74px] z-30 hidden border-b border-slate-200 bg-white/98 shadow-sm backdrop-blur-md md:block" aria-label="{{ __('Store Navigation') }}" data-storefront-category-navigation>
    <div class="storefront-shell grid grid-cols-[270px_minmax(0,1fr)] gap-4">
        <div class="relative">
            <button
                type="button"
                class="flex h-12 w-full items-center gap-3 rounded-t-xl bg-gradient-to-r from-purple-800 to-fuchsia-600 px-5 text-left text-sm font-black uppercase tracking-wide text-white transition hover:from-purple-900 hover:to-fuchsia-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-purple-200"
                id="all-categories-button"
                data-desktop-category-menu-button
                aria-controls="desktop-category-menu"
                aria-expanded="false"
                aria-haspopup="true"
            >
                @svg('heroicon-o-bars-3', 'size-4')
                <span>{{ __('All Categories') }}</span>
                @svg('heroicon-o-chevron-down', 'ml-auto size-4 transition-transform duration-200', ['data-desktop-category-menu-chevron' => true])
            </button>

            <div
                id="desktop-category-menu"
                class="invisible pointer-events-none absolute left-0 top-full z-50 w-full translate-y-2 rounded-b-2xl border-x border-b border-slate-200 bg-white opacity-0 shadow-xl transition duration-200"
                data-desktop-category-menu-panel
                aria-hidden="true"
            >
                <ul class="relative divide-y divide-slate-100 text-sm font-semibold text-slate-700">
                    @forelse($menuCategories as $category)
                        <li class="group/desktop-cat relative">
                            <a href="{{ $category->permalink }}" class="flex min-h-[45px] items-center justify-between px-4 py-2 transition hover:bg-purple-50 hover:text-purple-700">
                                <span class="flex min-w-0 items-center gap-3">
                                    @if($category->image)
                                        <span class="grid size-7 shrink-0 place-items-center overflow-hidden rounded-md p-0">
                                            <img src="{{ asset('storage/'.ltrim($category->image, '/')) }}" alt="{{ $category->name }}" class="block size-full scale-125 object-cover" loading="lazy">
                                        </span>
                                    @else
                                        <span class="grid size-6 shrink-0 place-items-center rounded-md bg-purple-100 text-xs font-black uppercase text-purple-700">{{ mb_substr($category->name, 0, 1) }}</span>
                                    @endif
                                    <span class="truncate">{{ $category->name }}</span>
                                </span>
                                @if($category->children && $category->children->isNotEmpty())
                                    @svg('heroicon-o-chevron-right', 'size-3.5 shrink-0 text-slate-400 transition group-hover/desktop-cat:text-purple-700')
                                @endif
                            </a>

                            @if($category->children && $category->children->isNotEmpty())
                                <div class="invisible absolute left-full top-0 z-50 ml-1.5 hidden w-72 rounded-2xl border border-slate-200 bg-white p-3 opacity-0 shadow-xl transition-all duration-200 group-hover/desktop-cat:visible group-hover/desktop-cat:block group-hover/desktop-cat:opacity-100 group-focus-within/desktop-cat:visible group-focus-within/desktop-cat:block group-focus-within/desktop-cat:opacity-100">
                                    <p class="border-b border-slate-100 px-2 pb-2 text-xs font-black uppercase tracking-wider text-purple-700">{{ $category->name }}</p>
                                    <div class="mt-2 max-h-[360px] space-y-2 overflow-y-auto pr-1">
                                        @foreach($category->children as $subcat)
                                            <div class="rounded-lg p-2 transition hover:bg-purple-50/50">
                                                <a href="{{ $subcat->permalink }}" class="block text-sm font-bold text-slate-800 hover:text-purple-700">
                                                    {{ $subcat->name }}
                                                </a>
                                                @if($subcat->children && $subcat->children->isNotEmpty())
                                                    <div class="mt-1 flex flex-wrap gap-1.5 border-l border-purple-200 pl-2">
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
                        <li class="px-4 py-3 text-center text-sm text-slate-400">{{ __('No categories available') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="flex h-12 items-center justify-between">
            <div class="flex h-full items-center gap-7 text-sm font-bold text-slate-800">
                <a href="{{ route('store.home') }}" class="flex h-full items-center border-b-2 transition hover:text-purple-700 {{ request()->routeIs('store.home') ? 'border-purple-700 text-purple-700' : 'border-transparent' }}">{{ __('Home') }}</a>
                <a href="{{ route('store.shop.index') }}" class="flex h-full items-center border-b-2 transition hover:text-purple-700 {{ request()->routeIs('store.shop.index', 'store.products.index') ? 'border-purple-700 text-purple-700' : 'border-transparent' }}">{{ __('Shop') }}</a>
            </div>

            <a href="{{ route('store.contact') }}" class="flex h-full items-center border-b-2 transition hover:text-purple-700 {{ request()->routeIs('store.contact') ? 'border-purple-700 text-purple-700' : 'border-transparent' }}">
                {{ __('Contact') }}
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
        <button type="button" class="grid size-8 place-items-center rounded-full bg-white/10 hover:bg-white/20 transition" data-drawer-close aria-label="{{ __('Close menu') }}">
            @svg('heroicon-o-x-mark', 'size-5')
        </button>
    </div>

    <!-- Quick Tabs / Links -->
    <div class="flex border-b border-slate-100 bg-slate-50 text-sm font-bold text-slate-600">
        <a href="{{ route('store.home') }}" class="flex-1 py-3 text-center border-r border-slate-200 hover:text-purple-700">{{ __('Home') }}</a>
        <a href="{{ route('store.shop.index') }}" class="flex-1 py-3 text-center border-r border-slate-200 hover:text-purple-700">{{ __('Shop') }}</a>
        <a href="{{ route('store.contact') }}" class="flex-1 py-3 text-center hover:text-purple-700">{{ __('Contact') }}</a>
    </div>

    <!-- Multi-level Categories in Drawer -->
    <div class="flex-1 overflow-y-auto p-4 space-y-1" id="mobile-drawer-categories">
        <p class="px-2 py-1.5 text-xs font-black uppercase tracking-wider text-purple-700">{{ __('Categories') }}</p>
        @foreach($menuCategories as $category)
            <div class="rounded-xl border border-slate-100 overflow-hidden bg-white" data-drawer-parent>
                <div class="flex items-center justify-between p-3 hover:bg-purple-50/50 transition">
                    <a href="{{ $category->permalink }}" class="flex items-center gap-2.5 text-sm font-bold text-slate-800 hover:text-purple-700">
                        @if($category->image)
                            <img src="{{ asset('storage/'.ltrim($category->image, '/')) }}" alt="{{ $category->name }}" class="size-6 rounded-md object-cover">
                        @else
                            <span class="grid size-6 place-items-center rounded-md bg-purple-100 text-xs font-black text-purple-700">{{ mb_substr($category->name, 0, 1) }}</span>
                        @endif
                        <span>{{ $category->name }}</span>
                    </a>
                    @if($category->children && $category->children->count() > 0)
                        <button type="button" class="p-1 text-slate-400 hover:text-purple-700" data-drawer-toggle aria-label="{{ __('Toggle Subcategories') }}">
                            @svg('heroicon-o-chevron-down', 'size-4 transition-transform duration-200')
                        </button>
                    @endif
                </div>

                @if($category->children && $category->children->count() > 0)
                    <div class="hidden space-y-1.5 border-t border-slate-100 bg-slate-50/70 px-4 py-2 text-sm font-medium text-slate-600" data-drawer-children>
                        @foreach($category->children as $subcat)
                            <div>
                                <a href="{{ $subcat->permalink }}" class="block py-1 text-slate-700 hover:text-purple-700 font-semibold">
                                    • {{ $subcat->name }}
                                </a>
                                @if($subcat->children && $subcat->children->count() > 0)
                                    <div class="pl-4 py-1 space-y-1 border-l border-purple-200">
                                        @foreach($subcat->children as $child)
                                            <a href="{{ $child->permalink }}" class="block text-xs text-slate-500 hover:text-purple-700">
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
        <form action="{{ route('store.language.update') }}" method="POST" class="mb-3" data-language-form>
            @csrf
            <label for="mobile-language" class="mb-1.5 block text-xs font-black uppercase tracking-wider text-slate-500">{{ __('Language') }}</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-purple-700">@svg('heroicon-o-language', 'size-5')</span>
                <select
                    id="mobile-language"
                    name="locale"
                    class="h-11 w-full cursor-pointer appearance-none rounded-xl border border-slate-200 bg-white pl-11 pr-10 text-sm font-bold text-slate-700 outline-none transition focus:border-purple-500 focus:ring-2 focus:ring-purple-100"
                    data-language-select
                >
                    <option value="en" @selected(app()->isLocale('en'))>English</option>
                    <option value="bn" @selected(app()->isLocale('bn'))>বাংলা</option>
                </select>
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">@svg('heroicon-o-chevron-down', 'size-4')</span>
            </div>
            <noscript><button type="submit" class="mt-2 w-full rounded-lg bg-purple-100 py-2 text-sm font-bold text-purple-700">OK</button></noscript>
        </form>
        <a href="{{ auth()->check() ? route('store.account.dashboard') : route('store.account.login') }}" class="flex items-center justify-center gap-2 rounded-xl bg-purple-700 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-purple-800">
            @svg('heroicon-o-user', 'size-4')
            <span>{{ auth()->check() ? __('My Profile') : __('Login / Sign Up') }}</span>
        </a>
    </div>
</aside>
