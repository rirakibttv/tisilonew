<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\GoogleAnalyticsService;
use App\Services\MetaConversionsApiService;
use Throwable;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
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
