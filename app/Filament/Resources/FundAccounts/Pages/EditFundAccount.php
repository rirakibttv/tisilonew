<?php

namespace App\Filament\Resources\FundAccounts\Pages;

use App\Filament\Resources\FundAccounts\FundAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFundAccount extends EditRecord
{
    protected static string $resource = FundAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->visible(fn (): bool => ! $this->record->transactions()->exists())];
    }
}
