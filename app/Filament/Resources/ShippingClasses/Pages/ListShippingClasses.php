<?php

namespace App\Filament\Resources\ShippingClasses\Pages;

use App\Filament\Resources\ShippingClasses\ShippingClassResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShippingClasses extends ListRecords
{
    protected static string $resource = ShippingClassResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
