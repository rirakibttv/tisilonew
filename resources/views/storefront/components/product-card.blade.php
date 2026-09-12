@php
    $item = $card;
    $product = $item['product'];
    $wishlistMode = $wishlistMode ?? false;
    $isWishlisted = collect(session('store_wishlist', []))->contains(fn ($id) => (int) $id === $product->id);
@endphp

<article data-product-card="{{ $product->id }}" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white transition duration-300 hover:-translate-y-1 hover:border-purple-200 hover:shadow-xl hover:shadow-purple-100/50 flex flex-col justify-between">
    <div class="relative aspect-square overflow-hidden bg-slate-50">
        <a href="{{ route('store.products.show', $product->slug) }}" class="block size-full">
            @if ($item['image'])
                <img src="{{ $item['image'] }}" alt="{{ $product->name }}" class="size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
            @else
                <div class="grid size-full place-items-center p-6 text-center">
                    <div>
                        <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-purple-50 text-2xl font-black text-purple-700 shadow-xs">{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</span>
                        <p class="mt-3 line-clamp-1 text-sm font-bold text-slate-500">{{ $product->brand?->name ?? 'Tisilo' }}</p>
                    </div>
                </div>
            @endif

            @if ($item['discount'] > 0)
                <span class="absolute left-2.5 top-2.5 rounded-md bg-rose-600 px-2 py-0.5 text-xs font-black text-white shadow-xs">-{{ $item['discount'] }}%</span>
            @endif
        </a>

        <form method="POST" action="{{ $wishlistMode || $isWishlisted ? route('store.wishlist.destroy', $product) : route('store.wishlist.store', $product) }}" class="absolute right-2.5 top-2.5">
            @csrf
            @if($wishlistMode || $isWishlisted) @method('DELETE') @endif
            <button class="grid size-8 place-items-center rounded-full bg-white/90 shadow-sm transition hover:text-rose-600 {{ $isWishlisted ? 'text-rose-600' : 'text-slate-400' }}" aria-label="{{ $isWishlisted ? 'উইশলিস্ট থেকে সরান' : 'উইশলিস্টে যোগ করুন' }}">
                @svg('heroicon-o-heart', 'size-4')
            </button>
        </form>
    </div>

    <div class="p-3.5 flex flex-col flex-1 justify-between">
        <div>
            <h3 data-product-card-name class="line-clamp-2 min-h-[2.75rem] text-sm font-bold leading-snug text-slate-800 transition group-hover:text-purple-700 sm:text-base">
                <a href="{{ route('store.products.show', $product->slug) }}">
                    {{ $product->name }}
                </a>
            </h3>
        </div>

        <div class="mt-3">
            <div class="flex items-baseline gap-2">
                <span class="text-base sm:text-lg font-black text-slate-900">৳{{ number_format($item['price'], 0) }}</span>
                @if ($item['regular_price'] > $item['price'])
                    <span class="text-sm text-slate-400 line-through">৳{{ number_format($item['regular_price'], 0) }}</span>
                @endif
            </div>

            <div data-product-card-trust class="mt-2 flex min-w-0 items-center justify-between gap-2 border-t border-slate-100 pt-2 text-[11px] sm:text-xs">
                <span data-product-card-rating class="flex min-w-0 items-center gap-1 font-bold text-slate-600" aria-label="{{ number_format($item['review_rating'], 1) }} out of 5 from {{ $item['review_count'] }} reviews">
                    <span class="text-sm leading-none text-amber-500" aria-hidden="true">★</span>
                    <span>{{ number_format($item['review_rating'], 1) }}</span>
                    <span class="truncate font-medium text-slate-400">({{ $item['review_count'] }} রিভিউ)</span>
                </span>
                <span data-product-card-stock class="shrink-0 font-bold {{ $item['can_purchase'] ? 'text-emerald-600' : 'text-rose-600' }}">{{ $item['stock_label'] }}</span>
            </div>

            <form method="POST" action="{{ route('store.cart.store') }}" data-product-card-actions class="mt-2 grid grid-cols-2 gap-1.5">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="1">
                @if($item['product_variation_id'])
                    <input type="hidden" name="product_variation_id" value="{{ $item['product_variation_id'] }}">
                @endif
                @if($item['vendor_listing_item_id'])
                    <input type="hidden" name="vendor_listing_item_id" value="{{ $item['vendor_listing_item_id'] }}">
                @endif
                <button type="submit" name="redirect_to" value="cart" @disabled(! $item['can_purchase']) class="min-h-9 rounded-lg border border-purple-200 bg-purple-50 px-1.5 py-2 text-[10px] font-black leading-tight text-purple-700 transition hover:border-purple-700 hover:bg-purple-700 hover:text-white disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400 sm:text-xs">
                    Add to Cart
                </button>
                <button type="submit" name="redirect_to" value="checkout" @disabled(! $item['can_purchase']) class="min-h-9 rounded-lg bg-orange-500 px-1.5 py-2 text-[10px] font-black leading-tight text-white transition hover:bg-orange-600 disabled:cursor-not-allowed disabled:bg-slate-300 sm:text-xs">
                    Order Now
                </button>
            </form>
        </div>
    </div>
</article>
