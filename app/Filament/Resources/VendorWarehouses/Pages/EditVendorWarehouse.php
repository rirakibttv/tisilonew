<?php

namespace App\Filament\Resources\VendorWarehouses\Pages;

use App\Filament\Resources\VendorWarehouses\VendorWarehouseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVendorWarehouse extends EditRecord
{
    protected static string $resource = VendorWarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
