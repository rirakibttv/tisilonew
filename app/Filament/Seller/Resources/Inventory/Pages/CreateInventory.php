<?php

namespace App\Filament\Seller\Resources\Inventory\Pages;

use App\Enums\InventoryMovementType;
use App\Filament\Seller\Resources\Inventory\InventoryResource;
use App\Models\InventoryStock;
use App\Services\Inventory\InventoryService;
use App\Support\SellerAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateInventory extends CreateRecord
{
    protected static string $resource = InventoryResource::class;

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
            performedBy: SellerAccess::user(),
            reason: 'Opening inventory balance',
            idempotencyKey: 'seller-inventory-opening-'.$stock->id,
        );
    }
}
