<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\IncompleteOrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\IncompleteOrder;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\BkashPaymentGateway;
use App\Services\CheckoutService;
use App\Services\VisitorAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class BkashPaymentController extends Controller
{
    public function __invoke(
        Request $request,
        BkashPaymentGateway $gateway,
        CheckoutService $checkout,
        VisitorAnalyticsService $analytics,
    ): RedirectResponse {
        $paymentId = trim((string) $request->query('paymentID'));
        $transaction = PaymentTransaction::query()
            ->with('order')
            ->where('gateway', 'bkash')
            ->where('gateway_payment_id', $paymentId)
            ->first();

        abort_unless(
            $transaction
            && hash_equals((string) $transaction->getKey(), (string) $request->session()->get('bkash_payment.transaction_id')),
            404,
        );

        if ($transaction->status === 'completed' && $transaction->order->payment_status === PaymentStatus::Paid) {
            return $this->receipt($transaction->order);
        }

        $callbackStatus = Str::lower(trim((string) $request->query('status')));
        if ($callbackStatus !== 'success') {
            $transaction->update([
                'status' => $callbackStatus === 'cancel' ? 'cancelled' : 'failed',
                'failure_reason' => $callbackStatus === 'cancel'
                    ? 'Customer cancelled the bKash payment.'
                    : 'bKash reported a failed payment.',
            ]);
            $checkout->cancelUnpaid($transaction->order);

            return $this->failed(
                $request,
                $transaction->order,
                $callbackStatus === 'cancel'
                    ? 'bKash পেমেন্ট বাতিল করা হয়েছে। আবার চেষ্টা করুন অথবা ক্যাশ অন ডেলিভারি নির্বাচন করুন।'
                    : 'bKash পেমেন্ট সফল হয়নি। আবার চেষ্টা করুন।',
            );
        }

        try {
            $payload = $gateway->executePayment($paymentId);
            $this->completePayment($transaction, $payload);
        } catch (Throwable $exception) {
            report($exception);
            $transaction->update([
                'status' => 'verification_failed',
                'failure_reason' => str($exception->getMessage())->limit(1000)->toString(),
            ]);

            return $this->failed(
                $request,
                $transaction->order,
                'bKash পেমেন্ট যাচাই সম্পন্ন হয়নি। আপনার অ্যাকাউন্ট থেকে টাকা কাটা হলে নতুন করে পেমেন্ট না করে আমাদের সঙ্গে যোগাযোগ করুন।',
                resetLandingToken: false,
            );
        }

        $order = $transaction->fresh('order')->order;
        $this->completeIncompleteOrder($request, $order);
        if ($order->landing_page_id) {
            $request->session()->put('landing_checkout.'.$order->landing_page_id.'.order_id', $order->getKey());
        } else {
            $request->session()->forget(['store_cart', 'tisilo_incomplete_order_id']);
        }
        $request->session()->forget('bkash_payment');

        try {
            $analytics->record($request, [
                'event_type' => 'purchase',
                'event_id' => 'purchase-'.$order->order_number,
                'order_id' => $order->getKey(),
                'value' => $order->total_amount,
                'metadata' => [
                    'currency' => $order->currency,
                    'invoice_id' => $order->order_number,
                    'payment_method' => 'bkash',
                ],
            ]);
        } catch (Throwable) {
            // Analytics must never block a paid order receipt.
        }

        return $this->receipt($order);
    }

    /** @param array<string, mixed> $payload */
    private function completePayment(PaymentTransaction $transaction, array $payload): void
    {
        $order = $transaction->order;
        $completed = Str::lower((string) ($payload['transactionStatus'] ?? '')) === 'completed';
        $samePayment = hash_equals((string) $transaction->gateway_payment_id, (string) ($payload['paymentID'] ?? ''));
        $sameInvoice = hash_equals($order->order_number, (string) ($payload['merchantInvoiceNumber'] ?? ''));
        $sameCurrency = hash_equals($order->currency ?: 'BDT', (string) ($payload['currency'] ?? ''));
        $sameAmount = (int) round((float) $order->total_amount * 100) === (int) round((float) ($payload['amount'] ?? -1) * 100);

        if (! $completed || ! $samePayment || ! $sameInvoice || ! $sameCurrency || ! $sameAmount || blank($payload['trxID'] ?? null)) {
            throw new \RuntimeException('bKash returned an invalid or incomplete payment confirmation.');
        }

        DB::transaction(function () use ($transaction, $order, $payload): void {
            $locked = PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());
            if ($locked->status === 'completed') {
                return;
            }

            $locked->update([
                'transaction_id' => (string) $payload['trxID'],
                'status' => 'completed',
                'gateway_response' => Arr::only($payload, [
                    'paymentID', 'trxID', 'transactionStatus', 'amount', 'currency',
                    'intent', 'merchantInvoiceNumber', 'paymentExecuteTime', 'statusCode', 'statusMessage',
                ]),
                'failure_reason' => null,
                'completed_at' => now(),
            ]);
            $order->update(['payment_status' => PaymentStatus::Paid]);
        }, attempts: 3);
    }

    private function failed(Request $request, Order $order, string $message, bool $resetLandingToken = true): RedirectResponse
    {
        $request->session()->forget('bkash_payment');
        if ($order->landing_page_id) {
            if ($resetLandingToken) {
                $request->session()->put('landing_checkout.'.$order->landing_page_id, ['token' => (string) Str::uuid()]);
            }

            return redirect(route('store.landing.show', $order->landingPage).'#order-now')
                ->withErrors(['payment_method' => $message]);
        }

        return to_route('store.checkout.index')->withErrors(['payment_method' => $message]);
    }

    private function receipt(Order $order): RedirectResponse
    {
        return redirect(URL::temporarySignedRoute(
            'store.checkout.success',
            now()->addDay(),
            ['order' => $order],
        ));
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
