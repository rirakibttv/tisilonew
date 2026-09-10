<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\Pages\Concerns\CanDuplicateProducts;
use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class PendingProducts extends ListRecords
{
    use CanDuplicateProducts;

    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Pending Products';

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->where('status', 'pending');
    }
}
