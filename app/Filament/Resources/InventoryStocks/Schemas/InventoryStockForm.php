<?php

namespace App\Filament\Resources\InventoryStocks\Schemas;

use App\Models\InventoryStock;
use App\Models\VendorListingItem;
use App\Models\VendorWarehouse;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class InventoryStockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Inventory Location')
                ->columns(2)
                ->schema([
                    Select::make('vendor_listing_item_id')
                        ->label('Vendor SKU')
                        ->options(fn (): array => VendorListingItem::query()
                            ->with(['vendor', 'listing.product'])
                            ->get()
                            ->mapWithKeys(fn (VendorListingItem $item): array => [
                                $item->id => $item->vendor->name.' — '
                                    .$item->listing->product->name.' — '
                                    .$item->seller_sku,
                            ])
                            ->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->disabled(fn (string $operation): bool => $operation === 'edit')
                        ->dehydrated()
                        ->required(),

                    Select::make('vendor_warehouse_id')
                        ->label('Warehouse')
                        ->options(function (Get $get): array {
                            $vendorId = VendorListingItem::query()
                                ->find($get('vendor_listing_item_id'))
                                ?->vendor_id;

                            if (! $vendorId) {
                                return [];
                            }

                            return VendorWarehouse::query()
                                ->where('vendor_id', $vendorId)
                                ->where('status', true)
                                ->orderByDesc('is_default')
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->disabled(fn (string $operation): bool => $operation === 'edit')
                        ->dehydrated()
                        ->required(),
                ]),

            Section::make('Stock Control')
                ->description('All stock changes after creation are recorded in the movement ledger.')
                ->columns(3)
                ->schema([
                    TextInput::make('opening_quantity')
                        ->label('Opening Quantity')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->dehydrated(false)
                        ->visible(fn (string $operation): bool => $operation === 'create'),

                    Placeholder::make('current_quantity')
                        ->label('On Hand')
                        ->content(fn (?InventoryStock $record): int => $record?->quantity ?? 0)
                        ->visible(fn (string $operation): bool => $operation === 'edit'),

                    Placeholder::make('current_reserved_quantity')
                        ->label('Reserved')
                        ->content(fn (?InventoryStock $record): int => $record?->reserved_quantity ?? 0)
                        ->visible(fn (string $operation): bool => $operation === 'edit'),

                    Placeholder::make('current_available_quantity')
                        ->label('Available')
                        ->content(fn (?InventoryStock $record): int => $record?->available_quantity ?? 0)
                        ->visible(fn (string $operation): bool => $operation === 'edit'),

                    TextInput::make('incoming_quantity')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),

                    TextInput::make('reorder_point')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                ]),
        ]);
    }
}
