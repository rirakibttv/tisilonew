<?php

namespace App\Filament\Resources\ShippingRegions\Pages;

use App\Filament\Resources\ShippingRegions\ShippingRegionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShippingRegions extends ListRecords
{
    protected static string $resource = ShippingRegionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
