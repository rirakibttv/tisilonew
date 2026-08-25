<?php

namespace App\Filament\Resources\VendorListings\Pages;

use App\Filament\Resources\VendorListings\VendorListingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVendorListing extends CreateRecord
{
    protected static string $resource = VendorListingResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
