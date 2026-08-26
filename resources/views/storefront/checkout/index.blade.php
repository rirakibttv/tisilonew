@extends('layouts.storefront')

@section('title', 'চেকআউট — Tisilo')

@section('content')
    <section class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-orange-600">Secure checkout</p>
            <h1 class="mt-2 text-3xl font-black text-slate-950 sm:text-4xl">অর্ডার সম্পন্ন করুন</h1>
            <p class="mt-2 text-sm text-slate-500">ডেলিভারি তথ্য যাচাই করে অর্ডার নিশ্চিত করুন।</p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        @if ($errors->any())
            <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-700">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('store.checkout.store') }}" class="grid gap-8 lg:grid-cols-[1fr_380px]">
            @csrf
            <div class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <h2 class="text-xl font-black text-slate-950">যোগাযোগের তথ্য</h2>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <label class="text-sm font-bold text-slate-700">
                            নাম <span class="text-rose-500">*</span>
                            <input name="customer_name" value="{{ old('customer_name', auth()->user()?->name) }}" required autocomplete="name" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-medium outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
                        </label>
                        <label class="text-sm font-bold text-slate-700">
                            মোবাইল নম্বর <span class="text-rose-500">*</span>
                            <input name="customer_phone" value="{{ old('customer_phone', auth()->user()?->phone) }}" required inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-medium outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
                        </label>
                        <label class="text-sm font-bold text-slate-700 sm:col-span-2">
                            ইমেইল (ঐচ্ছিক)
                            <input type="email" name="customer_email" value="{{ old('customer_email', auth()->user()?->email) }}" autocomplete="email" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-medium outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
                        </label>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <h2 class="text-xl font-black text-slate-950">ডেলিভারি ঠিকানা</h2>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <label class="text-sm font-bold text-slate-700 sm:col-span-2">
                            সম্পূর্ণ ঠিকানা <span class="text-rose-500">*</span>
                            <textarea name="address_line" required rows="3" autocomplete="street-address" placeholder="বাসা/রোড/এলাকার বিস্তারিত ঠিকানা" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-medium outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">{{ old('address_line') }}</textarea>
                        </label>
                        <label class="text-sm font-bold text-slate-700">
                            জেলা <span class="text-rose-500">*</span>
                            <input name="district" value="{{ old('district') }}" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-medium outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
                        </label>
                        <label class="text-sm font-bold text-slate-700">
                            উপজেলা/থানা
                            <input name="upazila" value="{{ old('upazila') }}" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-medium outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
                        </label>
                        <label class="text-sm font-bold text-slate-700">
                            পোস্ট কোড
                            <input name="postal_code" value="{{ old('postal_code') }}" inputmode="numeric" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-medium outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
                        </label>
                        <label class="text-sm font-bold text-slate-700">
                            ডেলিভারি এলাকা <span class="text-rose-500">*</span>
                            <select name="shipping_zone" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 bg-white px-4 font-medium outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
                                <option value="">এলাকা নির্বাচন করুন</option>
                                @foreach ($zones as $key => $zone)
                                    <option value="{{ $key }}" @selected(old('shipping_zone') === $key)>{{ $zone['name'] }} — ৳{{ number_format($zone['amount'], 0) }} ({{ $zone['estimated_days'] }} দিন)</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <h2 class="text-xl font-black text-slate-950">পেমেন্ট</h2>
                    <label class="mt-5 flex cursor-pointer items-center gap-4 rounded-2xl border-2 border-orange-200 bg-orange-50 p-5">
                        <input type="radio" name="payment_method" value="cod" checked class="size-5 accent-orange-500">
                        <span>
                            <span class="block font-black text-slate-900">ক্যাশ অন ডেলিভারি</span>
                            <span class="mt-1 block text-xs text-slate-500">পণ্য হাতে পাওয়ার পর মূল্য পরিশোধ করুন।</span>
                        </span>
                    </label>
                    <label class="mt-5 block text-sm font-bold text-slate-700">
                        অর্ডার নোট (ঐচ্ছিক)
                        <textarea name="notes" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-medium outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">{{ old('notes') }}</textarea>
                    </label>
                </div>
            </div>

            <aside class="h-fit rounded-3xl border border-slate-200 bg-white p-6 shadow-sm lg:sticky lg:top-28">
                <h2 class="text-xl font-black text-slate-950">আপনার অর্ডার</h2>
                <div class="mt-5 max-h-72 space-y-4 overflow-auto pr-1">
                    @foreach ($lines as $line)
                        <div class="flex gap-3 border-b border-slate-100 pb-4 last:border-0">
                            <div class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-xl bg-orange-50 font-black text-orange-500">
                                @if ($line['image'])<img src="{{ $line['image'] }}" alt="" class="size-full object-cover">@else{{ mb_strtoupper(mb_substr($line['name'], 0, 1)) }}@endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-black text-slate-900">{{ $line['name'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $line['quantity'] }} × ৳{{ number_format($line['price'], 0) }}</p>
                            </div>
                            <span class="text-sm font-black text-slate-900">৳{{ number_format($line['price'] * $line['quantity'], 0) }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 space-y-3 border-t border-slate-200 pt-5 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">সাবটোটাল</span><span class="font-bold">৳{{ number_format($subtotal, 0) }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">ডেলিভারি</span><span class="font-bold text-orange-600">এলাকা অনুযায়ী</span></div>
                </div>

                <label class="mt-6 flex items-start gap-3 text-xs leading-5 text-slate-500">
                    <input type="checkbox" name="terms" value="1" required class="mt-0.5 size-4 shrink-0 accent-orange-500">
                    <span>আমি অর্ডার, ডেলিভারি ও রিটার্ন সংক্রান্ত শর্তাবলিতে সম্মত।</span>
                </label>

                @if ($checkoutNote)
                    <div class="prose prose-sm mt-4 max-w-none rounded-xl bg-slate-50 p-4 text-xs text-slate-500">{!! $checkoutNote !!}</div>
                @endif

                <button class="mt-6 h-12 w-full rounded-xl bg-orange-500 px-5 text-sm font-black text-white shadow-lg shadow-orange-500/20 hover:bg-orange-600">অর্ডার নিশ্চিত করুন</button>
                <a href="{{ route('store.cart.index') }}" class="mt-4 block text-center text-sm font-bold text-slate-500 hover:text-orange-600">কার্টে ফিরে যান</a>
            </aside>
        </form>
    </section>
@endsection
