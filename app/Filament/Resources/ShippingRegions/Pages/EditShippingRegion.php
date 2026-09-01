<?php

namespace App\Filament\Resources\ShippingRegions\Pages;

use App\Filament\Resources\ShippingRegions\ShippingRegionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShippingRegion extends EditRecord
{
    protected static string $resource = ShippingRegionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
