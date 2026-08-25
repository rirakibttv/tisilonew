<?php

namespace App\Filament\Resources\InventoryStocks\Pages;

use App\Enums\InventoryMovementType;
use App\Filament\Resources\InventoryStocks\InventoryStockResource;
use App\Models\InventoryStock;
use App\Services\Inventory\InventoryService;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryStock extends CreateRecord
{
    protected static string $resource = InventoryStockResource::class;

    protected function afterCreate(): void
    {
        $openingQuantity = (int) ($this->data['opening_quantity'] ?? 0);

        if ($openingQuantity === 0) {
            return;
        }

        /** @var InventoryStock $stock */
        $stock = $this->record;

        app(InventoryService::class)->apply(
            $stock,
            InventoryMovementType::Opening,
            quantityDelta: $openingQuantity,
            performedBy: auth()->user(),
            reason: 'Opening inventory balance',
            idempotencyKey: 'inventory-opening-'.$stock->id,
        );
    }
}
