<?php

namespace App\Filament\Seller\Resources\Warehouses\Pages;

use App\Filament\Seller\Resources\Warehouses\WarehouseResource;
use App\Support\SellerAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateWarehouse extends CreateRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['vendor_id'] = SellerAccess::currentVendor()?->getKey();

        return $data;
    }
}
