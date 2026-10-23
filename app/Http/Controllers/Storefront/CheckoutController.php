<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\IncompleteOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\IncompleteOrder;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Services\BkashPaymentService;
use App\Services\CheckoutService;
use App\Services\CouponService;
use App\Services\PaymentMethodService;
use App\Services\ShippingRateService;
use App\Services\VisitorAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutController extends Controller
{
    public function index(
        Request $request,
        VisitorAnalyticsService $analytics,
        ShippingRateService $shippingRates,
        PaymentMethodService $paymentMethods,
        CouponService $coupons,
    ): View|RedirectResponse {
        $cart = collect($request->session()->get('store_cart', []));
        if ($cart->isEmpty()) {
            return to_route('store.cart.index')->withErrors(['cart' => 'চেকআউট করার আগে কার্টে পণ্য যোগ করুন।']);
        }

        $regions = $shippingRates->quotesForCart($cart);
        $subtotal = $this->subtotal($cart);
        $couponQuote = null;
        $couponError = null;
        $couponCode = (string) $request->session()->get('store_coupon_code', '');
        if ($couponCode !== '') {
            try {
                $couponQuote = $coupons->quote(
                    $couponCode,
                    $subtotal,
                    $request->user()?->getKey(),
                    $request->user()?->phone,
                );
            } catch (ValidationException $exception) {
                $couponError = collect($exception->errors())->flatten()->first();
                $request->session()->forget('store_coupon_code');
            }
        }
        $this->rememberIncompleteOrder($request, $cart);

        $metaBrowserEvents = [$this->checkoutTrackingEvent(
            $request,
            $analytics,
            $cart,
            (float) ($couponQuote['discount'] ?? 0),
            $couponQuote['coupon']->code ?? null,
        )];

        $enabledPaymentMethods = $paymentMethods->enabled();
        $preferredPaymentMethod = $request->user()?->paymentPreference?->default_method;

        return view('storefront.checkout.index', [
            'lines' => $cart,
            'subtotal' => $subtotal,
            'coupon' => $couponQuote['coupon'] ?? null,
            'couponDiscount' => (float) ($couponQuote['discount'] ?? 0),
            'couponError' => $couponError,
            'regions' => $regions,
            'checkoutNote' => collect(SiteSetting::valuesFor('pages')['pages'] ?? [])
                ->firstWhere('slug', 'policies-and-notes')['description'] ?? null,
            'paymentMethods' => $enabledPaymentMethods,
            'defaultPaymentMethod' => array_key_exists((string) $preferredPaymentMethod, $enabledPaymentMethods)
                ? $preferredPaymentMethod
                : $paymentMethods->default(),
            'metaBrowserEvents' => $metaBrowserEvents,
        ]);
    }

    public function store(
        Request $request,
        CheckoutService $checkout,
        BkashPaymentService $bkash,
        PaymentMethodService $paymentMethods,
        VisitorAnalyticsService $analytics,
        ShippingRateService $shippingRates,
    ): RedirectResponse {
        $cart = collect($request->session()->get('store_cart', []));
        $regions = $shippingRates->quotesForCart($cart);
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'address_line' => ['required', 'string', 'max:500'],
            'district_search' => ['required', 'string', 'max:120'],
            'thana' => ['required', 'string', 'max:120'],
            'shipping_region_id' => ['required', 'integer', Rule::in($regions->keys()->all())],
            'payment_method' => ['required', Rule::in($paymentMethods->keys())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'customer_name.required' => 'আপনার নাম লিখুন।',
            'customer_phone.required' => 'মোবাইল নম্বর লিখুন।',
            'customer_phone.regex' => 'সঠিক মোবাইল নম্বর লিখুন।',
            'address_line.required' => 'সম্পূর্ণ ডেলিভারি ঠিকানা লিখুন।',
            'district_search.required' => 'জেলার নাম লিখে তালিকা থেকে নির্বাচন করুন।',
            'thana.required' => 'থানা বা উপজেলার নাম লিখুন।',
            'shipping_region_id.required' => 'জেলার নাম লিখে তালিকা থেকে নির্বাচন করুন।',
            'shipping_region_id.in' => 'এই এলাকায় নির্বাচিত পণ্যের shipping rate পাওয়া যায়নি।',
            'payment_method.required' => 'একটি পেমেন্ট পদ্ধতি নির্বাচন করুন।',
            'payment_method.in' => 'নির্বাচিত পেমেন্ট পদ্ধতিটি এখন চালু নেই।',
        ]);

        $quote = $regions->get((int) $validated['shipping_region_id']);
        $validated['division'] = $quote['division'];
        $validated['district'] = $quote['district'];
        $validated['upazila'] = $validated['thana'];
        $validated['postal_code'] = $quote['postal_code'];
        $validated['landing_page_id'] = $this->activeLandingPage($request)?->getKey();
        $validated['marketing_attribution'] = $this->marketingAttribution($request);
        $validated['coupon_code'] = $request->session()->get('store_coupon_code');
        if ($validated['payment_method'] === 'bkash') {
            try {
                $payment = DB::transaction(function () use ($checkout, $bkash, $cart, $validated, $quote): array {
                    $order = $checkout->place($cart, $validated, $quote);

                    return ['order' => $order, ...$bkash->initiate($order)];
                }, attempts: 3);
            } catch (Throwable $exception) {
                report($exception);

                return back()->withInput()->withErrors([
                    'payment_method' => 'bKash payment শুরু করা যায়নি। অনুগ্রহ করে আবার চেষ্টা করুন অথবা ক্যাশ অন ডেলিভারি নির্বাচন করুন।',
                ]);
            }

            $request->session()->put('bkash_payment', [
                'transaction_id' => $payment['transaction']->getKey(),
                'order_id' => $payment['order']->getKey(),
            ]);

            return redirect()->away($payment['redirect_url']);
        }

        $order = $checkout->place($cart, $validated, $quote);

        $this->completeIncompleteOrder($request, $order);
        $request->session()->forget([
            'store_cart',
            'store_coupon_code',
            'tisilo_incomplete_order_id',
            'meta_checkout_tracking',
        ]);

        $purchaseEvent = $analytics->purchaseEvent($order);
        try {
            $analytics->record($request, $purchaseEvent);
        } catch (Throwable) {
            // Analytics must never block order confirmation.
        }

        return redirect(URL::temporarySignedRoute(
            'store.checkout.success',
            now()->addDay(),
            ['order' => $order],
        ))->with('meta_browser_events', [$purchaseEvent]);
    }

    public function success(Order $order): View
    {
        return view('storefront.checkout.success', compact('order'));
    }

    private function activeLandingPage(Request $request): ?LandingPage
    {
        $landingPageIds = collect($request->session()->get('store_cart', []))
            ->pluck('landing_page_id')
            ->filter()
            ->unique();

        if ($landingPageIds->count() !== 1) {
            return null;
        }

        return LandingPage::query()->published()->find($landingPageIds->first());
    }

    /** @return array<string, string>|null */
    private function marketingAttribution(Request $request): ?array
    {
        $decoded = json_decode(urldecode((string) $request->cookie('tisilo_attr')), true);
        $decoded = is_array($decoded) ? $decoded : [];

        $safe = [];
        foreach (['source', 'medium', 'campaign', 'content', 'term', 'click_source'] as $key) {
            if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
                $safe[$key] = str((string) $decoded[$key])->stripTags()->limit(191, '')->toString();
            }
        }
        foreach (['fbp' => '_fbp', 'fbc' => '_fbc'] as $key => $cookie) {
            if (filled($request->cookie($cookie))) {
                $safe[$key] = str((string) $request->cookie($cookie))->stripTags()->limit(255, '')->toString();
            }
        }
        if (filled($request->cookie('tisilo_ga_cid'))) {
            $safe['ga_client_id'] = str((string) $request->cookie('tisilo_ga_cid'))->stripTags()->limit(255, '')->toString();
        }

        return $safe ?: null;
    }

    /** @param Collection<string, array<string, mixed>> $cart */
    private function subtotal(Collection $cart): float
    {
        return $cart->sum(fn (array $line): float => (float) $line['price'] * (int) $line['quantity']);
    }

    /**
     * Record InitiateCheckout once for the current cart and return the exact
     * same event ID and commerce data for the browser Pixel event.
     *
     * @param  Collection<string, array<string, mixed>>  $cart
     * @return array<string, mixed>
     */
    private function checkoutTrackingEvent(
        Request $request,
        VisitorAnalyticsService $analytics,
        Collection $cart,
        float $discount = 0,
        ?string $couponCode = null,
    ): array {
        $contents = $cart->map(fn (array $line): array => [
            'id' => (string) $line['product_id'],
            'quantity' => (int) $line['quantity'],
            'item_price' => round((float) $line['price'], 2),
        ])->values();
        $fingerprint = hash('sha256', json_encode([
            'items' => $contents
                ->sortBy(fn (array $item): string => $item['id'].'|'.$item['item_price'].'|'.$item['quantity'])
                ->values()
                ->all(),
            'coupon' => $couponCode,
            'discount' => round($discount, 2),
        ], JSON_THROW_ON_ERROR));
        $tracking = $request->session()->get('meta_checkout_tracking');

        if (! is_array($tracking) || ($tracking['fingerprint'] ?? null) !== $fingerprint) {
            $tracking = [
                'fingerprint' => $fingerprint,
                'event' => [
                    'event_type' => 'initiate_checkout',
                    'event_id' => 'initiate-checkout-'.Str::uuid(),
                    'value' => round(max(0, $this->subtotal($cart) - $discount), 2),
                    'metadata' => [
                        'currency' => 'BDT',
                        'coupon_code' => $couponCode,
                        'content_ids' => $contents->pluck('id')->unique()->values()->all(),
                        'contents' => $contents->all(),
                        'num_items' => $contents->sum('quantity'),
                    ],
                ],
            ];
            $request->session()->put('meta_checkout_tracking', $tracking);

            try {
                $analytics->record($request, $tracking['event']);
            } catch (Throwable) {
                // Analytics must never block checkout.
            }
        }

        return $tracking['event'];
    }

    /** @param Collection<string, array<string, mixed>> $cart */
    private function rememberIncompleteOrder(Request $request, Collection $cart): void
    {
        $sessionId = hash('sha256', $request->session()->getId());
        $incomplete = IncompleteOrder::query()
            ->where('session_id', $sessionId)
            ->whereIn('status', [
                IncompleteOrderStatus::Incomplete->value,
                IncompleteOrderStatus::Contacted->value,
                IncompleteOrderStatus::Recovered->value,
            ])
            ->latest('id')
            ->first() ?? new IncompleteOrder(['session_id' => $sessionId]);

        $incomplete->fill([
            'user_id' => auth()->id(),
            'items' => $cart->map(fn (array $line): array => [
                'key' => $line['key'],
                'product_id' => $line['product_id'],
                'name' => $line['name'],
                'quantity' => $line['quantity'],
                'price' => $line['price'],
            ])->values()->all(),
            'total_amount' => $this->subtotal($cart),
            'status' => IncompleteOrderStatus::Incomplete,
            'last_activity_at' => now(),
        ])->save();

        $request->session()->put('tisilo_incomplete_order_id', $incomplete->getKey());
    }

    private function completeIncompleteOrder(Request $request, Order $order): void
    {
        IncompleteOrder::query()
            ->when(
                $request->session()->get('tisilo_incomplete_order_id'),
                fn ($query, $id) => $query->whereKey($id),
                fn ($query) => $query->where('session_id', hash('sha256', $request->session()->getId())),
            )
            ->where('status', IncompleteOrderStatus::Incomplete->value)
            ->latest('id')
            ->first()?->update([
                'converted_order_id' => $order->getKey(),
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'customer_phone' => $order->customer_phone,
                'customer_address' => collect($order->shipping_address)->filter()->join(', '),
                'status' => IncompleteOrderStatus::Converted,
                'recovered_at' => now(),
            ]);
    }
}
