<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\IncompleteOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\IncompleteOrder;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Services\CheckoutService;
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
    public function index(Request $request, VisitorAnalyticsService $analytics): View|RedirectResponse
    {
        $cart = collect($request->session()->get('store_cart', []));
        if ($cart->isEmpty()) {
            return to_route('store.cart.index')->withErrors(['cart' => 'চেকআউট করার আগে কার্টে পণ্য যোগ করুন।']);
        }

        $zones = $this->shippingZones();
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
            'zones' => $zones,
            'checkoutNote' => SiteSetting::valuesFor('general')['checkout_note'] ?? null,
        ]);
    }

    public function store(
        Request $request,
        CheckoutService $checkout,
        VisitorAnalyticsService $analytics,
    ): RedirectResponse {
        $cart = collect($request->session()->get('store_cart', []));
        $zones = $this->shippingZones();
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'address_line' => ['required', 'string', 'max:500'],
            'district' => ['required', 'string', 'max:120'],
            'upazila' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'shipping_zone' => ['required', Rule::in(array_keys($zones))],
            'payment_method' => ['required', Rule::in(['cod'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms' => ['accepted'],
        ], [
            'customer_name.required' => 'আপনার নাম লিখুন।',
            'customer_phone.required' => 'মোবাইল নম্বর লিখুন।',
            'customer_phone.regex' => 'সঠিক মোবাইল নম্বর লিখুন।',
            'address_line.required' => 'সম্পূর্ণ ডেলিভারি ঠিকানা লিখুন।',
            'district.required' => 'জেলা লিখুন।',
            'shipping_zone.required' => 'ডেলিভারি এলাকা নির্বাচন করুন।',
            'terms.accepted' => 'অর্ডার করতে শর্তাবলিতে সম্মতি দিন।',
        ]);

        $zone = $zones[$validated['shipping_zone']];
        $order = $checkout->place($cart, $validated, $zone);

        $this->completeIncompleteOrder($request, $order);
        $request->session()->forget(['store_cart', 'tisilo_incomplete_order_id']);

        try {
            $analytics->record($request, [
                'event_type' => 'purchase',
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

    /** @return array<string, array<string, mixed>> */
    private function shippingZones(): array
    {
        return collect(SiteSetting::valuesFor('shipping')['zones'] ?? [])
            ->filter(fn (array $zone): bool => (bool) ($zone['status'] ?? false))
            ->mapWithKeys(fn (array $zone): array => [
                (string) $zone['name'] => [
                    'name' => (string) $zone['name'],
                    'amount' => (float) ($zone['amount'] ?? 0),
                    'estimated_days' => (int) ($zone['estimated_days'] ?? 1),
                ],
            ])
            ->whenEmpty(fn (Collection $zones): Collection => $zones->put('Standard Delivery', [
                'name' => 'Standard Delivery',
                'amount' => 0,
                'estimated_days' => 3,
            ]))
            ->all();
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
