<a
    data-brand-card="{{ $brand->id }}"
    href="{{ route('store.products.index', ['brands' => [$brand->id]]) }}"
    class="storefront-brand-item group overflow-hidden rounded-2xl border border-slate-200 bg-white transition duration-300 hover:-translate-y-1 hover:border-purple-200 hover:shadow-xl hover:shadow-purple-100/50"
    @if($isDuplicate) tabindex="-1" @endif
>
    <div data-brand-card-image class="relative aspect-square overflow-hidden bg-slate-50">
        @if($brand->logo)
            <img
                src="{{ asset('storage/'.ltrim($brand->logo, '/')) }}"
                alt="{{ $brand->name }}"
                class="size-full object-contain p-4 transition duration-500 group-hover:scale-105 sm:p-5"
                loading="lazy"
            >
        @else
            <div data-brand-card-placeholder class="grid size-full place-items-center p-4" aria-hidden="true">
                <span class="grid size-16 place-items-center rounded-2xl bg-purple-50 text-2xl font-black text-purple-700 shadow-xs ring-1 ring-purple-100">
                    {{ mb_strtoupper(mb_substr($brand->name, 0, 1)) }}
                </span>
            </div>
        @endif
    </div>
    <h3 data-brand-card-name class="line-clamp-2 min-h-[2.75rem] border-t border-slate-100 px-2.5 py-2.5 text-center text-sm font-bold leading-snug text-slate-800 transition group-hover:text-purple-700">
        {{ $brand->name }}
    </h3>
</a>
