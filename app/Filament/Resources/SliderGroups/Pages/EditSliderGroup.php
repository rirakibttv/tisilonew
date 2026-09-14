<?php

namespace App\Filament\Resources\SliderGroups\Pages;

use App\Filament\Resources\SliderGroups\SliderGroupResource;
use App\Models\SliderGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSliderGroup extends EditRecord
{
    protected static string $resource = SliderGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (): bool => $this->record->placement === SliderGroup::MAIN_PLACEMENT
                    || $this->record->slides()->exists()),
        ];
    }
}
