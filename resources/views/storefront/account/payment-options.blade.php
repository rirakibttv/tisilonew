@extends('storefront.account.layout')

@section('title', 'Payment Option — Tisilo')

@section('account-content')
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-5 sm:p-6">
            <p class="text-xs font-black uppercase tracking-[0.15em] text-violet-600">Manage My Account</p>
            <h1 class="mt-1 text-2xl font-black text-slate-950">Payment Option</h1>
            <p class="mt-2 text-sm text-slate-500">চেকআউটে আগে থেকে নির্বাচিত থাকবে এমন পেমেন্ট পদ্ধতি ঠিক করুন। কোনো কার্ড বা গোপন তথ্য এখানে সংরক্ষণ করা হয় না।</p>
        </div>
        <form method="POST" action="{{ route('store.account.payment-options.update') }}" class="p-5 sm:p-6">
            @csrf
            @method('PATCH')
            <div class="grid gap-4 sm:grid-cols-2">
                @forelse($methods as $key => $method)
                    <label class="relative cursor-pointer rounded-2xl border p-5 transition has-[:checked]:border-violet-500 has-[:checked]:bg-violet-50 has-[:checked]:ring-2 has-[:checked]:ring-violet-100">
                        <input type="radio" name="default_method" value="{{ $key }}" {{ $selectedMethod === $key ? 'checked' : '' }} class="absolute right-4 top-4 border-slate-300 text-violet-600 focus:ring-violet-500">
                        <span class="grid size-11 place-items-center rounded-xl bg-white text-violet-600 shadow-sm">@svg('heroicon-o-credit-card', 'size-6')</span>
                        <span class="mt-4 block font-black text-slate-950">{{ $method['label'] }}</span>
                        <span class="mt-1 block text-sm leading-6 text-slate-500">{{ $method['description'] }}</span>
                    </label>
                @empty
                    <div class="col-span-full rounded-2xl bg-amber-50 p-5 text-sm font-bold text-amber-700">বর্তমানে কোনো পেমেন্ট পদ্ধতি সক্রিয় নেই।</div>
                @endforelse
            </div>
            @error('default_method')<p class="mt-3 text-sm font-bold text-rose-600">{{ $message }}</p>@enderror
            @if(count($methods))<button class="mt-5 rounded-xl bg-violet-600 px-6 py-3 text-sm font-black text-white">Save payment option</button>@endif
        </form>
    </div>
@endsection
