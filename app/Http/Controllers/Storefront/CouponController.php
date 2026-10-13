<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\CouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function store(Request $request, CouponService $coupons): RedirectResponse
    {
        $validated = $request->validate([
            'coupon_code' => ['required', 'string', 'max:64'],
        ]);
        $cart = collect($request->session()->get('store_cart', []));

        if ($cart->isEmpty()) {
            return to_route('store.cart.index')->withErrors([
                'coupon_code' => __('Add a product to the cart before applying a coupon.'),
            ]);
        }

        $subtotal = $cart->sum(
            fn (array $line): float => (float) $line['price'] * (int) $line['quantity'],
        );
        $quote = $coupons->quote(
            $validated['coupon_code'],
            $subtotal,
            $request->user()?->getKey(),
            $request->user()?->phone,
        );

        $request->session()->put('store_coupon_code', $quote['coupon']->code);

        return back()->withInput()->with('coupon_status', __('Coupon :code applied. You saved :amount.', [
            'code' => $quote['coupon']->code,
            'amount' => '৳'.number_format($quote['discount'], 0),
        ]));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('store_coupon_code');

        return back()->withInput()->with('coupon_status', __('Coupon removed.'));
    }
}
