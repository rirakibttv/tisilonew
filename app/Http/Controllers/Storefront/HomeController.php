<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\VendorListingItemStatus;
use App\Enums\VendorListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Storefront\MarketplaceProductPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $categories = Category::query()
            ->where('status', true)
            ->whereNull('parent_id')
            ->withCount(['products' => fn ($query) => $query->where('status', 'published')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(10)
            ->get();

        $products = Product::query()
            ->where('status', 'published')
            ->when(
                $request->filled('q'),
                fn ($query) => $query->where('name', 'like', '%'.$request->string('q').'%'),
            )
            ->with([
                'brand:id,name,slug',
                'category:id,name,slug',
                'variations:id,product_id,regular_price,sale_price,stock_quantity,status',
                'vendorListings' => fn ($query) => $query
                    ->where('status', VendorListingStatus::Approved->value),
                'vendorListings.items' => fn ($query) => $query
                    ->where('status', VendorListingItemStatus::Active->value),
                'vendorListings.items.stocks',
            ])
            ->orderByDesc('featured')
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (Product $product): array => MarketplaceProductPresenter::summarize($product));

        return view('storefront.home', [
            'categories' => $categories,
            'products' => $products,
            'search' => $request->string('q')->toString(),
        ]);
    }
}
