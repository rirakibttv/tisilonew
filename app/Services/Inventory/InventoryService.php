<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function apply(
        InventoryStock $stock,
        InventoryMovementType $type,
        int $quantityDelta = 0,
        int $reservedDelta = 0,
        ?User $performedBy = null,
        ?Model $reference = null,
        ?string $reason = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): InventoryMovement {
        return DB::transaction(function () use (
            $stock,
            $type,
            $quantityDelta,
            $reservedDelta,
            $performedBy,
            $reference,
            $reason,
            $idempotencyKey,
            $metadata,
        ): InventoryMovement {
            if ($idempotencyKey !== null) {
                $existing = InventoryMovement::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $lockedStock = InventoryStock::query()
                ->with('item')
                ->lockForUpdate()
                ->findOrFail($stock->getKey());

            $quantityBefore = $lockedStock->quantity;
            $reservedBefore = $lockedStock->reserved_quantity;
            $quantityAfter = $quantityBefore + $quantityDelta;
            $reservedAfter = $reservedBefore + $reservedDelta;

            if ($quantityAfter < 0 || $reservedAfter < 0) {
                throw new DomainException('Inventory quantities cannot become negative.');
            }

            if (
                $reservedAfter > $quantityAfter
                && ! $lockedStock->item->backorders_allowed
            ) {
                throw new DomainException('The requested reservation exceeds available stock.');
            }

            $lockedStock->forceFill([
                'quantity' => $quantityAfter,
                'reserved_quantity' => $reservedAfter,
            ])->save();

            return InventoryMovement::query()->create([
                'inventory_stock_id' => $lockedStock->id,
                'performed_by' => $performedBy?->id,
                'type' => $type,
                'quantity_delta' => $quantityDelta,
                'reserved_delta' => $reservedDelta,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'reserved_before' => $reservedBefore,
                'reserved_after' => $reservedAfter,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'idempotency_key' => $idempotencyKey,
                'reason' => $reason,
                'metadata' => $metadata ?: null,
                'created_at' => now(),
            ]);
        }, attempts: 3);
    }
}
