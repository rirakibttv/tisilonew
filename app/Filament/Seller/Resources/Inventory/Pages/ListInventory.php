<?php

namespace App\Filament\Seller\Resources\Inventory\Pages;

use App\Filament\Seller\Resources\Inventory\InventoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventory extends ListRecords
{
    protected static string $resource = InventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
