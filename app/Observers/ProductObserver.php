<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\FacebookAutoPostService;
use Throwable;

class ProductObserver
{
    public function created(Product $product): void
    {
        $this->queueFacebookPost($product);
    }

    public function updated(Product $product): void
    {
        if (! $product->wasChanged('status') || $product->status !== 'published') {
            return;
        }

        $this->queueFacebookPost($product);
    }

    private function queueFacebookPost(Product $product): void
    {
        try {
            app(FacebookAutoPostService::class)->queueWhenPublished($product);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
