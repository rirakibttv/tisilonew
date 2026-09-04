@php
    $cartCount = collect(session('store_cart', []))->sum('quantity');
@endphp

<!-- Wave Accent Line -->
<div class="h-1 w-full bg-gradient-to-r from-transparent via-purple-700 to-transparent opacity-90"></div>

<!-- Supermarket Footer -->
<footer class="bg-slate-950 text-slate-300 pb-20 md:pb-0">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Brand Block -->
            <div class="space-y-4">
                <a href="{{ route('store.home') }}" class="inline-block">
                    @if(filled($generalSettings['white_logo'] ?? null))
                        @php($whiteLogo = $generalSettings['white_logo'])
                        <img src="{{ asset('storage/'.ltrim($whiteLogo, '/')) }}" alt="{{ $generalSettings['site_name'] ?? 'Tisilo' }}" class="h-10 w-auto object-contain">
                    @else
                        <span class="text-2xl font-black text-white tracking-tight">{{ strtoupper($generalSettings['site_name'] ?? 'Tisilo') }}</span>
                        <span class="block text-[10px] font-bold uppercase tracking-widest text-purple-400">Supermarket</span>
                    @endif
                </a>
                <p class="text-xs leading-relaxed text-slate-400 max-w-sm">
                    {{ $generalSettings['footer_about_text'] ?? 'We believe in quality and customer satisfaction. Biggest Online Shopping Zone in Bangladesh.' }}
                </p>

                <!-- Social Icons -->
                @if(count($socialLinks ?? []))
                    <div class="flex flex-wrap gap-2 pt-2">
                        @foreach($socialLinks as $social)
                            @if($social['status'] ?? false)
                                <a href="{{ $social['link'] }}" target="_blank" rel="noopener" class="grid size-9 place-items-center rounded-xl bg-white/10 text-white transition hover:bg-purple-700 hover:-translate-y-0.5">
                                    <span class="text-xs font-bold">{{ substr($social['title'], 0, 2) }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Column 2: Useful Links -->
            <div>
                <h3 class="relative inline-block pb-2 text-sm font-black uppercase tracking-wider text-white after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-8 after:bg-purple-600 after:rounded-full">
                    Useful Link
                </h3>
                <ul class="mt-4 space-y-2.5 text-xs text-slate-400">
                    <li><a href="{{ route('store.pages.show', 'complaint') }}" class="transition hover:text-purple-400 hover:pl-1">Complaints</a></li>
                    <li><a href="{{ route('store.pages.show', 'order-procedure') }}" class="transition hover:text-purple-400 hover:pl-1">Order procedure</a></li>
                    <li><a href="{{ route('store.pages.show', 'delivery-rules') }}" class="transition hover:text-purple-400 hover:pl-1">Delivery Rules</a></li>
                    <li><a href="{{ route('store.pages.show', 'return-policy') }}" class="transition hover:text-purple-400 hover:pl-1">Return Policy</a></li>
                    <li><a href="{{ auth()->check() ? route('store.account.dashboard') : route('store.account.login') }}" class="transition hover:text-purple-400 hover:pl-1">Track Order</a></li>
                </ul>
            </div>

            <!-- Column 3: Legal Links -->
            <div>
                <h3 class="relative inline-block pb-2 text-sm font-black uppercase tracking-wider text-white after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-8 after:bg-purple-600 after:rounded-full">
                    Link
                </h3>
                <ul class="mt-4 space-y-2.5 text-xs text-slate-400">
                    <li><a href="{{ route('store.pages.show', 'privacy-policy') }}" class="transition hover:text-purple-400 hover:pl-1">Privacy Policy</a></li>
                    <li><a href="{{ route('store.pages.show', 'terms-and-conditions') }}" class="transition hover:text-purple-400 hover:pl-1">Terms & Conditions</a></li>
                    <li><a href="{{ route('store.shop.index') }}" class="transition hover:text-purple-400 hover:pl-1">Shop All Products</a></li>
                    <li><a href="/admin" class="transition hover:text-purple-400 hover:pl-1">Seller Center</a></li>
                </ul>
            </div>

            <!-- Column 4: Newsletter -->
            <div>
                <h3 class="relative inline-block pb-2 text-sm font-black uppercase tracking-wider text-white after:absolute after:bottom-0 after:left-0 after:h-0.5 after:w-8 after:bg-purple-600 after:rounded-full">
                    Newsletter
                </h3>
                <p class="mt-4 text-xs text-slate-400 leading-relaxed">
                    Subscribe for offers and updates.
                </p>
                <form onsubmit="event.preventDefault(); alert('ধন্যবাদ! আপনার সাবস্ক্রিপশন সম্পন্ন হয়েছে।');" class="mt-4 flex items-center">
                    <input type="email" required placeholder="আপনার ইমেইল দিন..." class="h-10 w-full rounded-l-xl border border-slate-800 bg-slate-900 px-3.5 text-xs text-white outline-none focus:border-purple-600">
                    <button type="submit" class="h-10 rounded-r-xl bg-purple-700 px-4 text-xs font-bold text-white transition hover:bg-purple-800">
                        Join
                    </button>
                </form>

                <!-- Payment Methods -->
                <div class="mt-6">
                    <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">পেমেন্ট মেথড</p>
                    <div class="mt-2 flex items-center gap-2">
                        <span class="rounded-lg bg-slate-900 border border-slate-800 px-2.5 py-1.5 text-[10px] font-black text-white">COD</span>
                        <span class="rounded-lg bg-slate-900 border border-slate-800 px-2.5 py-1.5 text-[10px] font-black text-rose-400">bKash</span>
                        <span class="rounded-lg bg-slate-900 border border-slate-800 px-2.5 py-1.5 text-[10px] font-black text-amber-400">Nagad</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Copyright Bar -->
    <div class="border-t border-slate-900 bg-black px-4 py-5 text-center text-xs text-slate-500">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 sm:flex-row">
            <p>© {{ date('Y') }} {{ $generalSettings['site_name'] ?? 'Tisilo Supermarket' }}. সর্বস্বত্ব সংরক্ষিত।</p>
            <p class="text-slate-600 text-[11px]">Made with passion for online shopping in Bangladesh</p>
        </div>
    </div>
</footer>

<!-- Fixed Mobile Bottom Navigation Bar (Matching tisilo.net mobile experience) -->
<nav class="fixed bottom-0 left-0 right-0 z-40 border-t border-slate-200 bg-white/98 py-1.5 shadow-2xl backdrop-blur-md md:hidden" aria-label="Mobile Navigation">
    <div class="grid grid-cols-5 text-center">
        <!-- 1. Home -->
        <a href="{{ route('store.home') }}" class="flex flex-col items-center justify-center py-1 text-[10px] font-bold transition {{ request()->routeIs('store.home') ? 'text-purple-700' : 'text-slate-600 hover:text-purple-700' }}">
            @svg('heroicon-o-home', 'size-5')
            <span class="mt-0.5">Home</span>
        </a>

        <!-- 2. Category Drawer Trigger -->
        <button type="button" class="flex flex-col items-center justify-center py-1 text-[10px] font-bold text-slate-600 hover:text-purple-700 transition" id="mobile-nav-category-btn">
            @svg('heroicon-o-squares-2x2', 'size-5')
            <span class="mt-0.5">Category</span>
        </button>

        <!-- 3. Tracking -->
        <a href="{{ auth()->check() ? route('store.account.dashboard') : route('store.account.login') }}" class="flex flex-col items-center justify-center py-1 text-[10px] font-bold text-slate-600 hover:text-purple-700 transition">
            @svg('heroicon-o-truck', 'size-5')
            <span class="mt-0.5">Tracking</span>
        </a>

        <!-- 4. Cart -->
        <a href="{{ route('store.cart.index') }}" class="relative flex flex-col items-center justify-center py-1 text-[10px] font-bold transition {{ request()->routeIs('store.cart.*') ? 'text-purple-700' : 'text-slate-600 hover:text-purple-700' }}">
            <span class="relative">
                @svg('heroicon-o-shopping-bag', 'size-5')
                @if($cartCount > 0)
                    <span class="absolute -right-2 -top-1.5 grid size-4 place-items-center rounded-full bg-purple-700 text-[9px] font-black text-white">{{ $cartCount }}</span>
                @endif
            </span>
            <span class="mt-0.5">Cart</span>
        </a>

        <!-- 5. Login / Account -->
        <a href="{{ auth()->check() ? route('store.account.dashboard') : route('store.account.login') }}" class="flex flex-col items-center justify-center py-1 text-[10px] font-bold transition {{ request()->routeIs('store.account.*') ? 'text-purple-700' : 'text-slate-600 hover:text-purple-700' }}">
            @svg('heroicon-o-user', 'size-5')
            <span class="mt-0.5">{{ auth()->check() ? 'Account' : 'Login' }}</span>
        </a>
    </div>
</nav>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Mobile Drawer Toggle
        const drawer = document.getElementById('mobile-drawer');
        const backdrop = document.getElementById('mobile-drawer-backdrop');
        const menuButtons = document.querySelectorAll('[data-mobile-menu-button], #mobile-nav-category-btn');
        const closeButtons = document.querySelectorAll('[data-drawer-close]');

        function openDrawer() {
            if (drawer && backdrop) {
                drawer.classList.remove('-translate-x-full');
                backdrop.classList.remove('opacity-0', 'pointer-events-none');
                backdrop.classList.add('opacity-100', 'pointer-events-auto');
                document.body.classList.add('overflow-hidden');
            }
        }

        function closeDrawer() {
            if (drawer && backdrop) {
                drawer.classList.add('-translate-x-full');
                backdrop.classList.remove('opacity-100', 'pointer-events-auto');
                backdrop.classList.add('opacity-0', 'pointer-events-none');
                document.body.classList.remove('overflow-hidden');
            }
        }

        menuButtons.forEach(btn => btn.addEventListener('click', openDrawer));
        closeButtons.forEach(btn => btn.addEventListener('click', closeDrawer));

        // Subcategory Collapsible in Drawer
        const toggleButtons = document.querySelectorAll('[data-drawer-toggle]');
        toggleButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const parent = this.closest('[data-drawer-parent]');
                const children = parent ? parent.querySelector('[data-drawer-children]') : null;
                const icon = this.querySelector('svg');

                if (children) {
                    const isHidden = children.classList.contains('hidden');
                    if (isHidden) {
                        children.classList.remove('hidden');
                        if (icon) icon.classList.add('rotate-180');
                    } else {
                        children.classList.add('hidden');
                        if (icon) icon.classList.remove('rotate-180');
                    }
                }
            });
        });

        // Desktop "ALL CATEGORIES" Button smooth scroll / focus
        const allCatBtn = document.getElementById('all-categories-button');
        if (allCatBtn) {
            allCatBtn.addEventListener('click', function() {
                const catSidebar = document.querySelector('.group\\/cat');
                if (catSidebar) {
                    catSidebar.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }
    });
</script>
@endpush
