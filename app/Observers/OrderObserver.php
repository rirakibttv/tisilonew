<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\GoogleAnalyticsService;
use App\Services\MetaConversionsApiService;
use App\Services\SmsGatewayService;
use App\Services\VisitorAnalyticsService;
use Throwable;

class OrderObserver
{
    public function created(Order $order): void
    {
        try {
            app(SmsGatewayService::class)->queueOrderPlaced($order);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function updated(Order $order): void
    {
        $statusChanged = $order->wasChanged('status');
        $paymentStatusChanged = $order->wasChanged('payment_status');
        if (! $statusChanged && ! $paymentStatusChanged) {
            return;
        }

        $isVerifiedWebsiteOrder = $order->channel === 'website'
            && ($order->status === OrderStatus::Confirmed || $order->payment_status === PaymentStatus::Paid);
        if ($isVerifiedWebsiteOrder) {
            try {
                app(VisitorAnalyticsService::class)->recordVerifiedOrderPurchase($order);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        if (! $statusChanged) {
            return;
        }

        try {
            app(MetaConversionsApiService::class)->queueOrderStatus($order);
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            app(GoogleAnalyticsService::class)->queueOrderStatus($order);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
