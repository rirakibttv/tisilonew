<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\VendorListingItemStatus;
use App\Enums\VendorListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Storefront\MarketplaceProductPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->filled('category')) {
            $category = Category::query()
                ->where('status', true)
                ->where('slug', $request->string('category')->toString())
                ->first();

            if ($category) {
                $query = $request->except(['category', 'page']);
                $url = $category->permalink.($query === [] ? '' : '?'.http_build_query($query));

                return redirect()->to($url, 301);
            }
        }

        return $this->catalog($request);
    }

    public function category(Request $request, Category $category, ?string $categoryPath = null): View|RedirectResponse
    {
        abort_unless($category->status, 404);
        abort_if(filled($categoryPath), 404);

        $requestedPath = (string) (parse_url($request->getRequestUri(), PHP_URL_PATH) ?: '');

        if (! str_ends_with($requestedPath, '/')) {
            $query = $request->getQueryString();

            return redirect()->to($category->permalink.($query ? '?'.$query : ''), 301);
        }

        return $this->catalog($request, $category);
    }

    private function catalog(Request $request, ?Category $selectedCategory = null): View
    {
        $sort = in_array($request->string('sort')->toString(), ['latest', 'price_low', 'price_high', 'name'], true)
            ? $request->string('sort')->toString()
            : 'latest';

        $query = Product::query()
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
            ->when($selectedCategory, function (Builder $query) use ($selectedCategory): void {
                $query->whereIn('category_id', $this->categoryAndDescendantIds($selectedCategory));
            })
            ->with($this->storefrontRelations());

        match ($sort) {
            'price_low' => $query->orderByRaw('COALESCE(sale_price, regular_price) asc'),
            'price_high' => $query->orderByRaw('COALESCE(sale_price, regular_price) desc'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('featured')->latest(),
        };

        $products = $query->paginate(16)->withQueryString();
        $products->through(fn (Product $product): array => MarketplaceProductPresenter::summarize($product));

        return view('storefront.products.index', [
            'products' => $products,
            'categories' => Category::query()->where('status', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'sort' => $sort,
            'selectedCategory' => $selectedCategory,
        ]);
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

    public function show(Product $product): View
    {
        abort_unless($product->status === 'published', 404);

        $product->load(array_merge($this->storefrontRelations(), [
            'variations.attributeValues.attribute:id,name',
            'vendorListings.vendor:id,name,slug,logo',
            'vendorListings.items.productVariation.attributeValues.attribute:id,name',
        ]));

        $summary = MarketplaceProductPresenter::summarize($product);
        $related = Product::query()
            ->where('status', 'published')
            ->whereKeyNot($product->getKey())
            ->when($product->category_id, fn (Builder $query) => $query->where('category_id', $product->category_id))
            ->with($this->storefrontRelations())
            ->limit(5)
            ->get()
            ->map(fn (Product $product): array => MarketplaceProductPresenter::summarize($product));

        return view('storefront.products.show', compact('product', 'summary', 'related'));
    }

    /** @return array<int|string, mixed> */
    private function storefrontRelations(): array
    {
        return [
            'brand:id,name,slug',
            'category:id,name,slug',
            'variations:id,product_id,sku,regular_price,sale_price,stock_quantity,stock_status,image,status,is_default,sort_order',
            'vendorListings' => fn ($query) => $query->where('status', VendorListingStatus::Approved->value),
            'vendorListings.items' => fn ($query) => $query->where('status', VendorListingItemStatus::Active->value),
            'vendorListings.items.stocks',
        ];
    }
}
