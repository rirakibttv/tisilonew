<?php

namespace App\Filament\Resources\IncompleteOrders\Tables;

use App\Enums\IncompleteOrderStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IncompleteOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->placeholder('Guest customer')
                    ->description(fn ($record): string => $record->customer_phone ?: ($record->customer_email ?: 'No contact supplied'))
                    ->searchable(['customer_name', 'customer_email', 'customer_phone']),
                TextColumn::make('items_count')->label('Items')->state(fn ($record): int => count($record->items ?? [])),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money(fn ($record): string => $record->currency ?: 'BDT')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (IncompleteOrderStatus $state): string => $state->label())
                    ->color(fn (IncompleteOrderStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('convertedOrder.order_number')
                    ->label('Converted Order')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('last_activity_at')->label('Last Activity')->dateTime('d M Y, h:i A')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(IncompleteOrderStatus::options()),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('last_activity_at', 'desc')
            ->defaultPaginationPageOption(25);
    }
}
