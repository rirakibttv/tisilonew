<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\VendorListingItemStatus;
use App\Enums\VendorListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Storefront\MarketplaceProductPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): View
    {
        $ids = collect($request->session()->get('store_wishlist', []))->map(fn ($id) => (int) $id)->unique();
        $products = Product::query()
            ->where('status', 'published')
            ->whereKey($ids)
            ->with([
                'brand:id,name,slug',
                'category:id,name,slug',
                'variations:id,product_id,regular_price,sale_price,stock_quantity,status',
                'vendorListings' => fn ($query) => $query->where('status', VendorListingStatus::Approved->value),
                'vendorListings.items' => fn ($query) => $query->where('status', VendorListingItemStatus::Active->value),
                'vendorListings.items.stocks',
            ])
            ->get()
            ->sortBy(fn (Product $product) => $ids->search($product->id))
            ->map(fn (Product $product): array => MarketplaceProductPresenter::summarize($product));

        return view('storefront.wishlist.index', compact('products'));
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->status === 'published', 404);
        $ids = collect($request->session()->get('store_wishlist', []));
        if (! $ids->contains($product->id)) {
            $ids->push($product->id);
        }
        $request->session()->put('store_wishlist', $ids->unique()->values()->all());

        return back()->with('success', 'পণ্যটি উইশলিস্টে যোগ হয়েছে।');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $ids = collect($request->session()->get('store_wishlist', []))
            ->reject(fn ($id) => (int) $id === $product->id)
            ->values();
        $request->session()->put('store_wishlist', $ids->all());

        return back()->with('success', 'পণ্যটি উইশলিস্ট থেকে সরানো হয়েছে।');
    }
}
