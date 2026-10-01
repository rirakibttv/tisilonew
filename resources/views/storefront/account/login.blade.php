@extends('layouts.storefront')

@section('title', __('Account Login').' — Tisilo')

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-8">
            <span class="grid size-12 place-items-center rounded-2xl bg-orange-50 text-orange-600">@svg('heroicon-o-user', 'size-6')</span>
            <h1 class="mt-5 text-3xl font-black text-slate-950">{{ __('Log In to Your Account') }}</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('View recent orders and save favorite products to your wishlist.') }}</p>

            <form method="POST" action="{{ route('store.account.authenticate') }}" class="mt-7 space-y-5">
                @csrf
                <div>
                    <label for="email" class="text-sm font-bold text-slate-700">{{ __('Email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-orange-400 focus:bg-white focus:ring-4 focus:ring-orange-100">
                    @error('email')<p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="text-sm font-bold text-slate-700">{{ __('Password') }}</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-orange-400 focus:bg-white focus:ring-4 focus:ring-orange-100">
                    @error('password')<p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-3 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 text-orange-500 focus:ring-orange-400">
                    {{ __('Remember Me') }}
                </label>
                <button class="h-12 w-full rounded-xl bg-orange-500 text-sm font-black text-white shadow-lg shadow-orange-500/20 transition hover:bg-orange-600">{{ __('Log In') }}</button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-500">{{ __('New Customer?') }} <a href="{{ route('store.account.register') }}" class="font-black text-orange-600 hover:text-orange-700">{{ __('Create an Account') }}</a></p>
        </div>
    </section>
@endsection
