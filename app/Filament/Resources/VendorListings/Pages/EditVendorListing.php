<?php

namespace App\Filament\Resources\VendorListings\Pages;

use App\Filament\Resources\VendorListings\VendorListingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVendorListing extends EditRecord
{
    protected static string $resource = VendorListingResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
