<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Models\Order;
use App\Services\BkashPaymentService;
use App\Services\CheckoutService;
use App\Services\LandingCheckoutService;
use App\Services\PaymentMethodService;
use App\Services\ShippingRateService;
use App\Services\VisitorAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class LandingCheckoutController extends Controller
{
    public function quote(Request $request, LandingPage $landingPage, LandingCheckoutService $selection, ShippingRateService $shipping): JsonResponse
    {
        $this->ensurePublished($landingPage);
        $validated = $request->validate($this->selectionRules());
        $cart = $selection->cart($landingPage, $validated);

        return response()->json([
            'unit_price' => $cart->first()['price'],
            'subtotal' => $cart->sum(fn (array $line): float => $line['price'] * $line['quantity']),
            'regions' => $shipping->quotesForCart($cart)->values(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(
        Request $request,
        LandingPage $landingPage,
        LandingCheckoutService $selection,
        ShippingRateService $shipping,
        CheckoutService $checkout,
        BkashPaymentService $bkash,
        PaymentMethodService $paymentMethods,
        VisitorAnalyticsService $analytics,
    ): RedirectResponse {
        $this->ensurePublished($landingPage);
        $sessionKey = 'landing_checkout.'.$landingPage->id;
        $state = $request->session()->get($sessionKey, []);

        try {
            if (! is_string($request->input('checkout_token')) || empty($state['token']) || ! hash_equals($state['token'], $request->input('checkout_token'))) {
                throw ValidationException::withMessages(['checkout_token' => 'ফর্মের মেয়াদ শেষ হয়েছে। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
            }
            // The route holds a session lock, so a repeated submit returns the same receipt.
            if (! empty($state['order_id'])) {
                return $this->receipt(Order::query()->findOrFail($state['order_id']));
            }
            $reference = hash('sha256', $landingPage->id.':'.$state['token']);
            $existing = Order::query()->where('checkout_reference', $reference)->first();
            if ($existing) {
                if ($existing->payment_method === 'bkash' && $existing->payment_status->value !== 'paid') {
                    $transaction = $existing->paymentTransactions()->where('status', 'initiated')->latest('id')->first();
                    if ($transaction?->redirect_url) {
                        $request->session()->put('bkash_payment', [
                            'transaction_id' => $transaction->getKey(),
                            'order_id' => $existing->getKey(),
                        ]);

                        return redirect()->away($transaction->redirect_url);
                    }

                    throw ValidationException::withMessages(['payment_method' => 'আগের bKash payment সম্পন্ন হয়নি। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
                }
                $request->session()->put($sessionKey.'.order_id', $existing->id);

                return $this->receipt($existing);
            }

            $validated = Validator::make($request->all(), [
                ...$this->selectionRules(),
                'customer_name' => ['required', 'string', 'max:255'],
                'customer_phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+\-\s]{8,20}$/'],
                'customer_email' => ['nullable', 'email', 'max:255'],
                'address_line' => ['required', 'string', 'max:500'],
                'district_search' => ['required', 'string', 'max:120'],
                'thana' => ['required', 'string', 'max:120'],
                'shipping_region_id' => ['required', 'integer'],
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
                'payment_method.required' => 'একটি পেমেন্ট পদ্ধতি নির্বাচন করুন।',
                'payment_method.in' => 'নির্বাচিত পেমেন্ট পদ্ধতিটি এখন চালু নেই।',
            ])->validate();

            $cart = $selection->cart($landingPage, $validated);
            $quote = $shipping->quotesForCart($cart)->get((int) $validated['shipping_region_id']);
            if (! $quote) {
                throw ValidationException::withMessages(['shipping_region_id' => 'এই পণ্যের জন্য নির্বাচিত এলাকায় ডেলিভারি চার্জ পাওয়া যায়নি।']);
            }
            $validated['division'] = $quote['division'];
            $validated['district'] = $quote['district'];
            $validated['upazila'] = $validated['thana'];
            $validated['postal_code'] = $quote['postal_code'];
            $validated['landing_page_id'] = $landingPage->id;
            $validated['checkout_reference'] = $reference;
            $decoded = json_decode(urldecode((string) $request->cookie('tisilo_attr')), true);
            $attribution = [];
            foreach (['source', 'medium', 'campaign', 'content', 'term', 'click_source'] as $key) {
                if (is_array($decoded) && isset($decoded[$key]) && is_scalar($decoded[$key])) {
                    $attribution[$key] = str((string) $decoded[$key])->stripTags()->limit(191, '')->toString();
                }
            }
            foreach (['fbp' => '_fbp', 'fbc' => '_fbc'] as $key => $cookie) {
                if (filled($request->cookie($cookie))) {
                    $attribution[$key] = str((string) $request->cookie($cookie))->stripTags()->limit(255, '')->toString();
                }
            }
            $validated['marketing_attribution'] = $attribution ?: null;
            if ($validated['payment_method'] === 'bkash') {
                $payment = DB::transaction(function () use ($checkout, $bkash, $cart, $validated, $quote): array {
                    $order = $checkout->place($cart, $validated, $quote);

                    return ['order' => $order, ...$bkash->initiate($order)];
                }, attempts: 3);
                $request->session()->put('bkash_payment', [
                    'transaction_id' => $payment['transaction']->getKey(),
                    'order_id' => $payment['order']->getKey(),
                ]);

                return redirect()->away($payment['redirect_url']);
            }

            $order = $checkout->place($cart, $validated, $quote);
        } catch (ValidationException $exception) {
            return redirect(route('store.landing.show', $landingPage).'#order-now')
                ->withErrors($exception->errors())->withInput($request->except(['_token', 'checkout_token']));
        } catch (Throwable $exception) {
            report($exception);

            return redirect(route('store.landing.show', $landingPage).'#order-now')
                ->withErrors(['payment_method' => 'bKash payment শুরু করা যায়নি। অনুগ্রহ করে আবার চেষ্টা করুন অথবা ক্যাশ অন ডেলিভারি নির্বাচন করুন।'])
                ->withInput($request->except(['_token', 'checkout_token']));
        }

        $request->session()->put($sessionKey.'.order_id', $order->id);
        try {
            $analytics->record($request, [
                'event_type' => 'purchase', 'event_id' => 'purchase-'.$order->order_number,
                'order_id' => $order->id, 'value' => $order->total_amount,
                'metadata' => ['currency' => $order->currency, 'invoice_id' => $order->order_number, 'payment_method' => $order->payment_method],
            ]);
        } catch (Throwable) {
            // Analytics must not interrupt a confirmed order.
        }

        return $this->receipt($order);
    }

    private function ensurePublished(LandingPage $campaign): void
    {
        abort_unless(LandingPage::query()->published()->whereKey($campaign->id)->exists(), 404);
    }

    private function selectionRules(): array
    {
        return [
            'product_id' => ['required', 'integer'],
            'product_variation_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    private function receipt(Order $order): RedirectResponse
    {
        return redirect(URL::temporarySignedRoute('store.checkout.success', now()->addDay(), ['order' => $order]));
    }
}
