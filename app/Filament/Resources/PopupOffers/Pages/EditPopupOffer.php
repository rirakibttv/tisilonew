<?php

namespace App\Filament\Resources\PopupOffers\Pages;

use App\Filament\Resources\PopupOffers\PopupOfferResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPopupOffer extends EditRecord
{
    protected static string $resource = PopupOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
