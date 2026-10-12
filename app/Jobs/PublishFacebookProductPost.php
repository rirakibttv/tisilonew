<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\FacebookAutoPostService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishFacebookProductPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $productId)
    {
        $this->onQueue('integrations');
    }

    public function handle(FacebookAutoPostService $autoPost): void
    {
        $product = Product::query()->find($this->productId);
        if (! $product) {
            return;
        }

        $autoPost->publishProduct($product);
    }
}
