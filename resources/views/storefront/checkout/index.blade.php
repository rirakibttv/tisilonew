@extends('layouts.storefront')

@section('title', 'চেকআউট — Tisilo')

@section('content')
    @php
        $selectedQuote = $regions->get((int) old('shipping_region_id'));
        $districtRegions = $regions->unique(fn (array $quote): string => mb_strtolower(trim((string) $quote['district'])))->values();
    @endphp
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
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <h2 class="text-xl font-black text-slate-950">ডেলিভারি ঠিকানা</h2>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <label class="text-sm font-bold text-slate-700 sm:col-span-2">
                            সম্পূর্ণ ঠিকানা <span class="text-rose-500">*</span>
                            <textarea name="address_line" required rows="3" autocomplete="street-address" placeholder="বাসা/রোড/এলাকার বিস্তারিত ঠিকানা" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-medium outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">{{ old('address_line') }}</textarea>
                        </label>
                        <div class="relative text-sm font-bold text-slate-700 sm:col-span-2" data-district-combobox>
                            <label for="checkout-district">জেলার নাম লিখুন <span class="text-rose-500">*</span></label>
                            <input id="checkout-district" name="district_search" type="text" value="{{ old('district_search', $selectedQuote['district'] ?? '') }}" required autocomplete="off" placeholder="জেলা লিখে নির্বাচন করুন" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="checkout-district-options" data-district-search class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-medium outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
                            <input type="hidden" name="shipping_region_id" value="{{ old('shipping_region_id') }}" data-shipping-region>
                            <div id="checkout-district-options" data-district-options role="listbox" class="absolute inset-x-0 top-full z-30 mt-1 hidden max-h-64 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl">
                                @foreach ($districtRegions as $quote)
                                    <button type="button" role="option" data-region-option data-region-id="{{ $quote['region_id'] }}" class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm hover:bg-orange-50"><span>{{ $quote['district'] }}</span><span class="text-xs font-semibold text-slate-500">৳{{ number_format($quote['amount'], 0) }}</span></button>
                                @endforeach
                            </div>
                            @if ($regions->isEmpty())
                                <span class="mt-2 block text-xs text-rose-600">এই কার্টের Shipping Class-এর জন্য কোনো সক্রিয় Region rate পাওয়া যায়নি।</span>
                            @endif
                        </div>
                        <label class="text-sm font-bold text-slate-700 sm:col-span-2">
                            থানা/উপজেলা লিখুন <span class="text-rose-500">*</span>
                            <input name="thana" value="{{ old('thana') }}" required maxlength="120" autocomplete="address-level3" placeholder="থানা বা উপজেলার নাম" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-medium outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
                            <span class="mt-2 block text-xs font-normal text-slate-500">ডেলিভারি লোকেশন নিশ্চিত করতে লিখুন; চার্জ জেলা অনুযায়ী হিসাব হবে।</span>
                        </label>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <h2 class="text-xl font-black text-slate-950">পেমেন্ট</h2>
                    <div class="mt-5">
                        @include('storefront.partials.payment-methods')
                    </div>
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
                    <div class="flex justify-between"><span class="text-slate-500">ডেলিভারি</span><span class="font-bold text-orange-600" data-shipping-amount>{{ $selectedQuote ? '৳'.number_format($selectedQuote['amount'], 0) : 'জেলা নির্বাচন করুন' }}</span></div>
                    <div class="flex justify-between border-t border-slate-100 pt-3 text-base"><span class="font-black text-slate-900">সর্বমোট</span><span class="font-black text-slate-900" data-order-total>{{ $selectedQuote ? '৳'.number_format($subtotal + $selectedQuote['amount'], 0) : 'জেলা নির্বাচন করুন' }}</span></div>
                </div>

                @if ($checkoutNote)
                    <div class="prose prose-sm mt-4 max-w-none rounded-xl bg-slate-50 p-4 text-xs text-slate-500">{!! $checkoutNote !!}</div>
                @endif

                <button data-checkout-submit class="mt-6 h-12 w-full rounded-xl bg-orange-500 px-5 text-sm font-black text-white shadow-lg shadow-orange-500/20 hover:bg-orange-600 disabled:cursor-not-allowed disabled:opacity-50">অর্ডার নিশ্চিত করুন</button>
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
            const region = form?.querySelector('[data-shipping-region]');
            const districtSearch = form?.querySelector('[data-district-search]');
            const districtOptions = form?.querySelector('[data-district-options]');
            const submit = form?.querySelector('[data-checkout-submit]');
            const quotesElement = document.getElementById('shipping-quotes');
            if (! form || ! region || ! districtSearch || ! districtOptions || ! submit || ! quotesElement) return;

            const quotes = JSON.parse(quotesElement.textContent || '{}');
            const quoteList = Object.values(quotes);
            const subtotal = Number(form.dataset.subtotal || 0);
            const money = value => `৳${new Intl.NumberFormat('bn-BD', { maximumFractionDigits: 0 }).format(value)}`;
            const districtLabel = quote => String(quote?.district || quote?.name || '').trim();
            const districtQuotes = () => {
                const seen = new Set();

                return quoteList.filter(quote => {
                    const key = districtLabel(quote).toLocaleLowerCase('bn-BD');
                    if (! key || seen.has(key)) return false;
                    seen.add(key);

                    return true;
                });
            };
            const closeOptions = () => {
                districtOptions.classList.add('hidden');
                districtSearch.setAttribute('aria-expanded', 'false');
            };
            const renderOptions = (filter = '') => {
                const query = filter.trim().toLocaleLowerCase('bn-BD');
                const matches = districtQuotes().filter(quote => districtLabel(quote).toLocaleLowerCase('bn-BD').includes(query));
                districtOptions.replaceChildren();
                matches.forEach(quote => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.role = 'option';
                    button.dataset.regionOption = '';
                    button.dataset.regionId = quote.region_id;
                    button.className = 'flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm hover:bg-orange-50';
                    const label = document.createElement('span');
                    label.className = 'font-bold';
                    label.textContent = districtLabel(quote);
                    const price = document.createElement('span');
                    price.className = 'text-xs font-semibold text-slate-500';
                    price.textContent = money(Number(quote.amount));
                    button.append(label, price);
                    districtOptions.append(button);
                });
                if (! matches.length) {
                    const empty = document.createElement('p');
                    empty.className = 'px-3 py-3 text-xs font-semibold text-rose-600';
                    empty.textContent = 'এই জেলার জন্য ডেলিভারি রেট পাওয়া যায়নি।';
                    districtOptions.append(empty);
                }
            };
            const update = () => {
                const quote = quotes[region.value];
                form.querySelector('[data-shipping-amount]').textContent = quote ? money(Number(quote.amount)) : 'জেলা নির্বাচন করুন';
                form.querySelector('[data-order-total]').textContent = quote ? money(subtotal + Number(quote.amount)) : 'জেলা নির্বাচন করুন';
                submit.disabled = ! quote;
            };
            const selectDistrict = quote => {
                if (! quote) return;
                region.value = String(quote.region_id);
                districtSearch.value = districtLabel(quote);
                closeOptions();
                update();
            };

            districtSearch.addEventListener('focus', () => {
                renderOptions(districtSearch.value);
                districtOptions.classList.remove('hidden');
                districtSearch.setAttribute('aria-expanded', 'true');
            });
            districtSearch.addEventListener('input', () => {
                const value = districtSearch.value.trim().toLocaleLowerCase('bn-BD');
                const exact = districtQuotes().find(quote => districtLabel(quote).toLocaleLowerCase('bn-BD') === value);
                region.value = exact ? String(exact.region_id) : '';
                renderOptions(districtSearch.value);
                districtOptions.classList.remove('hidden');
                districtSearch.setAttribute('aria-expanded', 'true');
                update();
            });
            districtSearch.addEventListener('keydown', event => {
                if (event.key === 'Escape') closeOptions();
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    districtOptions.querySelector('[data-region-option]')?.focus();
                }
                if (event.key === 'Enter') {
                    const first = districtOptions.querySelector('[data-region-option]');
                    if (first && ! districtOptions.classList.contains('hidden')) {
                        event.preventDefault();
                        selectDistrict(quotes[first.dataset.regionId]);
                    }
                }
            });
            districtOptions.addEventListener('click', event => {
                const option = event.target.closest('[data-region-option]');
                if (option) selectDistrict(quotes[option.dataset.regionId]);
            });
            document.addEventListener('click', event => {
                if (! event.target.closest('[data-district-combobox]')) closeOptions();
            });
            form.addEventListener('submit', event => {
                if (! region.value) event.preventDefault();
            });
            renderOptions();
            update();
        })();
    </script>
@endpush
