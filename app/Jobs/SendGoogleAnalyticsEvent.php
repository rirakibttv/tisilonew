<?php

namespace App\Jobs;

use App\Services\GoogleAnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendGoogleAnalyticsEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 1800];

    /** @param array<string, mixed> $payload */
    public function __construct(public array $payload)
    {
        $this->onQueue('integrations');
    }

    public function handle(GoogleAnalyticsService $analytics): void
    {
        $analytics->sendNow($this->payload);
    }
}
