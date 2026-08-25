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
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): View
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
            ->when(
                $request->filled('category'),
                fn (Builder $query) => $query->whereHas(
                    'category',
                    fn (Builder $query) => $query->where('slug', $request->string('category')->toString()),
                ),
            )
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
        ]);
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
            'variations:id,product_id,regular_price,sale_price,stock_quantity,status,is_default,sort_order',
            'vendorListings' => fn ($query) => $query->where('status', VendorListingStatus::Approved->value),
            'vendorListings.items' => fn ($query) => $query->where('status', VendorListingItemStatus::Active->value),
            'vendorListings.items.stocks',
        ];
    }
}
