<?php

namespace App\Filament\Seller\Resources\Orders\Pages;

use App\Filament\Seller\Resources\Orders\OrderResource;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;
}
