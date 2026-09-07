<?php

namespace App\Jobs;

use App\Services\MetaConversionsApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMetaConversionEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 1800];

    /** @param array<string, mixed> $event */
    public function __construct(
        public string $eventName,
        public array $event,
    ) {
        $this->onQueue('integrations');
    }

    public function handle(MetaConversionsApiService $meta): void
    {
        $meta->sendNow($this->eventName, $this->event);
    }
}
