<?php

namespace App\Filament\Resources\LandingPageGroups\Pages;

use App\Filament\Resources\LandingPageGroups\LandingPageGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLandingPageGroup extends EditRecord
{
    protected static string $resource = LandingPageGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
