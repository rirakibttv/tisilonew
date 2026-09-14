<?php

namespace App\Filament\Resources\SliderGroups\Pages;

use App\Filament\Resources\SliderGroups\SliderGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSliderGroups extends ListRecords
{
    protected static string $resource = SliderGroupResource::class;

    protected static ?string $title = 'Slider Panel';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Create Master Slider')];
    }
}
