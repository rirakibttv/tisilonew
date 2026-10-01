@php
    $counts = $accountNavigationCounts ?? [];
    $routeName = request()->route()?->getName();
    $routeFilter = request()->route('filter');
    $requestType = request()->route('type');
    $requestMode = request()->route('mode');
    $linkClass = 'group flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-sm font-bold transition';
    $activeClass = 'bg-violet-600 text-white shadow-md shadow-violet-200';
    $idleClass = 'text-slate-600 hover:bg-violet-50 hover:text-violet-700';
@endphp

<aside class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm lg:sticky lg:top-4">
    <div class="bg-gradient-to-br from-violet-700 via-purple-600 to-fuchsia-500 p-5 text-white">
        <div class="flex items-center gap-3">
            <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-white/20 text-lg font-black ring-1 ring-white/30">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-base font-black">{{ auth()->user()->name }}</p>
                <p class="mt-0.5 truncate text-xs text-violet-100">{{ auth()->user()->phone ?: auth()->user()->email }}</p>
            </div>
            <a href="{{ route('store.account.dashboard') }}" aria-label="{{ __('Dashboard Overview') }}" class="grid size-9 shrink-0 place-items-center rounded-xl bg-white/15 hover:bg-white/25">@svg('heroicon-o-squares-2x2', 'size-5')</a>
        </div>
    </div>

    <details class="group lg:hidden">
        <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4 text-sm font-black text-slate-800 [&::-webkit-details-marker]:hidden">
            {{ __('Account Menu') }}
            @svg('heroicon-o-chevron-down', 'size-4 transition group-open:rotate-180')
        </summary>
        <nav class="max-h-[65vh] overflow-y-auto border-t border-slate-100 p-3" aria-label="{{ __('Customer Account Navigation') }}">
            @include('storefront.account.partials.navigation-menu')
        </nav>
    </details>

    <nav class="hidden max-h-[calc(100vh-150px)] overflow-y-auto p-3 lg:block" aria-label="{{ __('Customer Account Navigation') }}">
        @include('storefront.account.partials.navigation-menu')
    </nav>
</aside>
