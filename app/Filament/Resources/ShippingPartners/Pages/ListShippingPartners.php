<?php

namespace App\Filament\Resources\ShippingPartners\Pages;

use App\Filament\Resources\ShippingPartners\ShippingPartnerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShippingPartners extends ListRecords
{
    protected static string $resource = ShippingPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
