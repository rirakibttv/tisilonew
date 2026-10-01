<section class="border-b border-purple-100 bg-gradient-to-r from-purple-950 via-purple-800 to-indigo-800 text-white">
    <div class="storefront-shell py-8">
        <nav class="text-xs font-semibold text-purple-200" aria-label="Breadcrumb">
            <a href="{{ route('store.home') }}" class="hover:text-white">Home</a>
            <span class="mx-2">/</span>
            <a href="{{ route('store.shop.index') }}" class="hover:text-white">Shop</a>
            @if ($selectedCategory)
                <span class="mx-2">/</span>
                <span class="text-white">{{ $selectedCategory->name }}</span>
            @endif
        </nav>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black sm:text-4xl">{{ $selectedCategory?->name ?? 'Tisilo Shop' }}</h1>
                <p class="mt-1.5 text-sm text-purple-100">{{ number_format($products->total()) }}টি পণ্য থেকে আপনার পছন্দের পণ্যটি খুঁজুন</p>
            </div>
            <span class="rounded-full border border-white/20 bg-white/10 px-4 py-2 text-xs font-bold">নিরাপদ কেনাকাটা · সারাদেশে ডেলিভারি</span>
        </div>
    </div>
</section>

@php
    $catalogUrl = $selectedCategory?->permalink ?? route('store.shop.index');
    $selectedCategoryPath = $selectedCategory?->hierarchicalPath() ?? request('category');
    $activeAttributeValues = collect($attributeFilters)->flatten()->count();
    $activeFilterCount = (int) request()->filled('q')
        + count($brandIds)
        + (int) filled($availability)
        + (int) ($minimumRating > 0)
        + (int) ($minimumPrice !== null)
        + (int) ($maximumPrice !== null)
        + $activeAttributeValues;
    $rangeMinimum = $minimumPrice !== null ? (int) $minimumPrice : $catalogMinimumPrice;
    $rangeMaximum = $maximumPrice !== null ? (int) $maximumPrice : $catalogMaximumPrice;
    $filterCategories = $selectedCategory
        ? $categories->filter(function ($category) use ($selectedCategory) {
            $contextParentId = $selectedCategory->parent_id;

            return $category->id === $selectedCategory->id
                || $category->parent_id === $selectedCategory->id
                || ($contextParentId && ($category->id === $contextParentId || $category->parent_id === $contextParentId));
        })
        : $categories->whereNull('parent_id');
@endphp

<section class="storefront-shell py-5" data-catalog-page>
    <button type="button" data-catalog-filter-overlay class="fixed inset-0 z-40 hidden bg-slate-950/50 backdrop-blur-[2px] lg:hidden" aria-label="ফিল্টার বন্ধ করুন"></button>

    <div class="flex items-start gap-4">
        <aside data-catalog-filter-panel class="fixed inset-y-0 left-0 z-50 w-[min(88vw,340px)] -translate-x-full overflow-y-auto bg-white shadow-2xl transition-transform duration-300 lg:sticky lg:top-3 lg:z-0 lg:block lg:max-h-[calc(100vh-24px)] lg:w-[285px] lg:shrink-0 lg:translate-x-0 lg:rounded-2xl lg:border lg:border-slate-200 lg:shadow-sm">
            <form id="catalog-filter-form" method="GET" action="{{ $catalogUrl }}" data-catalog-filter>
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-gradient-to-r from-purple-800 to-violet-700 px-4 py-3 text-white">
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-funnel', 'size-5')
                        <h2 class="font-black">ফিল্টার করুন</h2>
                        @if ($activeFilterCount)
                            <span class="grid size-6 place-items-center rounded-full bg-white text-xs font-black text-purple-700">{{ $activeFilterCount }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ $catalogUrl }}" class="text-xs font-bold text-purple-100 underline underline-offset-4 hover:text-white">রিসেট</a>
                        <button type="button" data-catalog-filter-close class="grid size-8 place-items-center rounded-lg bg-white/10 lg:hidden" aria-label="ফিল্টার বন্ধ করুন">
                            @svg('heroicon-o-x-mark', 'size-5')
                        </button>
                    </div>
                </div>

                <div class="border-b border-slate-200 p-4">
                    <label for="catalog-search" class="mb-2 block text-xs font-black uppercase tracking-wide text-slate-500">পণ্য খুঁজুন</label>
                    <div class="relative">
                        @svg('heroicon-o-magnifying-glass', 'pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400')
                        <input id="catalog-search" type="search" name="q" value="{{ request('q') }}" placeholder="পণ্য, ব্র্যান্ড বা SKU" class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-3 text-sm outline-none transition focus:border-purple-500 focus:bg-white focus:ring-4 focus:ring-purple-100">
                    </div>
                </div>

                <details class="group border-b border-slate-200" open>
                    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-black text-slate-800">
                        মূল্যসীমা
                        @svg('heroicon-o-chevron-down', 'size-4 text-slate-400 transition group-open:rotate-180')
                    </summary>
                    <div class="px-4 pb-4" data-price-range data-min-bound="{{ $catalogMinimumPrice }}" data-max-bound="{{ $catalogMaximumPrice }}">
                        <div class="grid grid-cols-2 gap-2">
                            <label>
                                <span class="mb-1 block text-[11px] font-bold text-slate-500">সর্বনিম্ন</span>
                                <span class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 px-2 text-sm">
                                    <b class="mr-1 text-slate-400">৳</b>
                                    <input data-price-min-input type="number" min="{{ $catalogMinimumPrice }}" max="{{ $catalogMaximumPrice }}" name="min_price" value="{{ $minimumPrice }}" placeholder="{{ $catalogMinimumPrice }}" class="min-w-0 flex-1 bg-transparent outline-none">
                                </span>
                            </label>
                            <label>
                                <span class="mb-1 block text-[11px] font-bold text-slate-500">সর্বোচ্চ</span>
                                <span class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 px-2 text-sm">
                                    <b class="mr-1 text-slate-400">৳</b>
                                    <input data-price-max-input type="number" min="{{ $catalogMinimumPrice }}" max="{{ $catalogMaximumPrice }}" name="max_price" value="{{ $maximumPrice }}" placeholder="{{ $catalogMaximumPrice }}" class="min-w-0 flex-1 bg-transparent outline-none">
                                </span>
                            </label>
                        </div>
                        @if ($catalogMaximumPrice > $catalogMinimumPrice)
                            <div class="catalog-price-slider mt-4" style="--price-start: {{ (($rangeMinimum - $catalogMinimumPrice) / max(1, $catalogMaximumPrice - $catalogMinimumPrice)) * 100 }}%; --price-end: {{ (($rangeMaximum - $catalogMinimumPrice) / max(1, $catalogMaximumPrice - $catalogMinimumPrice)) * 100 }}%;">
                                <span class="catalog-price-slider__track"></span>
                                <span class="catalog-price-slider__active"></span>
                                <input data-price-min-range type="range" min="{{ $catalogMinimumPrice }}" max="{{ $catalogMaximumPrice }}" value="{{ $rangeMinimum }}" aria-label="সর্বনিম্ন মূল্য">
                                <input data-price-max-range type="range" min="{{ $catalogMinimumPrice }}" max="{{ $catalogMaximumPrice }}" value="{{ $rangeMaximum }}" aria-label="সর্বোচ্চ মূল্য">
                            </div>
                        @endif
                    </div>
                </details>

                <details class="group border-b border-slate-200" open>
                    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-black text-slate-800">
                        স্টক অবস্থা
                        @svg('heroicon-o-chevron-down', 'size-4 text-slate-400 transition group-open:rotate-180')
                    </summary>
                    <div class="space-y-2 px-4 pb-4">
                        @foreach (['' => 'সব পণ্য', 'in_stock' => 'স্টকে আছে', 'out_of_stock' => 'স্টক শেষ'] as $stockValue => $stockLabel)
                            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                                <input type="radio" name="availability" value="{{ $stockValue }}" @checked($availability === $stockValue) class="size-4 border-slate-300 text-purple-700 focus:ring-purple-500">
                                <span>{{ $stockLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                </details>

                <details class="group border-b border-slate-200" open>
                    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-black text-slate-800">
                        ক্যাটাগরি
                        @svg('heroicon-o-chevron-down', 'size-4 text-slate-400 transition group-open:rotate-180')
                    </summary>
                    <div class="catalog-filter-scroll max-h-64 space-y-2 overflow-y-auto px-4 pb-4 pr-2">
                        <label class="flex cursor-pointer items-start gap-2.5 text-sm text-slate-700">
                            <input type="radio" name="category" value="" data-category-filter data-url="{{ route('store.shop.index') }}" @checked(blank($selectedCategoryPath)) class="mt-0.5 size-4 border-slate-300 text-purple-700 focus:ring-purple-500">
                            <span>সব ক্যাটাগরি</span>
                        </label>
                        @foreach ($filterCategories as $category)
                            <label class="flex cursor-pointer items-start gap-2.5 text-sm text-slate-700">
                                <input type="radio" name="category" value="{{ $category->hierarchicalPath() }}" data-category-filter data-url="{{ $category->permalink }}" @checked($selectedCategoryPath === $category->hierarchicalPath()) class="mt-0.5 size-4 border-slate-300 text-purple-700 focus:ring-purple-500">
                                <span>{{ $category->hierarchicalName() }}</span>
                            </label>
                        @endforeach
                    </div>
                </details>

                @if ($brands->isNotEmpty())
                    <details class="group border-b border-slate-200" open>
                        <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-black text-slate-800">
                            ব্র্যান্ড
                            @svg('heroicon-o-chevron-down', 'size-4 text-slate-400 transition group-open:rotate-180')
                        </summary>
                        <div class="catalog-filter-scroll max-h-60 space-y-2 overflow-y-auto px-4 pb-4 pr-2">
                            @foreach ($brands as $brand)
                                <label class="flex cursor-pointer items-center justify-between gap-2 text-sm text-slate-700">
                                    <span class="flex min-w-0 items-center gap-2.5">
                                        <input type="checkbox" name="brands[]" value="{{ $brand->id }}" @checked(in_array($brand->id, $brandIds, true)) class="size-4 rounded border-slate-300 text-purple-700 focus:ring-purple-500">
                                        <span class="truncate">{{ $brand->name }}</span>
                                    </span>
                                    <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500">{{ $brand->catalog_products_count }}</span>
                                </label>
                            @endforeach
                        </div>
                    </details>
                @endif

                @foreach ($filterAttributes as $attribute)
                    <details class="group border-b border-slate-200" @if (isset($attributeFilters[$attribute->slug])) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-black text-slate-800">
                            {{ $attribute->name }}
                            @svg('heroicon-o-chevron-down', 'size-4 text-slate-400 transition group-open:rotate-180')
                        </summary>
                        <div class="catalog-filter-scroll max-h-60 space-y-2 overflow-y-auto px-4 pb-4 pr-2">
                            @foreach ($attribute->values as $value)
                                <label class="flex cursor-pointer items-center justify-between gap-2 text-sm text-slate-700">
                                    <span class="flex min-w-0 items-center gap-2.5">
                                        <input type="checkbox" name="attributes[{{ $attribute->slug }}][]" value="{{ $value->id }}" @checked(in_array($value->id, $attributeFilters[$attribute->slug] ?? [], true)) class="size-4 rounded border-slate-300 text-purple-700 focus:ring-purple-500">
                                        @if ($value->color_code)
                                            <i class="size-4 shrink-0 rounded-full border border-slate-200 shadow-sm" style="background: {{ $value->color_code }}"></i>
                                        @endif
                                        <span class="truncate">{{ $value->value }}</span>
                                    </span>
                                    <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500">{{ $value->catalog_products_count }}</span>
                                </label>
                            @endforeach
                        </div>
                    </details>
                @endforeach

                <details class="group border-b border-slate-200" @if ($minimumRating) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-black text-slate-800">
                        কাস্টমার রেটিং
                        @svg('heroicon-o-chevron-down', 'size-4 text-slate-400 transition group-open:rotate-180')
                    </summary>
                    <div class="space-y-2 px-4 pb-4">
                        <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                            <input type="radio" name="rating" value="" @checked($minimumRating === 0) class="size-4 border-slate-300 text-purple-700 focus:ring-purple-500">
                            <span>সব রেটিং</span>
                        </label>
                        @foreach ([5, 4, 3, 2, 1] as $rating)
                            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                                <input type="radio" name="rating" value="{{ $rating }}" @checked($minimumRating === $rating) class="size-4 border-slate-300 text-purple-700 focus:ring-purple-500">
                                <span class="tracking-wider text-amber-400">{{ str_repeat('★', $rating) }}<i class="not-italic text-slate-300">{{ str_repeat('★', 5 - $rating) }}</i></span>
                                <span class="text-xs text-slate-500">ও বেশি</span>
                            </label>
                        @endforeach
                    </div>
                </details>

                <div class="sticky bottom-0 bg-white p-4 shadow-[0_-8px_20px_rgba(15,23,42,0.06)]">
                    <button class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-purple-700 to-violet-600 px-5 text-sm font-black text-white shadow-lg shadow-purple-200 transition hover:from-purple-800 hover:to-violet-700">
                        @svg('heroicon-o-adjustments-horizontal', 'size-5')
                        ফলাফল দেখুন
                    </button>
                </div>
            </form>
        </aside>

        <main class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" data-catalog-filter-open class="relative grid size-10 shrink-0 place-items-center rounded-xl bg-purple-50 text-purple-700 lg:hidden" aria-label="ফিল্টার খুলুন">
                        @svg('heroicon-o-funnel', 'size-5')
                        @if ($activeFilterCount)
                            <span class="absolute -right-1 -top-1 grid size-5 place-items-center rounded-full bg-rose-500 text-[10px] font-black text-white">{{ $activeFilterCount }}</span>
                        @endif
                    </button>
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-black text-slate-900">{{ $selectedCategory?->name ?? 'সকল পণ্য' }}</h2>
                        <p class="text-xs font-semibold text-slate-500"><span class="text-purple-700">{{ number_format($products->total()) }}</span>টি পণ্য পাওয়া গেছে</p>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-xs font-bold text-slate-500">
                    <span class="hidden sm:inline">সাজান:</span>
                    <select name="sort" form="catalog-filter-form" data-catalog-sort class="h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 pr-8 text-sm font-bold text-slate-700 outline-none focus:border-purple-500">
                        <option value="latest" @selected($sort === 'latest')>সর্বশেষ</option>
                        <option value="price_low" @selected($sort === 'price_low')>দাম: কম থেকে বেশি</option>
                        <option value="price_high" @selected($sort === 'price_high')>দাম: বেশি থেকে কম</option>
                        <option value="name" @selected($sort === 'name')>নাম অনুযায়ী</option>
                    </select>
                </label>
            </div>

            @if ($activeFilterCount)
                <div class="mt-3 flex flex-wrap items-center gap-2 rounded-xl bg-purple-50 px-3 py-2 text-xs">
                    <span class="font-bold text-slate-500">সক্রিয় ফিল্টার:</span>
                    @if (request('q'))<span class="rounded-full bg-white px-2.5 py-1 font-bold text-purple-700 shadow-sm">“{{ request('q') }}”</span>@endif
                    @foreach ($brands->whereIn('id', $brandIds) as $activeBrand)<span class="rounded-full bg-white px-2.5 py-1 font-bold text-purple-700 shadow-sm">{{ $activeBrand->name }}</span>@endforeach
                    @if ($availability)<span class="rounded-full bg-white px-2.5 py-1 font-bold text-purple-700 shadow-sm">{{ $availability === 'in_stock' ? 'স্টকে আছে' : 'স্টক শেষ' }}</span>@endif
                    @if ($minimumRating)<span class="rounded-full bg-white px-2.5 py-1 font-bold text-purple-700 shadow-sm">★ {{ $minimumRating }}+</span>@endif
                    @if ($minimumPrice !== null || $maximumPrice !== null)<span class="rounded-full bg-white px-2.5 py-1 font-bold text-purple-700 shadow-sm">৳{{ number_format($minimumPrice ?? $catalogMinimumPrice) }} – ৳{{ number_format($maximumPrice ?? $catalogMaximumPrice) }}</span>@endif
                    @foreach ($filterAttributes as $attribute)
                        @foreach ($attribute->values->whereIn('id', $attributeFilters[$attribute->slug] ?? []) as $activeValue)
                            <span class="rounded-full bg-white px-2.5 py-1 font-bold text-purple-700 shadow-sm">{{ $activeValue->value }}</span>
                        @endforeach
                    @endforeach
                    <a href="{{ $catalogUrl }}" class="ml-auto font-black text-rose-600 hover:text-rose-700">সব মুছুন</a>
                </div>
            @endif

            <div data-catalog-infinite data-next-page-url="{{ $products->nextPageUrl() }}" data-total-products="{{ $products->total() }}">
                <div data-product-grid class="storefront-product-grid mt-4 grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
                    @forelse ($products as $card)
                        @include('storefront.components.product-card', ['card' => $card])
                    @empty
                        <div class="col-span-full rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-20 text-center">
                            @svg('heroicon-o-magnifying-glass', 'mx-auto size-12 text-slate-300')
                            <h2 class="mt-4 text-lg font-black text-slate-800">কোনো পণ্য পাওয়া যায়নি</h2>
                            <p class="mt-2 text-sm text-slate-500">ফিল্টার পরিবর্তন করে আবার চেষ্টা করুন।</p>
                            <a href="{{ $catalogUrl }}" class="mt-5 inline-flex rounded-xl bg-purple-700 px-5 py-3 text-sm font-bold text-white">সব ফিল্টার মুছুন</a>
                        </div>
                    @endforelse
                </div>

                <div data-catalog-load-sentinel class="mt-8 flex min-h-12 items-center justify-center text-center" aria-live="polite">
                    <div data-catalog-loading class="hidden items-center gap-2 text-sm font-bold text-purple-700">
                        <span class="size-5 animate-spin rounded-full border-2 border-purple-200 border-t-purple-700"></span>
                        আরও পণ্য লোড হচ্ছে…
                    </div>
                    <p data-catalog-end class="text-sm font-semibold text-slate-500 {{ $products->hasMorePages() ? 'hidden' : '' }}">সব {{ number_format($products->total()) }}টি পণ্য লোড হয়েছে</p>
                    <button type="button" data-catalog-retry class="hidden rounded-xl border border-purple-200 bg-purple-50 px-4 py-2 text-sm font-bold text-purple-700">আবার চেষ্টা করুন</button>
                </div>
            </div>

            @if ($products->hasPages())
                <div data-catalog-pagination class="mt-10">{{ $products->links() }}</div>
            @endif
        </main>
    </div>
</section>
