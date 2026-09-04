@php
    $item = $card;
    $product = $item['product'];
    $wishlistMode = $wishlistMode ?? false;
    $isWishlisted = collect(session('store_wishlist', []))->contains(fn ($id) => (int) $id === $product->id);
@endphp

<article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white transition duration-300 hover:-translate-y-1 hover:border-purple-200 hover:shadow-xl hover:shadow-purple-100/50 flex flex-col justify-between">
    <div class="relative aspect-square overflow-hidden bg-slate-50">
        <a href="{{ route('store.products.show', $product->slug) }}" class="block size-full">
            @if ($item['image'])
                <img src="{{ $item['image'] }}" alt="{{ $product->name }}" class="size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
            @else
                <div class="grid size-full place-items-center p-6 text-center">
                    <div>
                        <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-purple-50 text-2xl font-black text-purple-700 shadow-xs">{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</span>
                        <p class="mt-3 line-clamp-1 text-xs font-bold text-slate-500">{{ $product->brand?->name ?? 'Tisilo' }}</p>
                    </div>
                </div>
            @endif

            @if ($item['discount'] > 0)
                <span class="absolute left-2.5 top-2.5 rounded-md bg-rose-600 px-2 py-0.5 text-[11px] font-black text-white shadow-xs">-{{ $item['discount'] }}%</span>
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
            <p class="text-[11px] font-bold text-purple-700 uppercase tracking-wider">{{ $product->category?->name ?? 'Tisilo' }}</p>
            <h3 class="mt-1 line-clamp-2 min-h-[2.5rem] text-xs sm:text-sm font-bold leading-snug text-slate-800 group-hover:text-purple-700 transition">
                <a href="{{ route('store.products.show', $product->slug) }}">
                    {{ $product->name }}
                </a>
            </h3>
        </div>

        <div class="mt-3">
            <div class="flex items-baseline gap-2">
                <span class="text-base sm:text-lg font-black text-slate-900">৳{{ number_format($item['price'], 0) }}</span>
                @if ($item['regular_price'] > $item['price'])
                    <span class="text-xs text-slate-400 line-through">৳{{ number_format($item['regular_price'], 0) }}</span>
                @endif
            </div>

            <div class="mt-3 pt-2 border-t border-slate-100 flex items-center justify-between">
                <span class="text-[11px] font-medium text-slate-400">{{ $item['available'] > 0 ? $item['available'].'টি স্টকে' : 'স্টকে আছে' }}</span>
                <a href="{{ route('store.products.show', $product->slug) }}" class="rounded-lg bg-purple-50 px-2.5 py-1 text-xs font-bold text-purple-700 transition hover:bg-purple-700 hover:text-white">
                    অর্ডার করুন
                </a>
            </div>
        </div>
    </div>
</article>
