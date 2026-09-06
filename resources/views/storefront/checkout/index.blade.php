@extends('layouts.storefront')

@section('title', 'চেকআউট — Tisilo')

@section('content')
    <section class="border-b border-slate-200 bg-white">
        <div class="storefront-shell py-10">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-orange-600">Secure checkout</p>
            <h1 class="mt-2 text-3xl font-black text-slate-950 sm:text-4xl">অর্ডার সম্পন্ন করুন</h1>
            <p class="mt-2 text-sm text-slate-500">ডেলিভারি তথ্য যাচাই করে অর্ডার নিশ্চিত করুন।</p>
        </div>
    </section>

    <section class="storefront-shell py-10">
        @if ($errors->any())
            <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-700">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('store.checkout.store') }}" class="grid gap-8 lg:grid-cols-[1fr_380px]" data-shipping-checkout data-subtotal="{{ $subtotal }}">
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
                        <label class="text-sm font-bold text-slate-700 sm:col-span-2">
                            উপজেলা/থানা নির্বাচন করুন <span class="text-rose-500">*</span>
                            <select name="shipping_region_id" required data-shipping-region class="mt-2 h-12 w-full rounded-xl border border-slate-200 bg-white px-4 font-medium outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
                                <option value="">Division › District › Upazila/থানা</option>
                                @foreach ($regions as $regionId => $quote)
                                    <option value="{{ $regionId }}" @selected((string) old('shipping_region_id') === (string) $regionId)>{{ $quote['name'] }} — ৳{{ number_format($quote['amount'], 0) }} ({{ $quote['estimated_min_days'] }}–{{ $quote['estimated_max_days'] }} দিন)</option>
                                @endforeach
                            </select>
                            @if ($regions->isEmpty())
                                <span class="mt-2 block text-xs text-rose-600">এই কার্টের Shipping Class-এর জন্য কোনো সক্রিয় Region rate পাওয়া যায়নি।</span>
                            @endif
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
                    <div class="flex justify-between"><span class="text-slate-500">ডেলিভারি</span><span class="font-bold text-orange-600" data-shipping-amount>এলাকা নির্বাচন করুন</span></div>
                    <div class="flex justify-between border-t border-slate-100 pt-3 text-base"><span class="font-black text-slate-900">সর্বমোট</span><span class="font-black text-slate-900" data-order-total>৳{{ number_format($subtotal, 0) }}</span></div>
                </div>

                @if ($checkoutNote)
                    <div class="prose prose-sm mt-4 max-w-none rounded-xl bg-slate-50 p-4 text-xs text-slate-500">{!! $checkoutNote !!}</div>
                @endif

                <button class="mt-6 h-12 w-full rounded-xl bg-orange-500 px-5 text-sm font-black text-white shadow-lg shadow-orange-500/20 hover:bg-orange-600">অর্ডার নিশ্চিত করুন</button>
                <a href="{{ route('store.cart.index') }}" class="mt-4 block text-center text-sm font-bold text-slate-500 hover:text-orange-600">কার্টে ফিরে যান</a>
            </aside>
        </form>
    </section>
@endsection

@push('scripts')
    <script type="application/json" id="shipping-quotes">@json($regions)</script>
    <script>
        (() => {
            const form = document.querySelector('[data-shipping-checkout]');
            const select = form?.querySelector('[data-shipping-region]');
            const quotesElement = document.getElementById('shipping-quotes');
            if (! form || ! select || ! quotesElement) return;

            const quotes = JSON.parse(quotesElement.textContent || '{}');
            const subtotal = Number(form.dataset.subtotal || 0);
            const money = value => `৳${new Intl.NumberFormat('bn-BD', { maximumFractionDigits: 0 }).format(value)}`;
            const update = () => {
                const quote = quotes[select.value];
                form.querySelector('[data-shipping-amount]').textContent = quote ? money(Number(quote.amount)) : 'এলাকা নির্বাচন করুন';
                form.querySelector('[data-order-total]').textContent = money(subtotal + Number(quote?.amount || 0));
            };

            select.addEventListener('change', update);
            update();
        })();
    </script>
@endpush
