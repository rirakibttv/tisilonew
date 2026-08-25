<?php

namespace App\Filament\Resources\VendorWarehouses\Pages;

use App\Filament\Resources\VendorWarehouses\VendorWarehouseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVendorWarehouses extends ListRecords
{
    protected static string $resource = VendorWarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
