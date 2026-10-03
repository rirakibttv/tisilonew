<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\VendorListingItemStatus;
use App\Enums\VendorListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\SliderGroup;
use App\Support\Storefront\MarketplaceProductPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = $request->string('q')->toString();

        $categories = Category::storefrontNavigation();

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
            ->withReviewSummary()
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

        $activeCategoryChildren = Category::query()
            ->where('status', true)
            ->get(['id', 'parent_id'])
            ->groupBy(fn (Category $category): int => (int) ($category->parent_id ?? 0));

        // Admin-controlled main-category product flows. Every enabled category
        // remains visible even when it does not yet have published products.
        $categorySections = $categories
            ->where('show_on_homepage', true)
            ->map(function (Category $category) use ($activeCategoryChildren, $productEagerLoads): array {
                $categoryProducts = Product::query()
                    ->withReviewSummary()
                    ->where('status', 'published')
                    ->whereIn('category_id', $this->categoryTreeIds($category, $activeCategoryChildren))
                    ->with($productEagerLoads)
                    ->latest()
                    ->limit(12)
                    ->get()
                    ->map(fn (Product $p): array => MarketplaceProductPresenter::summarize($p));

                return [
                    'category' => $category,
                    'products' => $categoryProducts,
                ];
            })->values();

        // Brands for showcase slider
        $brands = Brand::query()
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(12)
            ->get();

        $mainSliderGroup = SliderGroup::query()
            ->active()
            ->where('placement', SliderGroup::MAIN_PLACEMENT)
            ->with(['slides' => fn ($query) => $query->visible()])
            ->first();

        $sliders = $mainSliderGroup?->slides ?? collect();

        $additionalSliderGroups = SliderGroup::query()
            ->active()
            ->where('placement', '!=', SliderGroup::MAIN_PLACEMENT)
            ->with(['slides' => fn ($query) => $query->visible()])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (SliderGroup $group): bool => $group->slides->isNotEmpty())
            ->groupBy('placement');

        $flashSale = FlashSale::query()
            ->currentlyActive()
            ->with(['items' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit(12)
                ->with(['product' => fn ($productQuery) => $productQuery
                    ->where('status', 'published')
                    ->withReviewSummary()
                    ->with($productEagerLoads)])])
            ->latest('starts_at')
            ->first();

        $flashSaleProducts = ($flashSale?->items ?? collect())
            ->filter(fn ($item): bool => $item->product !== null)
            ->map(fn ($item): array => MarketplaceProductPresenter::summarize($item->product))
            ->values();

        // Hot Deal end date (from setting or fallback to 3 days from now)
        $generalSettings = SiteSetting::valuesFor('general');
        $hotDealEndDate = $generalSettings['hot_deal_end_date'] ?? null;
        if (! $hotDealEndDate || strtotime($hotDealEndDate) < time()) {
            $hotDealEndDate = date('Y-m-d H:i:s', strtotime('+3 days'));
        }

        return view('storefront.home', [
            'categories' => $categories,
            'hotDealProducts' => $hotDealProducts,
            'categorySections' => $categorySections,
            'brands' => $brands,
            'sliders' => $sliders,
            'additionalSliderGroups' => $additionalSliderGroups,
            'flashSale' => $flashSale,
            'flashSaleProducts' => $flashSaleProducts,
            'hotDealEndDate' => $hotDealEndDate,
            'search' => $search,
        ]);
    }

    /**
     * @param  Collection<int, Collection<int, Category>>  $childrenByParent
     * @return array<int, int>
     */
    private function categoryTreeIds(Category $root, Collection $childrenByParent): array
    {
        $ids = [];
        $queue = [$root->getKey()];

        while ($queue !== []) {
            $categoryId = (int) array_shift($queue);

            if (isset($ids[$categoryId])) {
                continue;
            }

            $ids[$categoryId] = $categoryId;

            foreach ($childrenByParent->get($categoryId, collect()) as $child) {
                $queue[] = $child->getKey();
            }
        }

        return array_values($ids);
    }
}
