<?php

namespace App\Filament\Resources\BannerSliders\Pages;

use App\Filament\Resources\BannerSliders\BannerSliderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBannerSlider extends EditRecord
{
    protected static string $resource = BannerSliderResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
