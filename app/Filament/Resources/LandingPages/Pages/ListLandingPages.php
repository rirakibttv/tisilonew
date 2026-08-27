<?php

namespace App\Filament\Resources\LandingPages\Pages;

use App\Filament\Resources\LandingPages\LandingPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLandingPages extends ListRecords
{
    protected static string $resource = LandingPageResource::class;

    protected static ?string $title = 'Landing Page Campaigns';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Create Landing Page')];
    }
}
