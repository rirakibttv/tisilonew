<?php

namespace App\Filament\Seller\Resources\Products\Pages;

use App\Enums\VendorListingStatus;
use App\Filament\Seller\Resources\Products\ProductResource;
use App\Support\SellerAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    public function getTitle(): string
    {
        return 'Add Product';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['vendor_id'] = SellerAccess::currentVendor()?->getKey();
        $data['status'] = in_array($data['status'] ?? null, [
            VendorListingStatus::Draft->value,
            VendorListingStatus::Pending->value,
        ], true) ? $data['status'] : VendorListingStatus::Pending->value;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
