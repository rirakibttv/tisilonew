<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\IncompleteOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\IncompleteOrder;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Services\CheckoutService;
use App\Services\ShippingRateService;
use App\Services\VisitorAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Throwable;

class CheckoutController extends Controller
{
    public function index(
        Request $request,
        VisitorAnalyticsService $analytics,
        ShippingRateService $shippingRates,
    ): View|RedirectResponse
    {
        $cart = collect($request->session()->get('store_cart', []));
        if ($cart->isEmpty()) {
            return to_route('store.cart.index')->withErrors(['cart' => 'চেকআউট করার আগে কার্টে পণ্য যোগ করুন।']);
        }

        $regions = $shippingRates->quotesForCart($cart);
        $this->rememberIncompleteOrder($request, $cart);

        try {
            $analytics->record($request, [
                'event_type' => 'initiate_checkout',
                'value' => $this->subtotal($cart),
                'metadata' => ['currency' => 'BDT'],
            ]);
        } catch (Throwable) {
            // Analytics must never block checkout.
        }

        return view('storefront.checkout.index', [
            'lines' => $cart,
            'subtotal' => $this->subtotal($cart),
            'regions' => $regions,
            'checkoutNote' => SiteSetting::valuesFor('general')['checkout_note'] ?? null,
        ]);
    }

    public function store(
        Request $request,
        CheckoutService $checkout,
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
            'payment_method' => ['required', Rule::in(['cod'])],
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
        ]);

        $quote = $regions->get((int) $validated['shipping_region_id']);
        $validated['division'] = $quote['division'];
        $validated['district'] = $quote['district'];
        $validated['upazila'] = $validated['thana'];
        $validated['postal_code'] = $quote['postal_code'];
        $validated['landing_page_id'] = $this->activeLandingPage($request)?->getKey();
        $validated['marketing_attribution'] = $this->marketingAttribution($request);
        $order = $checkout->place($cart, $validated, $quote);

        $this->completeIncompleteOrder($request, $order);
        $request->session()->forget(['store_cart', 'tisilo_incomplete_order_id']);

        try {
            $analytics->record($request, [
                'event_type' => 'purchase',
                'event_id' => 'purchase-'.$order->order_number,
                'order_id' => $order->getKey(),
                'value' => $order->total_amount,
                'metadata' => [
                    'currency' => $order->currency,
                    'invoice_id' => $order->order_number,
                    'payment_method' => $order->payment_method,
                ],
            ]);
        } catch (Throwable) {
            // Analytics must never block order confirmation.
        }

        return redirect(URL::temporarySignedRoute(
            'store.checkout.success',
            now()->addDay(),
            ['order' => $order],
        ));
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

        return $safe ?: null;
    }

    /** @param Collection<string, array<string, mixed>> $cart */
    private function subtotal(Collection $cart): float
    {
        return $cart->sum(fn (array $line): float => (float) $line['price'] * (int) $line['quantity']);
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
