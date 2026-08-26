<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPendingOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'Pending Order';

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->pending();
    }
}
