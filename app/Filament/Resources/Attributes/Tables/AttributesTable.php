<?php

namespace App\Filament\Resources\Attributes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AttributesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Attribute Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'select' => 'Select',
                        'color' => 'Color',
                        'text' => 'Text',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                TextColumn::make('values_count')
                    ->label('Values')
                    ->counts('values')
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('Sort Order')
                    ->sortable(),

                IconColumn::make('status')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Attribute Type')
                    ->options([
                        'select' => 'Select',
                        'color' => 'Color',
                        'text' => 'Text',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->defaultPaginationPageOption(25);
    }
}
