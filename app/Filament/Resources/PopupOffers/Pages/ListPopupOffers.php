<?php

namespace App\Filament\Resources\PopupOffers\Pages;

use App\Filament\Resources\PopupOffers\PopupOfferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPopupOffers extends ListRecords
{
    protected static string $resource = PopupOfferResource::class;

    protected static ?string $title = 'PopUp Offer';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Create Popup Offer')];
    }
}
