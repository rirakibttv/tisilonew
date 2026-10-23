<?php

namespace App\Filament\Resources\FundAccounts\Pages;

use App\Filament\Resources\FundAccounts\FundAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFundAccounts extends ListRecords
{
    protected static string $resource = FundAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
