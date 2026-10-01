@extends('layouts.storefront')

@section('title', __('New Account').' — Tisilo')

@section('content')
    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-xl rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-8">
            <span class="grid size-12 place-items-center rounded-2xl bg-orange-50 text-orange-600">@svg('heroicon-o-user-plus', 'size-6')</span>
            <h1 class="mt-5 text-3xl font-black text-slate-950">{{ __('Create a Tisilo Account') }}</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('Enjoy faster checkout, order history and a personal wishlist.') }}</p>

            <form method="POST" action="{{ route('store.account.store') }}" class="mt-7 grid gap-5 sm:grid-cols-2">
                @csrf
                <div class="sm:col-span-2">
                    <label for="name" class="text-sm font-bold text-slate-700">{{ __('Full Name') }}</label>
                    <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-orange-400 focus:bg-white focus:ring-4 focus:ring-orange-100">
                    @error('name')<p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="text-sm font-bold text-slate-700">{{ __('Email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-orange-400 focus:bg-white focus:ring-4 focus:ring-orange-100">
                    @error('email')<p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="phone" class="text-sm font-bold text-slate-700">{{ __('Mobile Number') }}</label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-orange-400 focus:bg-white focus:ring-4 focus:ring-orange-100">
                    @error('phone')<p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="text-sm font-bold text-slate-700">{{ __('Password') }}</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-orange-400 focus:bg-white focus:ring-4 focus:ring-orange-100">
                    @error('password')<p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="text-sm font-bold text-slate-700">{{ __('Confirm Password') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-orange-400 focus:bg-white focus:ring-4 focus:ring-orange-100">
                </div>
                <button class="h-12 rounded-xl bg-orange-500 text-sm font-black text-white shadow-lg shadow-orange-500/20 transition hover:bg-orange-600 sm:col-span-2">{{ __('Create Account') }}</button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-500">{{ __('Already have an account?') }} <a href="{{ route('store.account.login') }}" class="font-black text-orange-600 hover:text-orange-700">{{ __('Log In') }}</a></p>
        </div>
    </section>
@endsection
