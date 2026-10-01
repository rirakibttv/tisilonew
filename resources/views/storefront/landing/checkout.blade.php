<section id="order-now" class="scroll-mt-6 bg-orange-50 px-4 py-16 pb-28 sm:px-6 md:pb-16">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-2xl text-center">
            <p class="campaign-text text-xs font-black uppercase tracking-[0.2em]">{{ __('Easy and Secure Order') }}</p>
            <h2 class="mt-3 text-3xl font-black sm:text-4xl">{{ __('Complete Your Order on This Page') }}</h2>
            <p class="mt-3 text-sm leading-6 text-slate-600">{{ __('Choose the product and quantity, then enter your information. Delivery charge is added for your selected area—no separate checkout page is needed.') }}</p>
        </div>

        @if($errors->any())
            <div role="alert" class="mx-auto mt-6 max-w-4xl rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700">
                <p class="font-black">{{ __('The order was not submitted. Please correct the information below:') }}</p>
                <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('store.landing.order', $landingPage) }}" class="mt-9 grid items-start gap-6 lg:grid-cols-2" data-campaign-checkout data-quote-url="{{ route('store.landing.quote', $landingPage) }}" data-preview="{{ $preview ? '1' : '0' }}">
            @csrf
            <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">
            <div class="rounded-3xl border border-orange-100 bg-white p-5 shadow-lg sm:p-8">
                <h3 class="text-xl font-black">{{ __('1. Product and Order Summary') }}</h3>
                <label for="campaign-product" class="mt-6 block text-sm font-bold">{{ __('Select Product') }}</label>
                <select id="campaign-product" name="product_id" required class="mt-2 min-h-12 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm">
                    @foreach($checkoutProducts as $product)
                        <option value="{{ $product['id'] }}" @selected($initialSelection['product_id'] === $product['id'])>{{ $product['name'] }}</option>
                    @endforeach
                </select>
                @php
                    $selectedProduct = collect($checkoutProducts)->firstWhere('id', $initialSelection['product_id']);
                    $selectedVariation = collect($selectedProduct['variations'])->firstWhere('id', $initialSelection['product_variation_id']);
                @endphp
                <div class="mt-5 flex items-center gap-4">
                    <img data-campaign-image src="{{ $selectedProduct['image'] ?: '' }}" alt="{{ $selectedProduct['name'] }}" @class(['size-24 rounded-2xl bg-slate-50 object-cover', 'hidden' => ! $selectedProduct['image']])>
                    <div class="min-w-0">
                        <p class="break-words font-bold" data-campaign-name>{{ $selectedProduct['name'] }}</p>
                        <p class="campaign-text mt-2 text-xl font-black" data-unit-price>৳{{ number_format($initialSubtotal / $initialSelection['quantity'], 2) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Per Piece/Set') }}</p>
                    </div>
                </div>
                <div data-variation-field @class(['mt-5', 'hidden' => ! $selectedProduct['variable']])>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label for="campaign-variation" class="block text-sm font-bold">{{ __('Size/Design/Option') }}</label>
                        <span data-selected-variation-label class="text-xs font-bold text-slate-500">{{ $selectedVariation['label'] ?? '' }}</span>
                    </div>
                    <select id="campaign-variation" name="product_variation_id" @disabled(! $selectedProduct['variable']) @required($selectedProduct['variable']) class="sr-only">
                        @foreach($selectedProduct['variations'] as $variation)
                            <option value="{{ $variation['id'] }}" @selected($initialSelection['product_variation_id'] === $variation['id']) @disabled($variation['available'] < 1)>{{ $variation['label'] }} — ৳{{ number_format($variation['price'], 2) }}{{ $variation['available'] < 1 ? ' ('.__('Out of Stock').')' : '' }}</option>
                        @endforeach
                    </select>
                    <div data-variation-options class="mt-3 grid grid-cols-1 gap-3 px-1 pb-3 pt-1" role="group" aria-label="{{ __('Select Product Variation') }}">
                        @foreach($selectedProduct['variations'] as $variation)
                            <button
                                type="button"
                                data-variation-option="{{ $variation['id'] }}"
                                aria-pressed="{{ $initialSelection['product_variation_id'] === $variation['id'] ? 'true' : 'false' }}"
                                @disabled($variation['available'] < 1)
                                class="campaign-variation-option relative flex w-full items-center gap-3 overflow-hidden rounded-2xl border-2 border-slate-200 bg-white p-3 text-left transition hover:border-slate-400 disabled:cursor-not-allowed disabled:opacity-45"
                            >
                                <span class="campaign-variation-check absolute right-1.5 top-1.5 hidden size-6 place-items-center rounded-full text-xs font-black text-white">✓</span>
                                @if($variation['image'])
                                    <img src="{{ $variation['image'] }}" alt="{{ $variation['label'] }}" loading="lazy" class="size-16 shrink-0 rounded-xl bg-slate-50 object-cover">
                                @endif
                                <span class="min-w-0 flex-1 break-words text-xs font-bold leading-5">{{ $variation['label'] }}</span>
                                <span class="campaign-text shrink-0 pr-8 text-sm font-black">৳{{ number_format($variation['price'], 2) }}</span>
                                @if($variation['available'] < 1)<span class="mt-1 block text-[10px] font-bold text-rose-600">{{ __('Out of Stock') }}</span>@endif
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                    <label for="campaign-quantity" class="text-sm font-bold">{{ __('Quantity') }}</label>
                    <div class="flex items-center overflow-hidden rounded-xl border border-slate-200">
                        <button type="button" data-quantity-step="-1" class="h-12 w-12 bg-slate-50 text-xl font-bold" aria-label="{{ __('Decrease quantity') }}">−</button>
                        <input id="campaign-quantity" name="quantity" type="number" min="1" max="99" value="{{ $initialSelection['quantity'] }}" required class="h-12 w-16 border-x border-slate-200 text-center font-bold">
                        <button type="button" data-quantity-step="1" class="h-12 w-12 bg-slate-50 text-xl font-bold" aria-label="{{ __('Increase quantity') }}">+</button>
                    </div>
                </div>
                <p data-quote-status role="status" aria-live="polite" class="mt-4 text-sm text-rose-700">{{ $checkoutError ?: ($regions->isEmpty() ? __('No active delivery rate is available for the selected product.') : '') }}</p>
                <button type="button" data-quote-retry hidden class="mt-2 text-sm font-bold underline">{{ __('Recheck the Calculation') }}</button>
                @php
                    $selectedQuote = $regions->get((int) old('shipping_region_id'));
                @endphp
                <dl class="mt-5 space-y-4 border-t border-slate-100 pt-5 text-sm" aria-live="polite">
                    <div class="flex justify-between gap-3"><dt>{{ __('Product Subtotal') }}</dt><dd data-campaign-subtotal class="font-bold">৳{{ number_format($initialSubtotal, 2) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>{{ __('Delivery Charge') }}</dt><dd data-campaign-shipping class="text-right font-bold">{{ $selectedQuote ? '৳'.number_format($selectedQuote['amount'], 2) : __('Select District') }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-slate-100 pt-4 text-lg font-black"><dt>{{ __('Grand Total') }}</dt><dd data-campaign-total class="campaign-text">{{ $selectedQuote ? '৳'.number_format($initialSubtotal + $selectedQuote['amount'], 2) : __('Select Area') }}</dd></div>
                </dl>
                <div class="mt-5">
                    @include('storefront.partials.payment-methods')
                </div>
                <p class="mt-4 text-xs leading-5 text-slate-500">{{ __('This offer is ordered separately. Products in your regular shopping cart will remain unchanged.') }}</p>
            </div>

            <div class="rounded-3xl border border-orange-100 bg-white p-5 shadow-lg sm:p-8">
                <h3 class="text-xl font-black">{{ __('2. Your Delivery Information') }}</h3>
                <div class="mt-6 space-y-4">
                    <label class="block text-sm font-bold">{{ __('Your Name') }} <span class="text-rose-600">*</span>
                        <input name="customer_name" value="{{ old('customer_name', auth()->user()?->name) }}" required maxlength="255" autocomplete="name" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-normal">
                    </label>
                    <label class="block text-sm font-bold">{{ __('Mobile Number') }} <span class="text-rose-600">*</span>
                        <input name="customer_phone" type="tel" value="{{ old('customer_phone', auth()->user()?->phone) }}" required maxlength="32" autocomplete="tel" placeholder="01XXXXXXXXX" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-normal">
                    </label>
                    <label class="block text-sm font-bold">{{ __('Full Address') }} <span class="text-rose-600">*</span>
                        <textarea name="address_line" required maxlength="500" autocomplete="street-address" rows="3" placeholder="{{ __('House/Road/Village/Area') }}" class="mt-2 w-full rounded-xl border border-slate-200 p-4 font-normal">{{ old('address_line') }}</textarea>
                    </label>
                    <label class="block text-sm font-bold">{{ __('Thana / Upazila') }} <span class="text-rose-600">*</span>
                        <select name="shipping_region_id" required class="mt-2 min-h-12 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-normal">
                            <option value="">{{ __('Select Division › District › Thana/Upazila') }}</option>
                            @foreach($regions as $regionId => $region)
                                <option value="{{ $regionId }}" @selected((string) old('shipping_region_id') === (string) $regionId)>{{ $region['name'] }} — ৳{{ number_format($region['amount'], 2) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-bold">{{ __('Email (Optional)') }}
                        <input name="customer_email" type="email" value="{{ old('customer_email', auth()->user()?->email) }}" autocomplete="email" maxlength="255" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-4 font-normal">
                    </label>
                    <label class="block text-sm font-bold">{{ __('Order Note (Optional)') }}
                        <textarea name="notes" rows="2" maxlength="1000" class="mt-2 w-full rounded-xl border border-slate-200 p-3 font-normal">{{ old('notes') }}</textarea>
                    </label>
                </div>
                @if($preview)<p class="mt-4 text-sm font-bold text-amber-700">{{ __('Orders are disabled in preview. Publish the page to accept orders.') }}</p>@endif
                <button type="submit" data-campaign-submit @disabled($preview) class="campaign-bg mt-5 min-h-14 w-full rounded-2xl px-5 py-4 text-base font-black text-white shadow-lg disabled:cursor-not-allowed disabled:opacity-50">{{ __('Confirm Order') }}</button>
                <p class="mt-3 text-center text-xs text-slate-500">{{ __('You can order without an account.') }}</p>
            </div>
        </form>
        @php
            $campaignPayload = [
                'products' => $checkoutProducts,
                'regions' => $regions->values(),
                'subtotal' => $initialSubtotal,
                'error' => $checkoutError,
                'locale' => app()->getLocale(),
                'messages' => [
                    'selectDistrict' => __('Select District'),
                    'districtUnavailable' => __('No delivery rate was found for this district.'),
                    'outOfStock' => __('Out of Stock'),
                    'quoteFailed' => __('The calculation could not be verified. Please try again.'),
                    'noActiveRate' => __('No active delivery rate is available for the selected product.'),
                    'connectionError' => __('There was a connection problem. Please try again or refresh the page.'),
                    'checking' => __('Checking price and delivery charge…'),
                    'submitting' => __('Submitting Order…'),
                    'confirmOrder' => __('Confirm Order'),
                ],
            ];
        @endphp
        <script type="application/json" id="campaign-checkout-data">@json($campaignPayload)</script>
    </div>
</section>
