<section id="order-now" class="scroll-mt-6 bg-orange-50 px-4 py-16 pb-28 sm:px-6 md:pb-16">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-2xl text-center">
            <p class="campaign-text text-xs font-black uppercase tracking-[0.2em]">সহজ ও নিরাপদ অর্ডার</p>
            <h2 class="mt-3 text-3xl font-black sm:text-4xl">এই পেজেই অর্ডার সম্পন্ন করুন</h2>
            <p class="mt-3 text-sm leading-6 text-slate-600">পণ্য ও পরিমাণ বেছে আপনার তথ্য দিন। উপজেলা/থানা অনুযায়ী ডেলিভারি চার্জ যোগ হবে—আলাদা চেকআউট পেজে যেতে হবে না।</p>
        </div>

        @if($errors->any())
            <div role="alert" class="mx-auto mt-6 max-w-4xl rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700">
                <p class="font-black">অর্ডারটি জমা হয়নি। নিচের তথ্যগুলো ঠিক করুন:</p>
                <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('store.landing.order', $landingPage) }}" class="mt-9 grid items-start gap-6 lg:grid-cols-2" data-campaign-checkout data-quote-url="{{ route('store.landing.quote', $landingPage) }}" data-preview="{{ $preview ? '1' : '0' }}">
            @csrf
            <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">
            <div class="rounded-3xl border border-orange-100 bg-white p-5 shadow-lg sm:p-8">
                <h3 class="text-xl font-black">১. পণ্য ও অর্ডারের হিসাব</h3>
                <label for="campaign-product" class="mt-6 block text-sm font-bold">পণ্য নির্বাচন করুন</label>
                <select id="campaign-product" name="product_id" required class="mt-2 min-h-12 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm">
                    @foreach($checkoutProducts as $product)
                        <option value="{{ $product['id'] }}" @selected($initialSelection['product_id'] === $product['id'])>{{ $product['name'] }}</option>
                    @endforeach
                </select>
                @php($selectedProduct = collect($checkoutProducts)->firstWhere('id', $initialSelection['product_id']))
                @php($selectedVariation = collect($selectedProduct['variations'])->firstWhere('id', $initialSelection['product_variation_id']))
                <div class="mt-5 flex items-center gap-4">
                    <img data-campaign-image src="{{ $selectedProduct['image'] ?: '' }}" alt="{{ $selectedProduct['name'] }}" @class(['size-24 rounded-2xl bg-slate-50 object-cover', 'hidden' => ! $selectedProduct['image']])>
                    <div class="min-w-0">
                        <p class="break-words font-bold" data-campaign-name>{{ $selectedProduct['name'] }}</p>
                        <p class="campaign-text mt-2 text-xl font-black" data-unit-price>৳{{ number_format($initialSubtotal / $initialSelection['quantity'], 2) }}</p>
                        <p class="mt-1 text-xs text-slate-500">প্রতি পিস/সেট</p>
                    </div>
                </div>
                <div data-variation-field @class(['mt-5', 'hidden' => ! $selectedProduct['variable']])>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label for="campaign-variation" class="block text-sm font-bold">সাইজ/ডিজাইন/অপশন</label>
                        <span data-selected-variation-label class="text-xs font-bold text-slate-500">{{ $selectedVariation['label'] ?? '' }}</span>
                    </div>
                    <select id="campaign-variation" name="product_variation_id" @disabled(! $selectedProduct['variable']) @required($selectedProduct['variable']) class="sr-only">
                        @foreach($selectedProduct['variations'] as $variation)
                            <option value="{{ $variation['id'] }}" @selected($initialSelection['product_variation_id'] === $variation['id']) @disabled($variation['available'] < 1)>{{ $variation['label'] }} — ৳{{ number_format($variation['price'], 2) }}{{ $variation['available'] < 1 ? ' (স্টক নেই)' : '' }}</option>
                        @endforeach
                    </select>
                    <div data-variation-options class="mt-3 flex gap-3 overflow-x-auto px-1 pb-3 pt-1" role="group" aria-label="পণ্যের ভ্যারিয়েশন নির্বাচন করুন">
                        @foreach($selectedProduct['variations'] as $variation)
                            <button
                                type="button"
                                data-variation-option="{{ $variation['id'] }}"
                                aria-pressed="{{ $initialSelection['product_variation_id'] === $variation['id'] ? 'true' : 'false' }}"
                                @disabled($variation['available'] < 1)
                                class="campaign-variation-option relative min-w-44 max-w-56 shrink-0 overflow-hidden rounded-2xl border-2 border-slate-200 bg-white p-3 text-left transition hover:border-slate-400 disabled:cursor-not-allowed disabled:opacity-45"
                            >
                                <span class="campaign-variation-check absolute right-1.5 top-1.5 hidden size-6 place-items-center rounded-full text-xs font-black text-white">✓</span>
                                @if($variation['image'])
                                    <img src="{{ $variation['image'] }}" alt="{{ $variation['label'] }}" loading="lazy" class="mb-2 aspect-square w-20 rounded-xl bg-slate-50 object-cover">
                                @endif
                                <span class="block break-words text-xs font-bold leading-5">{{ $variation['label'] }}</span>
                                <span class="campaign-text mt-1 block text-sm font-black">৳{{ number_format($variation['price'], 2) }}</span>
                                @if($variation['available'] < 1)<span class="mt-1 block text-[10px] font-bold text-rose-600">স্টক নেই</span>@endif
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                    <label for="campaign-quantity" class="text-sm font-bold">পরিমাণ</label>
                    <div class="flex items-center overflow-hidden rounded-xl border border-slate-200">
                        <button type="button" data-quantity-step="-1" class="h-12 w-12 bg-slate-50 text-xl font-bold" aria-label="পরিমাণ কমান">−</button>
                        <input id="campaign-quantity" name="quantity" type="number" min="1" max="99" value="{{ $initialSelection['quantity'] }}" required class="h-12 w-16 border-x border-slate-200 text-center font-bold">
                        <button type="button" data-quantity-step="1" class="h-12 w-12 bg-slate-50 text-xl font-bold" aria-label="পরিমাণ বাড়ান">+</button>
                    </div>
                </div>
                <p data-quote-status role="status" aria-live="polite" class="mt-4 text-sm text-rose-700">{{ $checkoutError ?: ($regions->isEmpty() ? 'নির্বাচিত পণ্যের জন্য কোনো সক্রিয় ডেলিভারি রেট নেই।' : '') }}</p>
                <button type="button" data-quote-retry hidden class="mt-2 text-sm font-bold underline">আবার হিসাব যাচাই করুন</button>
                @php($selectedQuote = $regions->get((int) old('shipping_region_id')))
                <dl class="mt-5 space-y-4 border-t border-slate-100 pt-5 text-sm" aria-live="polite">
                    <div class="flex justify-between gap-3"><dt>পণ্যের মোট</dt><dd data-campaign-subtotal class="font-bold">৳{{ number_format($initialSubtotal, 2) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>ডেলিভারি চার্জ</dt><dd data-campaign-shipping class="text-right font-bold">{{ $selectedQuote ? '৳'.number_format($selectedQuote['amount'], 2) : 'উপজেলা/থানা নির্বাচন করুন' }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-slate-100 pt-4 text-lg font-black"><dt>সর্বমোট</dt><dd data-campaign-total class="campaign-text">{{ $selectedQuote ? '৳'.number_format($initialSubtotal + $selectedQuote['amount'], 2) : 'এলাকা নির্বাচন করুন' }}</dd></div>
                </dl>
                <p class="mt-5 rounded-xl bg-emerald-50 p-4 text-sm font-bold text-emerald-700">ক্যাশ অন ডেলিভারি — পণ্য হাতে পেয়ে মূল্য পরিশোধ করুন।</p>
                <p class="mt-4 text-xs leading-5 text-slate-500">এই অফারের অর্ডার আলাদাভাবে হবে। আপনার সাধারণ শপিং কার্টের পণ্য অপরিবর্তিত থাকবে।</p>
            </div>

            <div class="rounded-3xl border border-orange-100 bg-white p-5 shadow-lg sm:p-8">
                <h3 class="text-xl font-black">২. আপনার ডেলিভারির তথ্য</h3>
                <div class="mt-6 space-y-4">
                    <label class="block text-sm font-bold">আপনার নাম <span class="text-rose-600">*</span>
                        <input name="customer_name" value="{{ old('customer_name', auth()->user()?->name) }}" required maxlength="255" autocomplete="name" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-normal">
                    </label>
                    <label class="block text-sm font-bold">মোবাইল নম্বর <span class="text-rose-600">*</span>
                        <input name="customer_phone" type="tel" value="{{ old('customer_phone', auth()->user()?->phone) }}" required maxlength="32" autocomplete="tel" placeholder="01XXXXXXXXX" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-normal">
                    </label>
                    <label class="block text-sm font-bold">সম্পূর্ণ ঠিকানা <span class="text-rose-600">*</span>
                        <textarea name="address_line" required maxlength="500" autocomplete="street-address" rows="3" placeholder="বাসা/রোড/গ্রাম/এলাকার নাম" class="mt-2 w-full rounded-xl border border-slate-200 p-4 font-normal">{{ old('address_line') }}</textarea>
                    </label>
                    <label class="block text-sm font-bold">উপজেলা/থানা <span class="text-rose-600">*</span>
                        <select name="shipping_region_id" required class="mt-2 min-h-12 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-normal">
                            <option value="">বিভাগ › জেলা › উপজেলা/থানা নির্বাচন করুন</option>
                            @foreach($regions as $regionId => $region)
                                <option value="{{ $regionId }}" @selected((string) old('shipping_region_id') === (string) $regionId)>{{ $region['name'] }} — ৳{{ number_format($region['amount'], 2) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-bold">ইমেইল (ঐচ্ছিক)
                        <input name="customer_email" type="email" value="{{ old('customer_email', auth()->user()?->email) }}" autocomplete="email" maxlength="255" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-normal">
                    </label>
                    <label class="block text-sm font-bold">অর্ডার নোট (ঐচ্ছিক)
                        <textarea name="notes" rows="2" maxlength="1000" class="mt-2 w-full rounded-xl border border-slate-200 p-3 font-normal">{{ old('notes') }}</textarea>
                    </label>
                </div>
                <input type="hidden" name="payment_method" value="cod">
                <label class="mt-5 flex items-start gap-3 text-xs leading-6 text-slate-600">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms')) required class="mt-1 size-4 shrink-0 accent-orange-500">
                    <span>আমি অর্ডার, ডেলিভারি ও রিটার্ন সংক্রান্ত শর্তাবলিতে সম্মত।</span>
                </label>
                @if($preview)<p class="mt-4 text-sm font-bold text-amber-700">প্রিভিউতে অর্ডার বন্ধ আছে। পেজ প্রকাশ করার পরে অর্ডার নেওয়া যাবে।</p>@endif
                <button type="submit" data-campaign-submit @disabled($preview) class="campaign-bg mt-5 min-h-14 w-full rounded-2xl px-5 py-4 text-base font-black text-white shadow-lg disabled:cursor-not-allowed disabled:opacity-50">অর্ডার নিশ্চিত করুন</button>
                <p class="mt-3 text-center text-xs text-slate-500">অ্যাকাউন্ট ছাড়াই অর্ডার করতে পারবেন।</p>
            </div>
        </form>
        @php($campaignPayload = ['products' => $checkoutProducts, 'regions' => $regions->values(), 'subtotal' => $initialSubtotal, 'error' => $checkoutError])
        <script type="application/json" id="campaign-checkout-data">@json($campaignPayload)</script>
    </div>
</section>
