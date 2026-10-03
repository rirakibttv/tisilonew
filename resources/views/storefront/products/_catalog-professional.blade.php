@php
    $catalogUrl = $selectedCategory?->permalink ?? route('store.shop.index');
    $catalogHeading = $selectedCategory?->name ?? 'Tisilo Shop';
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

<section class="storefront-shell py-3" data-catalog-page>
    <nav class="mb-2 flex min-h-9 flex-wrap items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-xs" aria-label="{{ __('Breadcrumb') }}" data-catalog-breadcrumb>
        <a href="{{ route('store.home') }}" class="inline-flex items-center gap-1.5 transition hover:text-purple-700">
            @svg('heroicon-o-home', 'size-4')
            <span>{{ __('Home') }}</span>
        </a>
        @svg('heroicon-o-chevron-right', 'size-3.5 text-slate-400')
        @if ($selectedCategory)
            <a href="{{ route('store.shop.index') }}" class="transition hover:text-purple-700">{{ __('Shop') }}</a>
            @svg('heroicon-o-chevron-right', 'size-3.5 text-slate-400')
            <span class="font-bold text-slate-900" aria-current="page">{{ $selectedCategory->name }}</span>
        @else
            <span class="font-bold text-slate-900" aria-current="page">{{ __('Shop') }}</span>
        @endif
    </nav>

    <button type="button" data-catalog-filter-overlay class="fixed inset-0 z-40 hidden bg-slate-950/50 backdrop-blur-[2px] lg:hidden" aria-label="{{ __('Close filters') }}"></button>

    <div class="flex items-start gap-4">
        <aside data-catalog-filter-panel class="fixed inset-y-0 left-0 z-50 w-[min(88vw,340px)] -translate-x-full overflow-y-auto bg-white shadow-2xl transition-transform duration-300 lg:sticky lg:top-3 lg:z-0 lg:block lg:max-h-[calc(100vh-24px)] lg:w-[285px] lg:shrink-0 lg:translate-x-0 lg:rounded-2xl lg:border lg:border-slate-200 lg:shadow-sm">
            <form id="catalog-filter-form" method="GET" action="{{ $catalogUrl }}" data-catalog-filter>
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-gradient-to-r from-purple-800 to-violet-700 px-4 py-3 text-white">
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-funnel', 'size-5')
                        <h2 class="font-black">{{ __('Filter Products') }}</h2>
                        @if ($activeFilterCount)
                            <span class="grid size-6 place-items-center rounded-full bg-white text-xs font-black text-purple-700">{{ $activeFilterCount }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ $catalogUrl }}" class="text-xs font-bold text-purple-100 underline underline-offset-4 hover:text-white">{{ __('Reset') }}</a>
                        <button type="button" data-catalog-filter-close class="grid size-8 place-items-center rounded-lg bg-white/10 lg:hidden" aria-label="{{ __('Close filters') }}">
                            @svg('heroicon-o-x-mark', 'size-5')
                        </button>
                    </div>
                </div>

                <div class="border-b border-slate-200 p-4">
                    <label for="catalog-search" class="mb-2 block text-xs font-black uppercase tracking-wide text-slate-500">{{ __('Search Products') }}</label>
                    <div class="relative">
                        @svg('heroicon-o-magnifying-glass', 'pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400')
                        <input id="catalog-search" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Product, brand or SKU') }}" class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-3 text-sm outline-none transition focus:border-purple-500 focus:bg-white focus:ring-4 focus:ring-purple-100">
                    </div>
                </div>

                <details class="group border-b border-slate-200" open>
                    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-black text-slate-800">
                        {{ __('Price Range') }}
                        @svg('heroicon-o-chevron-down', 'size-4 text-slate-400 transition group-open:rotate-180')
                    </summary>
                    <div class="px-4 pb-4" data-price-range data-min-bound="{{ $catalogMinimumPrice }}" data-max-bound="{{ $catalogMaximumPrice }}">
                        <div class="grid grid-cols-2 gap-2">
                            <label>
                                <span class="mb-1 block text-[11px] font-bold text-slate-500">{{ __('Minimum') }}</span>
                                <span class="flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 px-2 text-sm">
                                    <b class="mr-1 text-slate-400">৳</b>
                                    <input data-price-min-input type="number" min="{{ $catalogMinimumPrice }}" max="{{ $catalogMaximumPrice }}" name="min_price" value="{{ $minimumPrice }}" placeholder="{{ $catalogMinimumPrice }}" class="min-w-0 flex-1 bg-transparent outline-none">
                                </span>
                            </label>
                            <label>
                                <span class="mb-1 block text-[11px] font-bold text-slate-500">{{ __('Maximum') }}</span>
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
                                <input data-price-min-range type="range" min="{{ $catalogMinimumPrice }}" max="{{ $catalogMaximumPrice }}" value="{{ $rangeMinimum }}" aria-label="{{ __('Minimum price') }}">
                                <input data-price-max-range type="range" min="{{ $catalogMinimumPrice }}" max="{{ $catalogMaximumPrice }}" value="{{ $rangeMaximum }}" aria-label="{{ __('Maximum price') }}">
                            </div>
                        @endif
                    </div>
                </details>

                <details class="group border-b border-slate-200" open>
                    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-black text-slate-800">
                        {{ __('Stock Status') }}
                        @svg('heroicon-o-chevron-down', 'size-4 text-slate-400 transition group-open:rotate-180')
                    </summary>
                    <div class="space-y-2 px-4 pb-4">
                        @foreach (['' => 'All Products', 'in_stock' => 'In Stock', 'out_of_stock' => 'Out of Stock'] as $stockValue => $stockLabel)
                            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                                <input type="radio" name="availability" value="{{ $stockValue }}" @checked($availability === $stockValue) class="size-4 border-slate-300 text-purple-700 focus:ring-purple-500">
                                <span>{{ __($stockLabel) }}</span>
                            </label>
                        @endforeach
                    </div>
                </details>

                <details class="group border-b border-slate-200" open>
                    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-black text-slate-800">
                        {{ __('Category') }}
                        @svg('heroicon-o-chevron-down', 'size-4 text-slate-400 transition group-open:rotate-180')
                    </summary>
                    <div class="catalog-filter-scroll max-h-64 space-y-2 overflow-y-auto px-4 pb-4 pr-2">
                        <label class="flex cursor-pointer items-start gap-2.5 text-sm text-slate-700">
                            <input type="radio" name="category" value="" data-category-filter data-url="{{ route('store.shop.index') }}" @checked(blank($selectedCategoryPath)) class="mt-0.5 size-4 border-slate-300 text-purple-700 focus:ring-purple-500">
                            <span>{{ __('All Categories') }}</span>
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
                            {{ __('Brand') }}
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
                        {{ __('Customer Rating') }}
                        @svg('heroicon-o-chevron-down', 'size-4 text-slate-400 transition group-open:rotate-180')
                    </summary>
                    <div class="space-y-2 px-4 pb-4">
                        <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                            <input type="radio" name="rating" value="" @checked($minimumRating === 0) class="size-4 border-slate-300 text-purple-700 focus:ring-purple-500">
                            <span>{{ __('All Ratings') }}</span>
                        </label>
                        @foreach ([5, 4, 3, 2, 1] as $rating)
                            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                                <input type="radio" name="rating" value="{{ $rating }}" @checked($minimumRating === $rating) class="size-4 border-slate-300 text-purple-700 focus:ring-purple-500">
                                <span class="tracking-wider text-amber-400">{{ str_repeat('★', $rating) }}<i class="not-italic text-slate-300">{{ str_repeat('★', 5 - $rating) }}</i></span>
                                <span class="text-xs text-slate-500">{{ __('and above') }}</span>
                            </label>
                        @endforeach
                    </div>
                </details>

                <div class="sticky bottom-0 bg-white p-4 shadow-[0_-8px_20px_rgba(15,23,42,0.06)]">
                    <button class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-purple-700 to-violet-600 px-5 text-sm font-black text-white shadow-lg shadow-purple-200 transition hover:from-purple-800 hover:to-violet-700">
                        @svg('heroicon-o-adjustments-horizontal', 'size-5')
                        {{ __('Show Results') }}
                    </button>
                </div>
            </form>
        </aside>

        <main class="min-w-0 flex-1">
            <div class="flex min-h-12 flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-xs" data-catalog-toolbar>
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" data-catalog-filter-open class="relative grid size-9 shrink-0 place-items-center rounded-lg bg-purple-50 text-purple-700 lg:hidden" aria-label="{{ __('Open filters') }}">
                        @svg('heroicon-o-funnel', 'size-5')
                        @if ($activeFilterCount)
                            <span class="absolute -right-1 -top-1 grid size-5 place-items-center rounded-full bg-rose-500 text-[10px] font-black text-white">{{ $activeFilterCount }}</span>
                        @endif
                    </button>
                    <div class="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-0.5">
                        <h1 class="truncate text-base font-black text-slate-900">{{ $catalogHeading }}</h1>
                        <p class="text-xs font-semibold text-slate-500">(<span class="text-purple-700">{{ number_format($products->total()) }}</span> {{ __('products found') }})</p>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-xs font-bold text-slate-500">
                    <span class="hidden sm:inline">{{ __('Sort by') }}:</span>
                    <select name="sort" form="catalog-filter-form" data-catalog-sort class="h-9 rounded-lg border border-slate-200 bg-slate-50 px-3 pr-8 text-sm font-bold text-slate-700 outline-none focus:border-purple-500">
                        <option value="latest" @selected($sort === 'latest')>{{ __('Latest') }}</option>
                        <option value="price_low" @selected($sort === 'price_low')>{{ __('Price: Low to High') }}</option>
                        <option value="price_high" @selected($sort === 'price_high')>{{ __('Price: High to Low') }}</option>
                        <option value="name" @selected($sort === 'name')>{{ __('Name') }}</option>
                    </select>
                </label>
            </div>

            @if ($activeFilterCount)
                <div class="mt-3 flex flex-wrap items-center gap-2 rounded-xl bg-purple-50 px-3 py-2 text-xs">
                    <span class="font-bold text-slate-500">{{ __('Active Filters') }}:</span>
                    @if (request('q'))<span class="rounded-full bg-white px-2.5 py-1 font-bold text-purple-700 shadow-sm">“{{ request('q') }}”</span>@endif
                    @foreach ($brands->whereIn('id', $brandIds) as $activeBrand)<span class="rounded-full bg-white px-2.5 py-1 font-bold text-purple-700 shadow-sm">{{ $activeBrand->name }}</span>@endforeach
                    @if ($availability)<span class="rounded-full bg-white px-2.5 py-1 font-bold text-purple-700 shadow-sm">{{ __($availability === 'in_stock' ? 'In Stock' : 'Out of Stock') }}</span>@endif
                    @if ($minimumRating)<span class="rounded-full bg-white px-2.5 py-1 font-bold text-purple-700 shadow-sm">★ {{ $minimumRating }}+</span>@endif
                    @if ($minimumPrice !== null || $maximumPrice !== null)<span class="rounded-full bg-white px-2.5 py-1 font-bold text-purple-700 shadow-sm">৳{{ number_format($minimumPrice ?? $catalogMinimumPrice) }} – ৳{{ number_format($maximumPrice ?? $catalogMaximumPrice) }}</span>@endif
                    @foreach ($filterAttributes as $attribute)
                        @foreach ($attribute->values->whereIn('id', $attributeFilters[$attribute->slug] ?? []) as $activeValue)
                            <span class="rounded-full bg-white px-2.5 py-1 font-bold text-purple-700 shadow-sm">{{ $activeValue->value }}</span>
                        @endforeach
                    @endforeach
                    <a href="{{ $catalogUrl }}" class="ml-auto font-black text-rose-600 hover:text-rose-700">{{ __('Clear All') }}</a>
                </div>
            @endif

            <div data-catalog-infinite data-next-page-url="{{ $products->nextPageUrl() }}" data-total-products="{{ $products->total() }}" data-number-locale="{{ app()->isLocale('bn') ? 'bn-BD' : 'en-US' }}" data-all-loaded-template="{{ __('All :count products loaded', ['count' => '__COUNT__']) }}">
                <div data-product-grid class="storefront-product-grid mt-4 grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
                    @forelse ($products as $card)
                        @include('storefront.components.product-card', ['card' => $card])
                    @empty
                        <div class="col-span-full rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-20 text-center">
                            @svg('heroicon-o-magnifying-glass', 'mx-auto size-12 text-slate-300')
                            <h2 class="mt-4 text-lg font-black text-slate-800">{{ __('No products found') }}</h2>
                            <p class="mt-2 text-sm text-slate-500">{{ __('Change the filters and try again.') }}</p>
                            <a href="{{ $catalogUrl }}" class="mt-5 inline-flex rounded-xl bg-purple-700 px-5 py-3 text-sm font-bold text-white">{{ __('Clear All Filters') }}</a>
                        </div>
                    @endforelse
                </div>

                <div data-catalog-load-sentinel class="mt-8 flex min-h-12 items-center justify-center text-center" aria-live="polite">
                    <div data-catalog-loading class="hidden items-center gap-2 text-sm font-bold text-purple-700">
                        <span class="size-5 animate-spin rounded-full border-2 border-purple-200 border-t-purple-700"></span>
                        {{ __('Loading more products…') }}
                    </div>
                    <p data-catalog-end class="text-sm font-semibold text-slate-500 {{ $products->hasMorePages() ? 'hidden' : '' }}">{{ __('All :count products loaded', ['count' => number_format($products->total())]) }}</p>
                    <button type="button" data-catalog-retry class="hidden rounded-xl border border-purple-200 bg-purple-50 px-4 py-2 text-sm font-bold text-purple-700">{{ __('Try Again') }}</button>
                </div>
            </div>

            @if ($products->hasPages())
                <div data-catalog-pagination class="mt-10">{{ $products->links() }}</div>
            @endif
        </main>
    </div>
</section>
