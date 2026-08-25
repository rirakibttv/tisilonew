<?php

namespace App\Filament\Resources\ProductTags\Pages;

use App\Filament\Resources\ProductTags\ProductTagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductTags extends ListRecords
{
    protected static string $resource = ProductTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
