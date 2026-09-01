<?php

namespace App\Filament\Resources\ShippingPartners\Pages;

use App\Filament\Resources\ShippingPartners\ShippingPartnerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShippingPartner extends EditRecord
{
    protected static string $resource = ShippingPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
