<?php

namespace App\Filament\Resources\ProductTags\Pages;

use App\Filament\Resources\ProductTags\ProductTagResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductTag extends CreateRecord
{
    protected static string $resource = ProductTagResource::class;
}
