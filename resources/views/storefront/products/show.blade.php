@extends('layouts.storefront')

@php
    $productPageTitle = $product->name.' — '.($generalSettings['site_name'] ?? 'Tisilo');
    $productMetaDescription = \App\Support\SeoMetadata::description(
        $product->short_description,
        $product->description,
        $product->name,
    );
@endphp
@section('title', $productPageTitle)
@section('meta_description', $productMetaDescription)

@push('scripts')
    <script>
        if (window.TisiloAnalytics) {
            window.TisiloAnalytics.track('product_view', {
                product_id: {{ $product->getKey() }},
                value: {{ (float) $summary['price'] }},
                metadata: { product_name: @json($product->name), currency: 'BDT' }
            });
        }
    </script>
@endpush

@section('content')
    @php
        $offers = $product->vendorListings->flatMap(fn ($listing) => $listing->items->map(fn ($item) => ['listing' => $listing, 'item' => $item]))
            ->filter(fn ($offer) => $offer['item']->available_quantity > 0 || $offer['item']->backorders_allowed)
            ->sortBy(fn ($offer) => (float) ($offer['item']->sale_price ?? $offer['item']->regular_price));
        $activeVariations = $product->variations->where('status', true)->sortBy([['is_default', 'desc'], ['sort_order', 'asc']])->values();
        $selectedVariation = $activeVariations->first(fn ($variation) => $variation->stock_status !== 'out_of_stock' && $variation->stock_quantity > 0) ?? $activeVariations->first();
        $isFlashSale = (bool) ($summary['is_flash_sale'] ?? false);
        $displayPrice = $isFlashSale
            ? (float) $summary['price']
            : (float) ($selectedVariation?->sale_price ?? $selectedVariation?->regular_price ?? $summary['price']);
        $displayRegularPrice = max(
            (float) ($selectedVariation?->regular_price ?? 0),
            (float) $summary['regular_price'],
        );
        $displayDiscount = $displayRegularPrice > $displayPrice && $displayRegularPrice > 0 ? (int) round((($displayRegularPrice - $displayPrice) / $displayRegularPrice) * 100) : 0;
        $variationOptions = $activeVariations->map(function ($variation) use ($isFlashSale, $summary): array {
            return [
                'id' => $variation->id,
                'label' => $variation->attributeValues->isNotEmpty() ? $variation->attributeValues->map(fn ($value) => $value->attribute->name.': '.$value->value)->join(' · ') : ($variation->sku ?: 'Option '.$variation->id),
                'price' => $isFlashSale ? (float) $summary['price'] : (float) ($variation->sale_price ?? $variation->regular_price),
                'regular_price' => (float) $variation->regular_price,
                'available' => $variation->stock_status === 'out_of_stock' ? 0 : (int) $variation->stock_quantity,
                'image' => $variation->image ? asset('storage/'.ltrim($variation->image, '/')) : null,
                'sku' => $variation->sku,
            ];
        })->values();
        $masterImage = $summary['image'];
        $selectedVariationImage = data_get($variationOptions->firstWhere('id', $selectedVariation?->id), 'image');
        $initialImage = $masterImage ?: $selectedVariationImage;
    @endphp

    <section class="storefront-shell grid items-start gap-7 py-6 lg:grid-cols-[minmax(0,.86fr)_minmax(0,1.04fr)] lg:gap-8">
        <div>
            <nav aria-label="{{ __('Breadcrumb') }}" class="mb-4 text-xs text-slate-500">
                <a href="{{ route('store.home') }}" class="hover:text-orange-600">{{ __('Home') }}</a>
                <span class="mx-2">/</span>
                @if ($product->category)
                    <a href="{{ $product->category->permalink }}" class="hover:text-orange-600">{{ $product->category->name }}</a>
                @else
                    <span>{{ __('Product') }}</span>
                @endif
                <span class="mx-2">/</span>
                <span class="text-slate-800">{{ $product->name }}</span>
            </nav>
            <div class="aspect-square overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-100 via-white to-orange-50" data-product-image-frame>
                <img data-product-main-image src="{{ $initialImage ?: '' }}" alt="{{ $product->name }}" @class(['size-full object-cover transition-opacity duration-200', 'hidden' => ! $initialImage])>
                <div data-product-image-placeholder @class(['size-full place-items-center p-8 text-center', 'grid' => ! $initialImage, 'hidden' => $initialImage])>
                    <div>
                        <span class="mx-auto grid size-32 place-items-center rounded-[2rem] bg-white text-6xl font-black text-orange-500 shadow-lg">{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</span>
                        <p class="mt-6 font-bold text-slate-500">{{ $product->brand?->name ?? 'Tisilo Choice' }}</p>
                    </div>
                </div>
            </div>
            @if($masterImage || $variationOptions->whereNotNull('image')->isNotEmpty())
                <div data-product-gallery-options class="mt-3 flex flex-wrap gap-2" role="group" aria-label="{{ __('Product Images') }}">
                    @if($masterImage)
                        <button type="button" data-product-gallery-master aria-label="{{ $product->name }} — {{ __('Main Image') }}" aria-pressed="true" class="product-gallery-option size-16 overflow-hidden rounded-xl border-2 border-slate-200 bg-white p-1 transition hover:border-orange-300">
                            <img src="{{ $masterImage }}" alt="" loading="lazy" class="size-full rounded-lg object-cover">
                        </button>
                    @endif
                    @foreach($variationOptions->whereNotNull('image') as $option)
                        <button type="button" data-product-gallery-variation="{{ $option['id'] }}" aria-label="{{ $option['label'] }}" aria-pressed="{{ ! $masterImage && $selectedVariation?->id === $option['id'] ? 'true' : 'false' }}" class="product-gallery-option size-16 overflow-hidden rounded-xl border-2 border-slate-200 bg-white p-1 transition hover:border-orange-300">
                            <img src="{{ $option['image'] }}" alt="" loading="lazy" class="size-full rounded-lg object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
            <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs font-bold text-slate-600 sm:gap-3">
                <span class="rounded-xl border border-slate-200 bg-white px-2 py-3">✓ {{ __('Authentic Product') }}</span>
                <span class="rounded-xl border border-slate-200 bg-white px-2 py-3">↻ {{ __('Easy Returns') }}</span>
                <span class="rounded-xl border border-slate-200 bg-white px-2 py-3">🔒 {{ __('Secure Payment') }}</span>
            </div>
        </div>

        <div class="lg:pt-1">
            <h1 class="text-3xl font-black leading-tight text-slate-950 sm:text-4xl">{{ $product->name }}</h1>
            <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                <span class="font-bold text-amber-500">★ {{ number_format($summary['review_rating'], 1) }} <span class="font-medium text-slate-400">{{ __('(:count reviews)', ['count' => $summary['review_count']]) }}</span></span>
                <span class="text-slate-300">|</span>
                <span class="text-slate-500">{{ __('SKU') }}: <span data-product-sku>{{ $selectedVariation?->sku ?: ($product->sku ?: __('N/A')) }}</span></span>
                <span data-product-stock class="{{ ($selectedVariation ? $selectedVariation->stock_quantity > 0 : $summary['available'] > 0) ? 'text-emerald-600' : 'text-amber-600' }} font-bold">{{ __(($selectedVariation ? $selectedVariation->stock_quantity > 0 : $summary['available'] > 0) ? 'In Stock' : 'Out of Stock') }}</span>
            </div>

            <div class="mt-6 rounded-2xl bg-orange-50 px-5 py-4">
                @if($isFlashSale)
                    <span class="mb-2 inline-flex rounded-full bg-rose-600 px-2.5 py-1 text-xs font-black uppercase tracking-wide text-white">{{ __('Flash Sale') }}</span>
                @endif
                <div class="flex items-end gap-3">
                    <span data-product-price class="text-4xl font-black text-orange-600">৳{{ number_format($displayPrice, 0) }}</span>
                    <span data-product-regular-price @class(['pb-1 text-lg text-slate-400 line-through', 'hidden' => $displayRegularPrice <= $displayPrice])>৳{{ number_format($displayRegularPrice, 0) }}</span>
                    <span data-product-discount @class(['mb-1 rounded-full bg-rose-500 px-2.5 py-1 text-xs font-bold text-white', 'hidden' => $displayDiscount < 1])>{{ __(':amount% off', ['amount' => $displayDiscount]) }}</span>
                </div>
            </div>

            @if ($product->short_description)
                <div class="mt-4">
                    <h2 class="text-sm font-medium text-slate-950">{{ __('Quick Overview') }}</h2>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $product->short_description }}</p>
                </div>
            @endif

            @if ($offers->isNotEmpty())
                <div class="mt-7">
                    <h2 class="text-sm font-black text-slate-900">{{ __('Choose a Seller') }}</h2>
                    <div class="mt-3 space-y-3">
                        @foreach ($offers as $offer)
                            @php
                                $offerItem = $offer['item'];
                            @endphp
                            <form method="POST" action="{{ route('store.cart.store') }}" class="flex flex-wrap items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-orange-300">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="hidden" name="vendor_listing_item_id" value="{{ $offerItem->id }}">
                                <div class="min-w-0 flex-1">
                                    <p class="font-black text-slate-900">{{ $offer['listing']->vendor->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $offerItem->seller_sku }} · {{ __(':count in stock', ['count' => $offerItem->available_quantity]) }}</p>
                                </div>
                                <span class="text-lg font-black text-orange-600">৳{{ number_format($isFlashSale ? $summary['price'] : (float) ($offerItem->sale_price ?? $offerItem->regular_price), 0) }}</span>
                                <input type="number" name="quantity" value="1" min="1" max="99" class="h-11 w-20 rounded-xl border border-slate-200 px-3 text-center">
                                <button class="h-11 rounded-xl bg-orange-500 px-5 text-sm font-black text-white hover:bg-orange-600">{{ __('Add to Cart') }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @else
                <form method="POST" action="{{ route('store.cart.store') }}" class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-product-variation-form>
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">

                    @if ($product->product_type === 'variable' && $activeVariations->isNotEmpty())
                        <label for="product-variation" data-product-variation-label class="block text-xs font-medium text-slate-600">{{ $variationOptions->firstWhere('id', $selectedVariation?->id)['label'] ?? __('Select Variation') }}</label>
                        <select id="product-variation" name="product_variation_id" required class="sr-only">
                            @foreach ($activeVariations as $variation)
                                <option value="{{ $variation->id }}" @selected($selectedVariation?->id === $variation->id) @disabled($variation->stock_status === 'out_of_stock' || $variation->stock_quantity < 1)>
                                    {{ $variation->attributeValues->isNotEmpty() ? $variation->attributeValues->map(fn ($value) => $value->attribute->name.': '.$value->value)->join(' · ') : 'Option '.$loop->iteration }}
                                    — ৳{{ number_format($isFlashSale ? $summary['price'] : (float) ($variation->sale_price ?? $variation->regular_price), 0) }}
                                </option>
                            @endforeach
                        </select>
                        <div data-product-variation-options class="mt-3 flex flex-wrap gap-2" role="group" aria-label="{{ __('Select Product Variation') }}">
                            @foreach($variationOptions as $option)
                                <button type="button" data-product-variation-option="{{ $option['id'] }}" aria-label="{{ $option['label'] }} — ৳{{ number_format($option['price'], 0) }}" aria-pressed="{{ $selectedVariation?->id === $option['id'] ? 'true' : 'false' }}" @disabled($option['available'] < 1) @class(['product-variation-option relative flex max-w-full items-center rounded-xl border-2 border-slate-200 bg-white text-left transition hover:border-orange-300 disabled:cursor-not-allowed disabled:opacity-45', 'size-20 p-1.5' => $option['image'], 'min-h-16 min-w-36 px-3 py-2' => ! $option['image']])>
                                    @if($option['image'])
                                        <img src="{{ $option['image'] }}" alt="" loading="lazy" class="size-full rounded-lg bg-slate-50 object-cover"><span class="sr-only">{{ $option['label'] }}</span>
                                    @else
                                        <span class="min-w-0"><span class="block break-words text-xs font-bold leading-5">{{ $option['label'] }}</span><span class="mt-1 block text-sm font-black text-orange-600">৳{{ number_format($option['price'], 0) }}</span></span>
                                    @endif
                                    <span class="product-variation-check absolute right-1.5 top-1.5 hidden size-5 place-items-center rounded-full bg-orange-500 text-[10px] font-black text-white">✓</span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-4">
                        <p class="text-xs font-black text-slate-900">{{ __('Quantity') }}</p>
                        <div class="mt-1 inline-flex h-9 items-center overflow-hidden rounded-lg border border-slate-200 bg-white">
                            <button type="button" data-product-quantity-step="-1" aria-label="{{ __('Decrease quantity') }}" class="grid h-full w-9 place-items-center text-base font-bold text-slate-500 hover:bg-slate-50">−</button>
                            <input data-product-quantity type="number" name="quantity" value="1" min="1" max="{{ max(1, (int) ($selectedVariation?->stock_quantity ?? 99)) }}" aria-label="{{ __('Quantity') }}" class="h-full w-10 border-x border-slate-200 text-center text-xs font-black [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                            <button type="button" data-product-quantity-step="1" aria-label="{{ __('Increase quantity') }}" class="grid h-full w-9 place-items-center text-base font-bold text-slate-500 hover:bg-slate-50">+</button>
                        </div>
                        <p data-product-stock-left class="mt-1 text-[11px] font-medium text-slate-600">{{ __('Only :count left', ['count' => (int) ($selectedVariation?->stock_quantity ?? $summary['available'])]) }}</p>
                    </div>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <button type="submit" name="redirect_to" value="cart" data-product-cart-button @disabled($selectedVariation && $selectedVariation->stock_quantity < 1) class="h-12 rounded-xl bg-orange-500 px-4 text-sm font-black text-white shadow-lg shadow-orange-500/20 hover:bg-orange-600 disabled:cursor-not-allowed disabled:opacity-50">@svg('heroicon-o-shopping-cart', 'mr-2 inline size-5') {{ __('Add to Cart') }}</button>
                        <button type="submit" name="redirect_to" value="checkout" data-product-cart-button @disabled($selectedVariation && $selectedVariation->stock_quantity < 1) class="h-12 rounded-xl bg-orange-500 px-4 text-sm font-black text-white shadow-lg shadow-orange-500/20 hover:bg-orange-600 disabled:cursor-not-allowed disabled:opacity-50">@svg('heroicon-o-shopping-cart', 'mr-2 inline size-5') {{ __('Order Now') }}</button>
                    </div>
                    @error('quantity')<p class="mt-3 text-sm font-bold text-rose-600">{{ $message }}</p>@enderror
                </form>
                @if($variationOptions->isNotEmpty())
                    @php
                        $variationPayload = [
                            'masterImage' => $masterImage,
                            'productName' => $product->name,
                            'variations' => $variationOptions,
                            'locale' => app()->getLocale(),
                            'messages' => [
                                'discount' => __(':amount% off', ['amount' => '__AMOUNT__']),
                                'inStock' => __('In Stock'),
                                'outOfStock' => __('Out of Stock'),
                                'notAvailable' => __('N/A'),
                                'onlyLeft' => __('Only :count left', ['count' => '__COUNT__']),
                            ],
                        ];
                    @endphp
                    <script type="application/json" id="product-variation-data">@json($variationPayload)</script>
                @endif
            @endif

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">{{ __('Delivery') }}</p><p class="mt-1 text-sm font-black">{{ __('2–5 business days nationwide') }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">{{ __('Payment') }}</p><p class="mt-1 text-sm font-black">{{ __('COD, bKash, Nagad and Card') }}</p></div>
            </div>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-white py-12">
        <div class="storefront-shell">
            <h2 class="text-2xl font-black text-slate-950">{{ __('Product Details') }}</h2>
            <div class="prose prose-slate mt-5 max-w-none text-sm leading-7 text-slate-600">
                @if ($product->description)
                    {!! $product->description !!}
                @else
                    <p>{{ __('Product details will be added soon.') }}</p>
                @endif
            </div>
        </div>
    </section>

    <section id="product-reviews" class="storefront-shell scroll-mt-24 py-12">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.35fr)_minmax(320px,.65fr)]">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-100 pb-5">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[.18em] text-orange-500">{{ __('Verified Customer Feedback') }}</p>
                        <h2 class="mt-2 text-2xl font-black text-slate-950">{{ __('Customer Reviews') }}</h2>
                    </div>
                    <div class="text-right">
                        <p class="text-3xl font-black text-slate-950">{{ number_format($summary['review_rating'], 1) }}<span class="text-base text-slate-400">/5</span></p>
                        <p class="text-sm font-bold text-amber-500">★★★★★ <span class="font-medium text-slate-500">{{ __('(:count reviews)', ['count' => $summary['review_count']]) }}</span></p>
                    </div>
                </div>

                @if ($product->approvedReviews->isNotEmpty())
                    <div class="divide-y divide-slate-100">
                        @foreach ($product->approvedReviews as $review)
                            <article class="py-5 first:pt-6 last:pb-0">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p class="font-black text-slate-900">{{ $review->reviewer_name }}</p>
                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                            <span class="font-black text-amber-500">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                                            @if ($review->is_verified_purchase)
                                                <span class="rounded-full bg-emerald-50 px-2 py-1 font-bold text-emerald-700">✓ {{ __('Verified Purchase') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <time class="text-xs text-slate-400" datetime="{{ ($review->published_at ?? $review->created_at)->toAtomString() }}">{{ ($review->published_at ?? $review->created_at)->format('d M Y') }}</time>
                                </div>
                                @if ($review->title)
                                    <h3 class="mt-3 font-black text-slate-900">{{ $review->title }}</h3>
                                @endif
                                <p class="mt-2 whitespace-pre-line text-sm leading-7 text-slate-600">{{ $review->review }}</p>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="py-12 text-center">
                        <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-orange-50 text-2xl text-orange-500">★</span>
                        <p class="mt-4 font-black text-slate-900">{{ __('No published reviews yet') }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Buy this product and share the first experience.') }}</p>
                    </div>
                @endif
            </div>

            <aside class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <h2 class="text-xl font-black text-slate-950">{{ __('Write Your Review') }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('Only customers who bought and received this product can submit a review.') }}</p>

                @if (session('review_submitted'))
                    <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-700">{{ session('review_submitted') }}</div>
                @endif

                @guest
                    <a href="{{ route('store.account.login') }}" class="mt-5 flex h-12 items-center justify-center rounded-xl bg-orange-500 px-5 text-sm font-black text-white hover:bg-orange-600">{{ __('Log in to Review') }}</a>
                @else
                    @if (auth()->user()->role === \App\Enums\UserRole::Customer)
                        @if ($viewerReview && $viewerReview->status !== \App\Models\ProductReview::STATUS_REJECTED)
                            <div @class([
                                'mt-5 rounded-2xl border p-4 text-sm font-bold',
                                'border-emerald-200 bg-emerald-50 text-emerald-700' => $viewerReview->status === \App\Models\ProductReview::STATUS_APPROVED,
                                'border-amber-200 bg-amber-50 text-amber-700' => $viewerReview->status === \App\Models\ProductReview::STATUS_PENDING,
                            ])>
                                {{ $viewerReview->status === \App\Models\ProductReview::STATUS_APPROVED
                                    ? __('Your review has been published.')
                                    : __('Your review is awaiting approval.') }}
                            </div>
                        @elseif ($canSubmitReview)
                            @if ($viewerReview?->status === \App\Models\ProductReview::STATUS_REJECTED)
                                <div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-700">{{ __('The review was not approved. You can edit and resubmit it.') }}</div>
                            @endif
                            <form method="POST" action="{{ route('store.products.reviews.store', $product) }}" class="mt-5 space-y-4">
                                @csrf
                                <div>
                                    <label for="review-rating" class="text-sm font-black text-slate-800">{{ __('Rating') }}</label>
                                    <select id="review-rating" name="rating" required class="mt-2 h-12 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm focus:border-orange-400 focus:ring-orange-400">
                                        <option value="">{{ __('Select Rating') }}</option>
                                        @foreach ([5 => ['★★★★★', 'Excellent'], 4 => ['★★★★☆', 'Very Good'], 3 => ['★★★☆☆', 'Good'], 2 => ['★★☆☆☆', 'Average'], 1 => ['★☆☆☆☆', 'Poor']] as $value => [$stars, $label])
                                            <option value="{{ $value }}" @selected((int) old('rating', $viewerReview?->rating) === $value)>{{ $stars }} — {{ __($label) }}</option>
                                        @endforeach
                                    </select>
                                    @error('rating')<p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="review-title" class="text-sm font-black text-slate-800">{{ __('Title') }} <span class="font-medium text-slate-400">({{ __('Optional') }})</span></label>
                                    <input id="review-title" name="title" value="{{ old('title', $viewerReview?->title) }}" maxlength="150" class="mt-2 h-12 w-full rounded-xl border border-slate-200 px-3 text-sm focus:border-orange-400 focus:ring-orange-400" placeholder="{{ __('Your experience in a few words') }}">
                                    @error('title')<p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="review-body" class="text-sm font-black text-slate-800">{{ __('Review') }}</label>
                                    <textarea id="review-body" name="review" required minlength="10" maxlength="3000" rows="5" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-3 text-sm focus:border-orange-400 focus:ring-orange-400" placeholder="{{ __('Write about your experience using this product') }}">{{ old('review', $viewerReview?->review) }}</textarea>
                                    @error('review')<p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
                                </div>
                                <button type="submit" class="h-12 w-full rounded-xl bg-orange-500 px-5 text-sm font-black text-white shadow-lg shadow-orange-500/20 hover:bg-orange-600">{{ __('Submit Review') }}</button>
                            </form>
                        @elseif (! $hasDeliveredPurchase)
                            <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold leading-6 text-slate-600">{{ __('No delivered order for this product was found in your account. The review form will appear after delivery.') }}</div>
                        @endif
                    @else
                        <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold leading-6 text-slate-600">{{ __('Product reviews can only be submitted from a customer account.') }}</div>
                    @endif
                @endguest
            </aside>
        </div>
    </section>

    @if ($related->isNotEmpty())
        <section class="storefront-shell py-14">
            <h2 class="text-2xl font-black text-slate-950">{{ __('Related Products') }}</h2>
            <div data-product-grid class="storefront-product-grid mt-7 grid-cols-2 md:grid-cols-3 lg:grid-cols-5">
                @foreach ($related as $card)
                    @include('storefront.components.product-card', ['card' => $card])
                @endforeach
            </div>
        </section>
    @endif
@endsection
