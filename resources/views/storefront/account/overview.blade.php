@extends('storefront.account.layout')

@section('title', __('Customer Dashboard').' — Tisilo')

@section('account-content')
    <div class="overflow-hidden rounded-3xl bg-gradient-to-r from-slate-950 via-violet-950 to-violet-700 p-6 text-white shadow-xl sm:p-8">
        <p class="text-xs font-black uppercase tracking-[0.2em] text-violet-200">{{ __('Customer Dashboard') }}</p>
        <div class="mt-3 flex flex-wrap items-end justify-between gap-5">
            <div>
                <h1 class="text-2xl font-black sm:text-3xl">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">{{ __('Manage orders, reviews, returns and your account easily from one place.') }}</p>
            </div>
            <a href="{{ route('store.products.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-black text-violet-700 shadow-lg">{{ __('Shop Now') }} @svg('heroicon-o-arrow-right', 'size-4')</a>
        </div>
    </div>

    <div class="mt-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
        @foreach([
            ['All Orders', $summary['orders'], 'heroicon-o-shopping-bag', 'bg-violet-50 text-violet-700'],
            ['To Pay', $summary['to_pay'], 'heroicon-o-banknotes', 'bg-amber-50 text-amber-700'],
            ['To Ship', $summary['to_ship'], 'heroicon-o-truck', 'bg-sky-50 text-sky-700'],
            ['To Receive', $summary['to_receive'], 'heroicon-o-archive-box-arrow-down', 'bg-emerald-50 text-emerald-700'],
        ] as [$label, $value, $icon, $colors])
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <span class="grid size-10 place-items-center rounded-xl {{ $colors }}">@svg($icon, 'size-5')</span>
                <p class="mt-4 text-2xl font-black text-slate-950">{{ $value }}</p>
                <p class="mt-1 text-xs font-bold uppercase tracking-wide text-slate-400">{{ __($label) }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-5 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.15em] text-violet-600">{{ __('Order Activity') }}</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">{{ __('Recent Orders') }}</h2>
            </div>
            <a href="{{ route('store.account.orders', 'all') }}" class="text-sm font-black text-violet-600">{{ __('View All') }} →</a>
        </div>
        @include('storefront.account.partials.order-list', ['orders' => $orders])
    </div>
@endsection
