<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Widgets\Accounts\SupplierOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderWidgets(): array
    {
        return [SupplierOverview::class];
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
