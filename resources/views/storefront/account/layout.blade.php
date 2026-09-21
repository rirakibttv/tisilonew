@extends('layouts.storefront')

@section('content')
    <section class="storefront-shell py-5 sm:py-8">
        @if(session('status'))
            <div class="mb-5 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-700 shadow-sm">
                @svg('heroicon-o-check-circle', 'size-5 shrink-0')
                {{ session('status') }}
            </div>
        @endif

        <div class="grid items-start gap-5 lg:grid-cols-[286px_minmax(0,1fr)]">
            @include('storefront.account.partials.navigation-responsive')

            <div class="min-w-0">
                @yield('account-content')
            </div>
        </div>
    </section>
@endsection
