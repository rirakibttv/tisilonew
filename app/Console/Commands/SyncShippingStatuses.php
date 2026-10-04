<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\CourierShipmentService;
use Illuminate\Console\Command;
use Throwable;

class SyncShippingStatuses extends Command
{
    protected $signature = 'shipping:sync-statuses {--limit=100 : Maximum shipments to sync per run}';

    protected $description = 'Refresh active courier shipment statuses from configured partner APIs';

    public function handle(CourierShipmentService $shipments): int
    {
        $synced = 0;
        $failed = 0;
        $partnerIds = array_keys($shipments->availablePartnerOptions());

        if ($partnerIds === []) {
            $this->components->info('No enabled API courier has shipments to sync.');

            return self::SUCCESS;
        }

        Order::query()
            ->with('shippingPartner')
            ->whereIn('shipping_partner_id', $partnerIds)
            ->whereNotNull('tracking_number')
            ->where(function ($query): void {
                $query->whereNull('shipping_status')
                    ->orWhereNotIn('shipping_status', [
                        'delivered', 'cancelled', 'returned', 'return_completed',
                    ]);
            })
            ->orderByRaw('shipping_status_synced_at IS NULL DESC')
            ->orderBy('shipping_status_synced_at')
            ->limit(max(1, min(500, (int) $this->option('limit'))))
            ->get()
            ->each(function (Order $order) use ($shipments, &$synced, &$failed): void {
                try {
                    $order->update([
                        'shipping_status' => $shipments->fetchStatus($order),
                        'shipping_status_synced_at' => now(),
                    ]);
                    $synced++;
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;
                }
            });

        $this->components->info("Shipping status sync complete: {$synced} updated, {$failed} failed.");

        return $failed > 0 && $synced === 0 ? self::FAILURE : self::SUCCESS;
    }
}
