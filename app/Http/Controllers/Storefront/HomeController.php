<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\VendorListingItemStatus;
use App\Enums\VendorListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Support\Storefront\MarketplaceProductPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = $request->string('q')->toString();

        $categories = Category::query()
            ->where('status', true)
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($query) => $query->where('status', true)->orderBy('sort_order')->orderBy('name'),
                'children.children' => fn ($query) => $query->where('status', true)->orderBy('sort_order')->orderBy('name'),
            ])
            ->withCount(['products' => fn ($query) => $query->where('status', 'published')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $productEagerLoads = [
            'brand:id,name,slug',
            'category:id,parent_id,name,slug',
            'variations:id,product_id,regular_price,sale_price,stock_quantity,status',
            'vendorListings' => fn ($query) => $query->where('status', VendorListingStatus::Approved->value),
            'vendorListings.items' => fn ($query) => $query->where('status', VendorListingItemStatus::Active->value),
            'vendorListings.items.stocks',
        ];

        // All latest products or searched products
        $allProducts = Product::query()
            ->where('status', 'published')
            ->when(
                $request->filled('q'),
                fn ($query) => $query->where('name', 'like', '%'.$search.'%'),
            )
            ->with($productEagerLoads)
            ->orderByDesc('featured')
            ->latest()
            ->limit(24)
            ->get();

        $summarizedProducts = $allProducts->map(
            fn (Product $product): array => MarketplaceProductPresenter::summarize($product)
        );

        // Filter Hot Deals: products with discount or featured
        $hotDealProducts = $summarizedProducts
            ->filter(fn (array $card): bool => ($card['discount'] ?? 0) > 0 || ($card['product']->featured ?? false))
            ->values();

        if ($hotDealProducts->isEmpty()) {
            $hotDealProducts = $summarizedProducts->take(12);
        } else {
            $hotDealProducts = $hotDealProducts->take(12);
        }

        // Category-wise product blocks (matching tisilo.net sections)
        $categorySections = $categories
            ->filter(fn (Category $cat): bool => $cat->products_count > 0)
            ->map(function (Category $category) use ($productEagerLoads): array {
                $descendantIds = $category->children->pluck('id')
                    ->merge($category->children->flatMap->children->pluck('id'))
                    ->push($category->id);

                $categoryProducts = Product::query()
                    ->where('status', 'published')
                    ->whereIn('category_id', $descendantIds)
                    ->with($productEagerLoads)
                    ->latest()
                    ->limit(10)
                    ->get()
                    ->map(fn (Product $p): array => MarketplaceProductPresenter::summarize($p));

                return [
                    'category' => $category,
                    'products' => $categoryProducts,
                ];
            })
            ->filter(fn (array $section): bool => $section['products']->isNotEmpty())
            ->values();

        // Brands for showcase slider
        $brands = Brand::query()
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(12)
            ->get();

        // Hot Deal end date (from setting or fallback to 3 days from now)
        $generalSettings = SiteSetting::valuesFor('general');
        $hotDealEndDate = $generalSettings['hot_deal_end_date'] ?? null;
        if (! $hotDealEndDate || strtotime($hotDealEndDate) < time()) {
            $hotDealEndDate = date('Y-m-d H:i:s', strtotime('+3 days'));
        }

        return view('storefront.home', [
            'categories' => $categories,
            'products' => $summarizedProducts,
            'hotDealProducts' => $hotDealProducts,
            'categorySections' => $categorySections,
            'brands' => $brands,
            'hotDealEndDate' => $hotDealEndDate,
            'search' => $search,
        ]);
    }
}
