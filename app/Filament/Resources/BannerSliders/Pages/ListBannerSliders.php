<?php

namespace App\Filament\Resources\BannerSliders\Pages;

use App\Filament\Resources\BannerSliders\BannerSliderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBannerSliders extends ListRecords
{
    protected static string $resource = BannerSliderResource::class;

    protected static ?string $title = 'Banner & Slider';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Create New Slider')];
    }
}
