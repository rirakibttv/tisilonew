@extends('layouts.storefront')

@section('title', ($product->seo_title ?: $product->name).' — Tisilo')

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
        $activeVariations = $product->variations->where('status', true)->sortByDesc('is_default')->sortBy('sort_order');
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-5 text-xs text-slate-500 sm:px-6 lg:px-8">
        <a href="{{ route('store.home') }}" class="hover:text-orange-600">হোম</a>
        <span class="mx-2">/</span>
        <a href="{{ route('store.products.index', ['category' => $product->category?->slug]) }}" class="hover:text-orange-600">{{ $product->category?->name ?? 'পণ্য' }}</a>
        <span class="mx-2">/</span>
        <span class="text-slate-800">{{ $product->name }}</span>
    </div>

    <section class="mx-auto grid max-w-7xl gap-8 px-4 pb-14 sm:px-6 lg:grid-cols-[minmax(0,.9fr)_minmax(0,1.1fr)] lg:px-8">
        <div>
            <div class="aspect-square overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-100 via-white to-orange-50">
                @if ($summary['image'])
                    <img src="{{ $summary['image'] }}" alt="{{ $product->name }}" class="size-full object-cover">
                @else
                    <div class="grid size-full place-items-center p-8 text-center">
                        <div>
                            <span class="mx-auto grid size-32 place-items-center rounded-[2rem] bg-white text-6xl font-black text-orange-500 shadow-lg">{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</span>
                            <p class="mt-6 font-bold text-slate-500">{{ $product->brand?->name ?? 'Tisilo Choice' }}</p>
                        </div>
                    </div>
                @endif
            </div>
            <div class="mt-4 grid grid-cols-3 gap-3 text-center text-xs font-bold text-slate-600">
                <span class="rounded-xl border border-slate-200 bg-white p-3">✓ আসল পণ্য</span>
                <span class="rounded-xl border border-slate-200 bg-white p-3">↻ সহজ রিটার্ন</span>
                <span class="rounded-xl border border-slate-200 bg-white p-3">🔒 নিরাপদ পেমেন্ট</span>
            </div>
        </div>

        <div>
            <p class="text-sm font-bold text-orange-600">{{ $product->brand?->name ?? 'Tisilo' }} · {{ $product->category?->name }}</p>
            <h1 class="mt-3 text-3xl font-black leading-tight text-slate-950 sm:text-4xl">{{ $product->name }}</h1>
            <div class="mt-4 flex flex-wrap items-center gap-4 text-sm">
                <span class="font-bold text-amber-500">★ 4.8 <span class="font-medium text-slate-400">(0 রিভিউ)</span></span>
                <span class="text-slate-300">|</span>
                <span class="text-slate-500">SKU: {{ $product->sku ?: 'N/A' }}</span>
                <span class="{{ $summary['available'] > 0 ? 'text-emerald-600' : 'text-amber-600' }} font-bold">{{ $summary['available'] > 0 ? 'স্টকে আছে' : 'অর্ডারযোগ্য' }}</span>
            </div>

            <div class="mt-6 rounded-2xl bg-orange-50 p-5">
                <div class="flex items-end gap-3">
                    <span class="text-4xl font-black text-orange-600">৳{{ number_format($summary['price'], 0) }}</span>
                    @if ($summary['regular_price'] > $summary['price'])
                        <span class="pb-1 text-lg text-slate-400 line-through">৳{{ number_format($summary['regular_price'], 0) }}</span>
                        <span class="mb-1 rounded-full bg-rose-500 px-2.5 py-1 text-xs font-bold text-white">{{ $summary['discount'] }}% ছাড়</span>
                    @endif
                </div>
                <p class="mt-2 text-xs text-slate-500">মূল্য ভেন্ডর ও নির্বাচিত ভ্যারিয়েশন অনুযায়ী পরিবর্তিত হতে পারে।</p>
            </div>

            @if ($product->short_description)
                <p class="mt-6 text-sm leading-7 text-slate-600">{{ $product->short_description }}</p>
            @endif

            @if ($offers->isNotEmpty())
                <div class="mt-7">
                    <h2 class="text-sm font-black text-slate-900">বিক্রেতা নির্বাচন করুন</h2>
                    <div class="mt-3 space-y-3">
                        @foreach ($offers as $offer)
                            @php($offerItem = $offer['item'])
                            <form method="POST" action="{{ route('store.cart.store') }}" class="flex flex-wrap items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-orange-300">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="hidden" name="vendor_listing_item_id" value="{{ $offerItem->id }}">
                                <div class="min-w-0 flex-1">
                                    <p class="font-black text-slate-900">{{ $offer['listing']->vendor->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $offerItem->seller_sku }} · {{ $offerItem->available_quantity }}টি স্টকে</p>
                                </div>
                                <span class="text-lg font-black text-orange-600">৳{{ number_format((float) ($offerItem->sale_price ?? $offerItem->regular_price), 0) }}</span>
                                <input type="number" name="quantity" value="1" min="1" max="99" class="h-11 w-20 rounded-xl border border-slate-200 px-3 text-center">
                                <button class="h-11 rounded-xl bg-orange-500 px-5 text-sm font-black text-white hover:bg-orange-600">কার্টে যোগ</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @else
                <form method="POST" action="{{ route('store.cart.store') }}" class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">

                    @if ($product->product_type === 'variable' && $activeVariations->isNotEmpty())
                        <label for="product-variation" class="text-sm font-black text-slate-900">ভ্যারিয়েশন নির্বাচন করুন</label>
                        <select id="product-variation" name="product_variation_id" class="mt-2 h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-orange-400">
                            @foreach ($activeVariations as $variation)
                                <option value="{{ $variation->id }}">
                                    {{ $variation->attributeValues->isNotEmpty() ? $variation->attributeValues->map(fn ($value) => $value->attribute->name.': '.$value->value)->join(' · ') : 'Option '.$loop->iteration }}
                                    — ৳{{ number_format((float) ($variation->sale_price ?? $variation->regular_price), 0) }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    <div class="mt-5 flex flex-wrap gap-3">
                        <input type="number" name="quantity" value="1" min="1" max="99" class="h-12 w-24 rounded-xl border border-slate-200 px-3 text-center font-bold">
                        <button class="h-12 flex-1 rounded-xl bg-orange-500 px-6 text-sm font-black text-white shadow-lg shadow-orange-500/20 hover:bg-orange-600">@svg('heroicon-o-shopping-cart', 'mr-2 inline size-5') কার্টে যোগ করুন</button>
                    </div>
                    @error('quantity')<p class="mt-3 text-sm font-bold text-rose-600">{{ $message }}</p>@enderror
                </form>
            @endif

            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">ডেলিভারি</p><p class="mt-1 text-sm font-black">সারাদেশে ২–৫ কার্যদিবস</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">পেমেন্ট</p><p class="mt-1 text-sm font-black">COD, bKash, Nagad ও Card</p></div>
            </div>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-white py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-black text-slate-950">পণ্যের বিস্তারিত</h2>
            <div class="prose prose-slate mt-5 max-w-none text-sm leading-7 text-slate-600">
                @if ($product->description)
                    {!! nl2br(e($product->description)) !!}
                @else
                    <p>এই পণ্যের বিস্তারিত তথ্য শিগগিরই যোগ করা হবে।</p>
                @endif
            </div>
        </div>
    </section>

    @if ($related->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-black text-slate-950">সম্পর্কিত পণ্য</h2>
            <div class="mt-7 grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-5">
                @foreach ($related as $card)
                    @include('storefront.components.product-card', ['card' => $card])
                @endforeach
            </div>
        </section>
    @endif
@endsection
