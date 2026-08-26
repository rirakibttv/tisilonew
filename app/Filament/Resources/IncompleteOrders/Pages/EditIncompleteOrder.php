<?php

namespace App\Filament\Resources\IncompleteOrders\Pages;

use App\Filament\Resources\IncompleteOrders\IncompleteOrderResource;
use Filament\Resources\Pages\EditRecord;

class EditIncompleteOrder extends EditRecord
{
    protected static string $resource = IncompleteOrderResource::class;
}
