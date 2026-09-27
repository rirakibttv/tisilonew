<?php

namespace App\Filament\Seller\Resources\Orders\Pages;

use App\Filament\Seller\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;
}
