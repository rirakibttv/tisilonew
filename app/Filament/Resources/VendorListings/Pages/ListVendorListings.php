<?php

namespace App\Filament\Resources\VendorListings\Pages;

use App\Filament\Resources\VendorListings\VendorListingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVendorListings extends ListRecords
{
    protected static string $resource = VendorListingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
