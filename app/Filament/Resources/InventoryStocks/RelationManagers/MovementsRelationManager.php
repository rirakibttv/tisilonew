<?php

namespace App\Filament\Resources\InventoryStocks\RelationManagers;

use App\Enums\InventoryMovementType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Inventory Movement Ledger';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(
                        fn (InventoryMovementType $state): string => str($state->value)->headline()->toString(),
                    ),
                TextColumn::make('quantity_delta')
                    ->label('On-hand Change')
                    ->numeric(),
                TextColumn::make('quantity_before')
                    ->label('Before'),
                TextColumn::make('quantity_after')
                    ->label('After'),
                TextColumn::make('reserved_delta')
                    ->label('Reserved Change')
                    ->numeric(),
                TextColumn::make('performer.name')
                    ->label('Performed By')
                    ->placeholder('System'),
                TextColumn::make('reason')
                    ->limit(50)
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
