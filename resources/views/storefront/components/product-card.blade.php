@php
    $item = $card;
    $product = $item['product'];
    $wishlisted = collect(session('store_wishlist', []))->contains($product->id);
@endphp

<article class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white transition duration-300 hover:-translate-y-1 hover:border-orange-200 hover:shadow-xl hover:shadow-orange-100/60">
    <form method="POST" action="{{ $wishlisted ? route('store.wishlist.destroy', $product) : route('store.wishlist.store', $product) }}" class="absolute right-3 top-3 z-20">
        @csrf
        @if ($wishlisted) @method('DELETE') @endif
        <button class="grid size-9 place-items-center rounded-full bg-white/95 shadow-sm transition {{ $wishlisted ? 'text-rose-500' : 'text-slate-500 hover:text-rose-500' }}" aria-label="{{ $wishlisted ? 'উইশলিস্ট থেকে সরান' : 'উইশলিস্টে যোগ করুন' }}">
            @svg($wishlisted ? 'heroicon-s-heart' : 'heroicon-o-heart', 'size-5')
        </button>
    </form>
    <a href="{{ route('store.products.show', $product->slug) }}" class="block">
        <div class="relative aspect-square overflow-hidden bg-gradient-to-br from-slate-100 via-white to-orange-50">
            @if ($item['image'])
                <img src="{{ $item['image'] }}" alt="{{ $product->name }}" class="size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
            @else
                <div class="grid size-full place-items-center p-8 text-center">
                    <div>
                        <span class="mx-auto grid size-20 place-items-center rounded-3xl bg-white text-3xl font-black text-orange-500 shadow-sm">{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</span>
                        <p class="mt-4 line-clamp-2 text-sm font-bold text-slate-500">{{ $product->brand?->name ?? 'Tisilo Choice' }}</p>
                    </div>
                </div>
            @endif

            @if ($item['discount'] > 0)
                <span class="absolute left-3 top-3 rounded-full bg-rose-500 px-2.5 py-1 text-xs font-bold text-white">-{{ $item['discount'] }}%</span>
            @endif

        </div>

        <div class="p-4">
            <p class="text-xs font-semibold text-orange-600">{{ $product->category?->name ?? 'Featured' }}</p>
            <h3 class="mt-1 line-clamp-2 min-h-11 text-sm font-bold leading-5 text-slate-800 group-hover:text-orange-600">{{ $product->name }}</h3>
            <div class="mt-3 flex items-end gap-2">
                <span class="text-lg font-black text-slate-950">৳{{ number_format($item['price'], 0) }}</span>
                @if ($item['regular_price'] > $item['price'])
                    <span class="pb-0.5 text-xs text-slate-400 line-through">৳{{ number_format($item['regular_price'], 0) }}</span>
                @endif
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                <span class="flex items-center gap-1 text-amber-500">★ <span class="text-slate-500">4.8</span></span>
                <span>{{ $item['available'] > 0 ? $item['available'].'টি স্টকে' : 'অর্ডারযোগ্য' }}</span>
            </div>
        </div>
    </a>
</article>
