<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;

class BkashPaymentService
{
    public function __construct(private readonly BkashPaymentGateway $gateway) {}

    /** @return array{transaction: PaymentTransaction, redirect_url: string} */
    public function initiate(Order $order): array
    {
        $transaction = PaymentTransaction::query()->create([
            'order_id' => $order->getKey(),
            'gateway' => 'bkash',
            'merchant_invoice_number' => $order->order_number,
            'status' => 'initiating',
            'amount' => $order->total_amount,
            'currency' => $order->currency ?: 'BDT',
            'initiated_at' => now(),
        ]);

        $payment = $this->gateway->createPayment($order, route('store.payments.bkash.callback'));
        $transaction->update([
            'gateway_payment_id' => $payment['payment_id'],
            'status' => 'initiated',
            'redirect_url' => $payment['redirect_url'],
            'gateway_response' => $payment['response'],
        ]);
        $order->update(['payment_status' => PaymentStatus::Pending]);

        return ['transaction' => $transaction->fresh(), 'redirect_url' => $payment['redirect_url']];
    }
}
