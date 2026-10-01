<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\VendorListingItemStatus;
use App\Enums\VendorListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Support\Storefront\MarketplaceProductPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    private const CATALOG_PAGE_SIZE = 30;

    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        if ($request->filled('category')) {
            $category = $this->resolveCategoryPath($request->string('category')->toString());

            if ($category) {
                $query = $request->except(['category', 'page']);
                $url = $category->permalink.($query === [] ? '' : '?'.http_build_query($query));

                return redirect()->to($url, 301);
            }
        }

        return $this->catalog($request);
    }

    public function category(Request $request, string $categorySlug, ?string $categoryPath = null): View|JsonResponse|RedirectResponse
    {
        $category = $this->resolveCategoryPath(
            $categorySlug.(filled($categoryPath) ? '/'.$categoryPath : ''),
        );

        if (! $category && blank($categoryPath)) {
            $legacyMatches = Category::query()
                ->where('status', true)
                ->where('slug', $categorySlug)
                ->limit(2)
                ->get();

            if ($legacyMatches->count() === 1) {
                $query = $request->getQueryString();

                return redirect()->to($legacyMatches->first()->permalink.($query ? '?'.$query : ''), 301);
            }
        }

        abort_unless($category, 404);

        return $this->catalog($request, $category);
    }

    private function catalog(Request $request, ?Category $selectedCategory = null): View|JsonResponse
    {
        $sort = in_array($request->string('sort')->toString(), ['latest', 'price_low', 'price_high', 'name'], true)
            ? $request->string('sort')->toString()
            : 'latest';
        $brandIds = collect((array) $request->input('brands', []))
            ->push($request->integer('brand'))
            ->map(static fn ($id): int => max(0, (int) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $availability = in_array($request->string('availability')->toString(), ['in_stock', 'out_of_stock'], true)
            ? $request->string('availability')->toString()
            : '';
        $minimumRating = min(5, max(0, $request->integer('rating')));
        $minimumPrice = $this->normalizedPrice($request->input('min_price'));
        $maximumPrice = $this->normalizedPrice($request->input('max_price'));
        $attributeFilters = collect((array) $request->input('attributes', []))
            ->filter(static fn ($values, $slug): bool => is_string($slug) && is_array($values))
            ->map(static fn (array $values): array => collect($values)
                ->map(static fn ($id): int => max(0, (int) $id))
                ->filter()
                ->unique()
                ->values()
                ->all())
            ->filter()
            ->all();
        $categoryIds = $selectedCategory ? $this->categoryAndDescendantIds($selectedCategory) : [];
        $effectivePrice = 'COALESCE(products.sale_price, products.regular_price)';

        $query = Product::query()
            ->withReviewSummary()
            ->where('status', 'published')
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $search = $request->string('q')->toString();

                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhereHas('brand', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('category', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($selectedCategory, function (Builder $query) use ($categoryIds): void {
                $query->whereIn('category_id', $categoryIds);
            })
            ->when($brandIds !== [], fn (Builder $query) => $query->whereIn('brand_id', $brandIds))
            ->when($minimumPrice !== null, fn (Builder $query) => $query->whereRaw("{$effectivePrice} >= ?", [$minimumPrice]))
            ->when($maximumPrice !== null, fn (Builder $query) => $query->whereRaw("{$effectivePrice} <= ?", [$maximumPrice]))
            ->when($availability === 'in_stock', fn (Builder $query) => $this->whereAvailable($query))
            ->when($availability === 'out_of_stock', fn (Builder $query) => $this->whereUnavailable($query))
            ->when($minimumRating > 0, function (Builder $query) use ($minimumRating): void {
                $query->whereIn('products.id', ProductReview::query()
                    ->select('product_id')
                    ->published()
                    ->groupBy('product_id')
                    ->havingRaw('AVG(rating) >= ?', [$minimumRating]));
            });

        foreach ($attributeFilters as $attributeSlug => $valueIds) {
            $query->whereHas('variations', fn (Builder $variationQuery) => $variationQuery
                ->where('status', true)
                ->whereHas('attributeValues', fn (Builder $valueQuery) => $valueQuery
                    ->where('attribute_values.status', true)
                    ->whereIn('attribute_values.id', $valueIds)
                    ->whereHas('attribute', fn (Builder $attributeQuery) => $attributeQuery
                        ->where('slug', $attributeSlug)
                        ->where('status', true))));
        }

        $query->with($this->storefrontRelations());

        match ($sort) {
            'price_low' => $query->orderByRaw('COALESCE(sale_price, regular_price) asc'),
            'price_high' => $query->orderByRaw('COALESCE(sale_price, regular_price) desc'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('featured')->latest(),
        };

        $products = $query->paginate(self::CATALOG_PAGE_SIZE)->withQueryString();
        $products->through(fn (Product $product): array => MarketplaceProductPresenter::summarize($product));

        if ($request->boolean('catalog_fragment')) {
            return response()->json([
                'html' => view('storefront.products._cards', compact('products'))->render(),
                'next_page_url' => $products->nextPageUrl(),
                'loaded' => $products->count(),
            ]);
        }

        $brands = Brand::query()
            ->where('status', true)
            ->whereHas('products', function (Builder $query) use ($categoryIds): void {
                $query->where('status', 'published')
                    ->when($categoryIds !== [], fn (Builder $query) => $query->whereIn('category_id', $categoryIds));
            })
            ->withCount(['products as catalog_products_count' => fn (Builder $query) => $query
                ->where('status', 'published')
                ->when($categoryIds !== [], fn (Builder $query) => $query->whereIn('category_id', $categoryIds))])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $priceRange = Product::query()
            ->where('status', 'published')
            ->when($categoryIds !== [], fn (Builder $query) => $query->whereIn('category_id', $categoryIds))
            ->selectRaw("FLOOR(MIN({$effectivePrice})) as minimum, CEIL(MAX({$effectivePrice})) as maximum")
            ->first();
        $catalogMinimumPrice = max(0, (int) ($priceRange?->minimum ?? 0));
        $catalogMaximumPrice = max($catalogMinimumPrice, (int) ($priceRange?->maximum ?? 0));

        $attributeValueCounts = DB::table('attribute_value_product_variation as attribute_pivot')
            ->join('product_variations', 'product_variations.id', '=', 'attribute_pivot.product_variation_id')
            ->join('products', 'products.id', '=', 'product_variations.product_id')
            ->where('products.status', 'published')
            ->where('product_variations.status', true)
            ->when($categoryIds !== [], fn ($query) => $query->whereIn('products.category_id', $categoryIds))
            ->groupBy('attribute_pivot.attribute_value_id')
            ->selectRaw('attribute_pivot.attribute_value_id, COUNT(DISTINCT products.id) as product_count')
            ->pluck('product_count', 'attribute_pivot.attribute_value_id');

        $filterAttributes = Attribute::query()
            ->where('status', true)
            ->whereHas('values.productVariations', fn (Builder $query) => $query
                ->where('product_variations.status', true)
                ->whereHas('product', fn (Builder $productQuery) => $productQuery
                    ->where('status', 'published')
                    ->when($categoryIds !== [], fn (Builder $productQuery) => $productQuery->whereIn('category_id', $categoryIds))))
            ->with(['values' => fn ($query) => $query
                ->where('status', true)
                ->whereHas('productVariations', fn (Builder $variationQuery) => $variationQuery
                    ->where('product_variations.status', true)
                    ->whereHas('product', fn (Builder $productQuery) => $productQuery
                        ->where('status', 'published')
                        ->when($categoryIds !== [], fn (Builder $productQuery) => $productQuery->whereIn('category_id', $categoryIds))))
                ->orderBy('sort_order')
                ->orderBy('value')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(function (Attribute $attribute) use ($attributeValueCounts): void {
                $attribute->values->each(fn ($value) => $value->setAttribute(
                    'catalog_products_count',
                    (int) ($attributeValueCounts[$value->getKey()] ?? 0),
                ));
            });

        return view('storefront.products.index', [
            'products' => $products,
            'categories' => Category::query()
                ->where('status', true)
                ->with('parent.parent')
                ->orderBy('name')
                ->get(['id', 'parent_id', 'name', 'slug']),
            'brands' => $brands,
            'sort' => $sort,
            'brandIds' => $brandIds,
            'availability' => $availability,
            'minimumRating' => $minimumRating,
            'minimumPrice' => $minimumPrice,
            'maximumPrice' => $maximumPrice,
            'catalogMinimumPrice' => $catalogMinimumPrice,
            'catalogMaximumPrice' => $catalogMaximumPrice,
            'attributeFilters' => $attributeFilters,
            'filterAttributes' => $filterAttributes,
            'selectedCategory' => $selectedCategory,
        ]);
    }

    private function normalizedPrice(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return max(0, round((float) $value, 2));
    }

    private function whereAvailable(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->where(function (Builder $query): void {
                $query->where('product_type', '!=', 'variable')
                    ->where('stock_status', '!=', 'out_of_stock')
                    ->where(function (Builder $query): void {
                        $query->where('manage_stock', false)
                            ->orWhere('stock_quantity', '>', 0)
                            ->orWhere('stock_status', 'on_backorder');
                    });
            })->orWhere(function (Builder $query): void {
                $query->where('product_type', 'variable')
                    ->whereHas('variations', fn (Builder $query) => $query
                        ->where('status', true)
                        ->where('stock_status', '!=', 'out_of_stock')
                        ->where(fn (Builder $query) => $query
                            ->where('stock_quantity', '>', 0)
                            ->orWhere('stock_status', 'on_backorder')));
            });
        });
    }

    private function whereUnavailable(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->where(function (Builder $query): void {
                $query->where('product_type', '!=', 'variable')
                    ->where(function (Builder $query): void {
                        $query->where('stock_status', 'out_of_stock')
                            ->orWhere(fn (Builder $query) => $query
                                ->where('manage_stock', true)
                                ->where('stock_quantity', '<=', 0)
                                ->where('stock_status', '!=', 'on_backorder'));
                    });
            })->orWhere(function (Builder $query): void {
                $query->where('product_type', 'variable')
                    ->whereDoesntHave('variations', fn (Builder $query) => $query
                        ->where('status', true)
                        ->where('stock_status', '!=', 'out_of_stock')
                        ->where(fn (Builder $query) => $query
                            ->where('stock_quantity', '>', 0)
                            ->orWhere('stock_status', 'on_backorder')));
            });
        });
    }

    /** @return array<int, int> */
    private function categoryAndDescendantIds(Category $category): array
    {
        $ids = [(int) $category->getKey()];
        $pending = $ids;

        while ($pending !== []) {
            $children = Category::query()
                ->where('status', true)
                ->whereIn('parent_id', $pending)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $pending = array_values(array_diff($children, $ids));
            $ids = array_values(array_unique([...$ids, ...$pending]));
        }

        return $ids;
    }

    private function resolveCategoryPath(string $path): ?Category
    {
        $segments = array_values(array_filter(
            explode('/', trim(urldecode($path), '/')),
            static fn (string $segment): bool => $segment !== '',
        ));

        if ($segments === []) {
            return null;
        }

        $category = null;

        foreach ($segments as $index => $slug) {
            $category = Category::query()
                ->where('status', true)
                ->where('slug', $slug)
                ->when(
                    $index === 0,
                    fn (Builder $query) => $query->whereNull('parent_id'),
                    fn (Builder $query) => $query->where('parent_id', $category?->getKey()),
                )
                ->first();

            if (! $category) {
                return null;
            }
        }

        return $category;
    }

    public function show(Request $request, Product $product): View
    {
        abort_unless($product->status === 'published', 404);

        $product
            ->loadAvg('approvedReviews as review_rating', 'rating')
            ->loadCount('approvedReviews as review_count');
        $product->load(array_merge($this->storefrontRelations(), [
            'approvedReviews' => fn ($query) => $query->latest('published_at')->limit(20),
            'variations.attributeValues.attribute:id,name',
            'vendorListings.vendor:id,name,slug,logo',
            'vendorListings.items.productVariation.attributeValues.attribute:id,name',
        ]));

        $summary = MarketplaceProductPresenter::summarize($product);
        $related = Product::query()
            ->withReviewSummary()
            ->where('status', 'published')
            ->whereKeyNot($product->getKey())
            ->when($product->category_id, fn (Builder $query) => $query->where('category_id', $product->category_id))
            ->with($this->storefrontRelations())
            ->limit(5)
            ->get()
            ->map(fn (Product $product): array => MarketplaceProductPresenter::summarize($product));

        $customer = $request->user()?->role === UserRole::Customer ? $request->user() : null;
        $viewerReview = $customer
            ? ProductReview::query()
                ->where('product_id', $product->getKey())
                ->where('user_id', $customer->getKey())
                ->first()
            : null;
        $hasDeliveredPurchase = $customer
            ? OrderItem::query()
                ->where('product_id', $product->getKey())
                ->whereHas('order', fn (Builder $query) => $query
                    ->where('user_id', $customer->getKey())
                    ->where('status', OrderStatus::Delivered->value))
                ->exists()
            : false;
        $canSubmitReview = $hasDeliveredPurchase
            && (! $viewerReview || $viewerReview->status === ProductReview::STATUS_REJECTED);

        return view('storefront.products.show', compact(
            'product',
            'summary',
            'related',
            'viewerReview',
            'hasDeliveredPurchase',
            'canSubmitReview',
        ));
    }

    /** @return array<int|string, mixed> */
    private function storefrontRelations(): array
    {
        return [
            'brand:id,name,slug',
            'category:id,parent_id,name,slug',
            'variations:id,product_id,sku,regular_price,sale_price,stock_quantity,stock_status,image,status,is_default,sort_order',
            'vendorListings' => fn ($query) => $query->where('status', VendorListingStatus::Approved->value),
            'vendorListings.items' => fn ($query) => $query->where('status', VendorListingItemStatus::Active->value),
            'vendorListings.items.stocks',
        ];
    }
}
