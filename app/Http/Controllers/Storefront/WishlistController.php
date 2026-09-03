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
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    public function index(Request $request): View
    {
        $ids = collect(Auth::check()
            ? Auth::user()->wishlistProducts()->pluck('products.id')
            : $request->session()->get('store_wishlist', []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if (Auth::check()) {
            $request->session()->put('store_wishlist', $ids->all());
        }

        $products = Product::query()
            ->whereIn('id', $ids)
            ->where('status', 'published')
            ->with([
                'brand:id,name,slug',
                'category:id,name,slug',
                'variations:id,product_id,regular_price,sale_price,stock_quantity,status',
                'vendorListings' => fn ($query) => $query->where('status', VendorListingStatus::Approved->value),
                'vendorListings.items' => fn ($query) => $query->where('status', VendorListingItemStatus::Active->value),
                'vendorListings.items.stocks',
            ])
            ->get()
            ->sortBy(fn (Product $product): int => $ids->search($product->id))
            ->map(fn (Product $product): array => MarketplaceProductPresenter::summarize($product));

        return view('storefront.wishlist.index', compact('products'));
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->status === 'published', 404);

        if (Auth::check()) {
            Auth::user()->wishlistProducts()->syncWithoutDetaching([$product->id]);
            $request->session()->put(
                'store_wishlist',
                Auth::user()->wishlistProducts()->pluck('products.id')->map(fn ($id): int => (int) $id)->all(),
            );
        } else {
            $wishlist = collect($request->session()->get('store_wishlist', []))
                ->push($product->id)
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            $request->session()->put('store_wishlist', $wishlist);
        }

        return back()->with('status', 'পণ্যটি উইশলিস্টে যোগ হয়েছে।');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        if (Auth::check()) {
            Auth::user()->wishlistProducts()->detach($product->id);
            $request->session()->put(
                'store_wishlist',
                Auth::user()->wishlistProducts()->pluck('products.id')->map(fn ($id): int => (int) $id)->all(),
            );
        } else {
            $wishlist = collect($request->session()->get('store_wishlist', []))
                ->reject(fn ($id): bool => (int) $id === $product->id)
                ->values()
                ->all();

            $request->session()->put('store_wishlist', $wishlist);
        }

        return back()->with('status', 'পণ্যটি উইশলিস্ট থেকে সরানো হয়েছে।');
    }
}
