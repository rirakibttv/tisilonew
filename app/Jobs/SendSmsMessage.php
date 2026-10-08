<?php

namespace App\Jobs;

use App\Services\SmsGatewayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public string $number,
        public string $message,
    ) {
        $this->onQueue('integrations');
    }

    public function handle(SmsGatewayService $sms): void
    {
        $sms->sendNow($this->number, $this->message);
    }
}
