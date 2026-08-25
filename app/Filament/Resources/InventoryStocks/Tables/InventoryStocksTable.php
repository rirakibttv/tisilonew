<?php

namespace App\Filament\Resources\InventoryStocks\Tables;

use App\Enums\InventoryMovementType;
use App\Models\InventoryStock;
use App\Services\Inventory\InventoryService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InventoryStocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.listing.product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item.vendor.name')
                    ->label('Vendor')
                    ->searchable(),
                TextColumn::make('item.seller_sku')
                    ->label('Seller SKU')
                    ->searchable(),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('On Hand')
                    ->sortable(),
                TextColumn::make('reserved_quantity')
                    ->label('Reserved')
                    ->sortable(),
                TextColumn::make('available_quantity')
                    ->label('Available'),
                TextColumn::make('reorder_point')
                    ->label('Reorder At')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('vendor_warehouse_id')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('adjust')
                    ->label('Adjust')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->schema([
                        TextInput::make('quantity_delta')
                            ->label('Quantity Change')
                            ->helperText('Use a negative number to reduce stock.')
                            ->numeric()
                            ->rules(['required', 'integer', 'not_in:0'])
                            ->required(),
                        Textarea::make('reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (InventoryStock $record, array $data): void {
                        app(InventoryService::class)->apply(
                            $record,
                            InventoryMovementType::Adjustment,
                            quantityDelta: (int) $data['quantity_delta'],
                            performedBy: auth()->user(),
                            reason: $data['reason'],
                        );

                        Notification::make()
                            ->title('Inventory updated')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
