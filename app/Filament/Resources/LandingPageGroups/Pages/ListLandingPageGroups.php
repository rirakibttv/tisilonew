<?php

namespace App\Filament\Resources\LandingPageGroups\Pages;

use App\Filament\Resources\LandingPageGroups\LandingPageGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLandingPageGroups extends ListRecords
{
    protected static string $resource = LandingPageGroupResource::class;
    protected static ?string $title = 'Landing Page Groups';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Create Group')];
    }
}
