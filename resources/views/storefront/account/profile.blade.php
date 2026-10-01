@extends('storefront.account.layout')

@section('title', __('My Profile').' — Tisilo')

@section('account-content')
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-5 sm:p-6">
            <p class="text-xs font-black uppercase tracking-[0.15em] text-violet-600">{{ __('Manage My Account') }}</p>
            <h1 class="mt-1 text-2xl font-black text-slate-950">{{ __('My Profile') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('Securely update your personal information and password.') }}</p>
        </div>
        <form method="POST" action="{{ route('store.account.profile.update') }}" class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
            @csrf
            @method('PATCH')
            <label class="text-sm font-bold text-slate-700">Full name
                <input name="name" value="{{ old('name', auth()->user()->name) }}" required class="mt-2 w-full rounded-xl border-slate-200 focus:border-violet-500 focus:ring-violet-500">
                @error('name')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
            </label>
            <label class="text-sm font-bold text-slate-700">Mobile number
                <input name="phone" value="{{ old('phone', auth()->user()->phone) }}" required class="mt-2 w-full rounded-xl border-slate-200 focus:border-violet-500 focus:ring-violet-500">
                @error('phone')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
            </label>
            <label class="text-sm font-bold text-slate-700 sm:col-span-2">Email address
                <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required class="mt-2 w-full rounded-xl border-slate-200 focus:border-violet-500 focus:ring-violet-500">
                @error('email')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
            </label>

            <div class="sm:col-span-2"><div class="border-t border-slate-100 pt-5"><h2 class="font-black text-slate-900">{{ __('Change Password') }} <span class="text-xs font-normal text-slate-400">({{ __('Optional') }})</span></h2></div></div>
            <label class="text-sm font-bold text-slate-700">Current password
                <input type="password" name="current_password" autocomplete="current-password" class="mt-2 w-full rounded-xl border-slate-200 focus:border-violet-500 focus:ring-violet-500">
                @error('current_password')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
            </label>
            <div></div>
            <label class="text-sm font-bold text-slate-700">New password
                <input type="password" name="password" autocomplete="new-password" class="mt-2 w-full rounded-xl border-slate-200 focus:border-violet-500 focus:ring-violet-500">
                @error('password')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
            </label>
            <label class="text-sm font-bold text-slate-700">Confirm new password
                <input type="password" name="password_confirmation" autocomplete="new-password" class="mt-2 w-full rounded-xl border-slate-200 focus:border-violet-500 focus:ring-violet-500">
            </label>
            <div class="sm:col-span-2"><button class="rounded-xl bg-violet-600 px-6 py-3 text-sm font-black text-white shadow-lg shadow-violet-200">{{ __('Save Changes') }}</button></div>
        </form>
    </div>
@endsection
