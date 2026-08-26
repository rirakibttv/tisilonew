<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Order Items';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product_name')->label('Product')->searchable(),
                TextColumn::make('sku')->label('SKU')->searchable(),
                TextColumn::make('vendor.name')->label('Vendor')->placeholder('Direct'),
                TextColumn::make('quantity')->sortable(),
                TextColumn::make('unit_price')->money('BDT'),
                TextColumn::make('total_amount')->money('BDT'),
            ])
            ->defaultSort('id');
    }
}
