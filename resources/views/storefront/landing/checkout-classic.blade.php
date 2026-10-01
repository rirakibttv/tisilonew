@php
    $selectedProduct = collect($checkoutProducts)->firstWhere('id', $initialSelection['product_id']);
    $selectedVariation = collect($selectedProduct['variations'])->firstWhere('id', $initialSelection['product_variation_id']);
    $selectedQuote = $regions->get((int) old('shipping_region_id'));
    $districtRegions = $regions->unique(fn (array $quote): string => mb_strtolower(trim((string) $quote['district'])))->values();
@endphp

<section id="order-now" class="storefront-shell scroll-mt-5 py-8 pb-28 md:pb-10">
    <div class="campaign-green-border rounded-md border-4 bg-white p-2 shadow-sm sm:p-3">
        <h2 class="campaign-green-bg px-4 py-3 text-center text-lg font-black text-white sm:text-2xl">{{ __('This offer is available for a limited time, so order before it ends.') }}</h2>

        @if($errors->any())
            <div role="alert" class="mx-2 mt-4 rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm text-rose-700">
                <p class="font-black">{{ __('The order was not submitted. Please correct the information below:') }}</p>
                <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('store.landing.order', $landingPage) }}" class="mt-3 grid items-start gap-3 lg:grid-cols-2" data-campaign-checkout data-quote-url="{{ route('store.landing.quote', $landingPage) }}" data-preview="{{ $preview ? '1' : '0' }}">
            @csrf
            <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">

            <div class="border border-slate-200 bg-white p-3 sm:p-4">
                <h3 class="border-b border-slate-200 pb-2 text-sm font-semibold">{{ __('Product Details') }}</h3>

                @if(count($checkoutProducts) > 1)
                    <label for="campaign-product" class="mt-3 block text-xs font-bold">{{ __('Select Product') }}</label>
                    <select id="campaign-product" name="product_id" required class="mt-1 h-11 w-full border border-slate-200 bg-white px-3 text-sm">
                        @foreach($checkoutProducts as $product)
                            <option value="{{ $product['id'] }}" @selected($initialSelection['product_id'] === $product['id'])>{{ $product['name'] }}</option>
                        @endforeach
                    </select>
                @else
                    <input id="campaign-product" type="hidden" name="product_id" value="{{ $selectedProduct['id'] }}">
                @endif

                <div class="mt-3 grid grid-cols-[minmax(0,1fr)_92px_88px] border border-slate-200 text-xs font-bold text-slate-700">
                    <div class="border-r border-slate-200 px-3 py-2">{{ __('Product') }}</div>
                    <div class="border-r border-slate-200 px-3 py-2 text-center">{{ __('Quantity') }}</div>
                    <div class="px-3 py-2 text-right">{{ __('Price') }}</div>
                </div>
                <div class="grid min-h-24 grid-cols-[minmax(0,1fr)_92px_88px] items-center bg-slate-50 text-xs">
                    <div class="flex min-w-0 items-center gap-2 border-r border-white px-3 py-3">
                        <img data-campaign-image src="{{ $selectedProduct['image'] ?: '' }}" alt="{{ $selectedProduct['name'] }}" @class(['size-12 shrink-0 object-cover', 'hidden' => ! $selectedProduct['image']])>
                        <span data-campaign-name class="line-clamp-2 font-semibold">{{ $selectedProduct['name'] }}</span>
                    </div>
                    <div class="flex items-center justify-center border-r border-white px-1">
                        <button type="button" data-quantity-step="-1" class="grid size-8 place-items-center border border-slate-200 bg-white text-base font-black" aria-label="{{ __('Decrease quantity') }}">−</button>
                        <input id="campaign-quantity" name="quantity" type="number" min="1" max="99" value="{{ $initialSelection['quantity'] }}" required class="h-8 w-8 border-y border-slate-200 bg-white text-center font-bold">
                        <button type="button" data-quantity-step="1" class="grid size-8 place-items-center border border-slate-200 bg-white text-base font-black" aria-label="{{ __('Increase quantity') }}">+</button>
                    </div>
                    <div data-unit-price class="px-3 text-right font-bold">৳{{ number_format($initialSubtotal / $initialSelection['quantity'], 2) }}</div>
                </div>

                <div data-variation-field @class(['mt-4', 'hidden' => ! $selectedProduct['variable']])>
                    <div class="flex items-center justify-between gap-2"><label for="campaign-variation" class="text-xs font-bold">{{ __('Variation') }}</label><span data-selected-variation-label class="text-[11px] text-slate-500">{{ $selectedVariation['label'] ?? '' }}</span></div>
                    <select id="campaign-variation" name="product_variation_id" @disabled(! $selectedProduct['variable']) @required($selectedProduct['variable']) class="sr-only">
                        @foreach($selectedProduct['variations'] as $variation)
                            <option value="{{ $variation['id'] }}" @selected($initialSelection['product_variation_id'] === $variation['id']) @disabled($variation['available'] < 1)>{{ $variation['label'] }} — ৳{{ number_format($variation['price'], 2) }}</option>
                        @endforeach
                    </select>
                    <div data-variation-options class="mt-2 grid grid-cols-1 gap-2 pb-2" role="group" aria-label="{{ __('Select Product Variation') }}">
                        @foreach($selectedProduct['variations'] as $variation)
                            <button type="button" data-variation-option="{{ $variation['id'] }}" aria-pressed="{{ $initialSelection['product_variation_id'] === $variation['id'] ? 'true' : 'false' }}" @disabled($variation['available'] < 1) class="campaign-variation-option relative flex w-full items-center gap-3 rounded-lg border-2 border-slate-200 bg-white p-2 text-left disabled:opacity-40">
                                <span class="campaign-variation-check absolute right-1 top-1 hidden size-5 place-items-center rounded-full text-[10px] font-black text-white">✓</span>
                                @if($variation['image'])<img src="{{ $variation['image'] }}" alt="{{ $variation['label'] }}" loading="lazy" class="size-14 shrink-0 object-cover">@endif
                                <span class="min-w-0 flex-1 text-[11px] font-bold leading-4">{{ $variation['label'] }}</span>
                                <span class="campaign-text shrink-0 pr-7 text-xs font-black">৳{{ number_format($variation['price'], 2) }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <p data-quote-status role="status" aria-live="polite" class="mt-3 text-xs text-rose-700">{{ $checkoutError ?: ($regions->isEmpty() ? __('No active delivery rate is available for the selected product.') : '') }}</p>
                <button type="button" data-quote-retry hidden class="mt-2 text-xs font-bold underline">{{ __('Recheck the Calculation') }}</button>

                <dl class="mt-3 border border-slate-200 text-sm" aria-live="polite">
                    <div class="flex justify-between border-b border-slate-200 px-3 py-2"><dt>{{ __('Total') }}</dt><dd data-campaign-subtotal class="font-bold">৳{{ number_format($initialSubtotal, 2) }}</dd></div>
                    <div class="flex justify-between border-b border-slate-200 px-3 py-2"><dt>{{ __('Delivery Charge') }}</dt><dd data-campaign-shipping class="text-right font-bold">{{ $selectedQuote ? '৳'.number_format($selectedQuote['amount'], 2) : __('Select District') }}</dd></div>
                    <div class="flex justify-between px-3 py-2 font-black"><dt>{{ __('Grand Total') }}</dt><dd data-campaign-total class="campaign-green-text">{{ $selectedQuote ? '৳'.number_format($initialSubtotal + $selectedQuote['amount'], 2) : __('Select District') }}</dd></div>
                </dl>
            </div>

            <div class="border border-slate-200 bg-white p-3 sm:p-4">
                <p class="border-b border-slate-200 pb-2 text-center text-sm text-slate-500">{{ __('Note') }}</p>
                <h3 class="mt-3 text-sm font-black">{{ __('Enter Your Information') }}</h3>
                <div class="mt-3 space-y-3">
                    <label class="block text-xs font-bold">{{ __('Your Name') }} <span class="text-rose-600">*</span><input name="customer_name" value="{{ old('customer_name', auth()->user()?->name) }}" required maxlength="255" autocomplete="name" placeholder="{{ __('Name') }}" class="mt-1 h-11 w-full border border-slate-200 px-3 font-normal outline-none focus:border-green-600"></label>
                    <label class="block text-xs font-bold">{{ __('Mobile Number') }} <span class="text-rose-600">*</span><input name="customer_phone" type="tel" value="{{ old('customer_phone', auth()->user()?->phone) }}" required maxlength="32" autocomplete="tel" placeholder="{{ __('Within 18 digits') }}" class="mt-1 h-11 w-full border border-slate-200 px-3 font-normal outline-none focus:border-green-600"></label>
                    <label class="block text-xs font-bold">{{ __('Full Address') }} <span class="text-rose-600">*</span><textarea name="address_line" required maxlength="500" autocomplete="street-address" rows="2" placeholder="{{ __('Village, road, house') }}" class="mt-1 w-full border border-slate-200 p-3 font-normal outline-none focus:border-green-600">{{ old('address_line') }}</textarea></label>

                    <div class="relative" data-district-combobox>
                        <label for="campaign-district" class="block text-xs font-bold">{{ __('District') }} <span class="text-rose-600">*</span></label>
                        <input id="campaign-district" name="district_search" type="text" value="{{ old('district_search', $selectedQuote['district'] ?? '') }}" required autocomplete="off" placeholder="{{ __('Type and select a district') }}" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="campaign-district-options" data-district-search class="mt-1 h-11 w-full border border-slate-200 px-3 font-normal outline-none focus:border-green-600">
                        <input type="hidden" name="shipping_region_id" value="{{ old('shipping_region_id') }}" data-shipping-region>
                        <div id="campaign-district-options" data-district-options role="listbox" class="absolute inset-x-0 top-full z-30 mt-1 hidden max-h-60 overflow-y-auto border border-slate-200 bg-white p-1 shadow-xl">
                            @foreach($districtRegions as $quote)
                                <button type="button" role="option" data-region-option data-region-id="{{ $quote['region_id'] }}" data-region-district="{{ $quote['district'] }}" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-green-50"><span class="font-semibold">{{ $quote['district'] }}</span><span class="text-xs text-slate-500">৳{{ number_format($quote['amount'], 2) }}</span></button>
                            @endforeach
                            <p data-district-empty class="hidden px-3 py-3 text-xs text-rose-600">{{ __('No delivery rate was found for this district.') }}</p>
                        </div>
                    </div>

                    <label class="block text-xs font-bold">{{ __('Thana / Upazila') }} <span class="text-rose-600">*</span><input name="thana" value="{{ old('thana') }}" required maxlength="120" autocomplete="address-level3" placeholder="{{ __('Thana or Upazila Name') }}" class="mt-1 h-11 w-full border border-slate-200 px-3 font-normal outline-none focus:border-green-600"><span class="mt-1 block text-[10px] font-normal leading-4 text-slate-500">{{ __('Enter it to confirm the delivery location; the charge is calculated by district.') }}</span></label>
                    @include('storefront.partials.payment-methods')
                    <label class="block text-xs font-bold">{{ __('Order Note (Optional)') }}<textarea name="notes" rows="2" maxlength="1000" class="mt-1 w-full border border-slate-200 p-3 font-normal outline-none focus:border-green-600">{{ old('notes') }}</textarea></label>
                </div>
                <input type="hidden" name="customer_email" value="{{ old('customer_email', auth()->user()?->email) }}">
                @if($preview)<p class="mt-3 text-xs font-bold text-amber-700">{{ __('Orders are disabled in preview.') }}</p>@endif
                <button type="submit" data-campaign-submit @disabled($preview) class="campaign-green-bg mt-3 min-h-12 w-full rounded px-5 py-3 text-sm font-black text-white shadow disabled:opacity-50">{{ __('Complete Order') }}</button>
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
                    'confirmOrder' => __('Complete Order'),
                ],
            ];
        @endphp
        <script type="application/json" id="campaign-checkout-data">@json($campaignPayload)</script>
    </div>
</section>
