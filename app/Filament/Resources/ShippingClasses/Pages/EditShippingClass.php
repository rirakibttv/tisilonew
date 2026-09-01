<?php

namespace App\Filament\Resources\ShippingClasses\Pages;

use App\Filament\Resources\ShippingClasses\ShippingClassResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShippingClass extends EditRecord
{
    protected static string $resource = ShippingClassResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
