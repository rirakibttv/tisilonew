<?php

namespace App\Filament\Resources;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFlashSales extends ListRecords
{
    protected static string $resource = FlashSaleResource::class;

    protected static ?string $title = 'Flash Sale';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Create Flash Sale')];
    }
}
