<?php

namespace App\Filament\Seller\Resources\Inventory;

use App\Enums\InventoryMovementType;
use App\Filament\Resources\InventoryStocks\RelationManagers\MovementsRelationManager;
use App\Filament\Seller\Resources\Inventory\Pages\CreateInventory;
use App\Filament\Seller\Resources\Inventory\Pages\EditInventory;
use App\Filament\Seller\Resources\Inventory\Pages\ListInventory;
use App\Models\InventoryStock;
use App\Models\VendorListingItem;
use App\Models\VendorWarehouse;
use App\Services\Inventory\InventoryService;
use App\Support\SellerAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class InventoryResource extends Resource
{
    protected static ?string $model = InventoryStock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Inventory';

    protected static ?string $modelLabel = 'Stock Record';

    protected static ?string $pluralModelLabel = 'Inventory';

    protected static ?string $slug = 'inventory';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Inventory Location')
                ->columns(2)
                ->schema([
                    Select::make('vendor_listing_item_id')
                        ->label('Your SKU')
                        ->options(fn (): array => VendorListingItem::query()
                            ->where('vendor_id', SellerAccess::currentVendor()?->getKey() ?? 0)
                            ->with('listing.product')
                            ->get()
                            ->mapWithKeys(fn (VendorListingItem $item): array => [
                                $item->id => ($item->listing?->product?->name ?? 'Product').' — '.$item->seller_sku,
                            ])->all())
                        ->searchable()
                        ->preload()
                        ->disabled(fn (string $operation): bool => $operation === 'edit')
                        ->dehydrated()
                        ->required(),
                    Select::make('vendor_warehouse_id')
                        ->label('Warehouse')
                        ->options(fn (): array => VendorWarehouse::query()
                            ->where('vendor_id', SellerAccess::currentVendor()?->getKey() ?? 0)
                            ->where('status', true)
                            ->orderByDesc('is_default')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->disabled(fn (string $operation): bool => $operation === 'edit')
                        ->dehydrated()
                        ->required(),
                ]),
            Section::make('Stock Control')
                ->description('স্টকের প্রতিটি পরিবর্তন movement history-তে সংরক্ষিত থাকবে।')
                ->columns(3)
                ->schema([
                    TextInput::make('opening_quantity')
                        ->label('Opening Quantity')
                        ->numeric()->minValue(0)->default(0)
                        ->dehydrated(false)
                        ->visible(fn (string $operation): bool => $operation === 'create'),
                    Placeholder::make('current_quantity')->label('On Hand')
                        ->content(fn (?InventoryStock $record): int => $record?->quantity ?? 0)
                        ->visible(fn (string $operation): bool => $operation === 'edit'),
                    Placeholder::make('current_reserved')->label('Reserved')
                        ->content(fn (?InventoryStock $record): int => $record?->reserved_quantity ?? 0)
                        ->visible(fn (string $operation): bool => $operation === 'edit'),
                    Placeholder::make('current_available')->label('Available')
                        ->content(fn (?InventoryStock $record): int => $record?->available_quantity ?? 0)
                        ->visible(fn (string $operation): bool => $operation === 'edit'),
                    TextInput::make('incoming_quantity')->numeric()->minValue(0)->default(0)->required(),
                    TextInput::make('reorder_point')->numeric()->minValue(0)->default(5)->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.listing.product.name')->label('Product')->searchable()->sortable(),
                TextColumn::make('item.seller_sku')->label('Seller SKU')->searchable(),
                TextColumn::make('warehouse.name')->label('Warehouse')->searchable(),
                TextColumn::make('quantity')->label('On Hand')->sortable(),
                TextColumn::make('reserved_quantity')->label('Reserved')->sortable(),
                TextColumn::make('available_quantity')->label('Available'),
                TextColumn::make('reorder_point')->label('Reorder At')->sortable(),
            ])
            ->filters([
                SelectFilter::make('vendor_warehouse_id')
                    ->label('Warehouse')
                    ->options(fn (): array => VendorWarehouse::query()
                        ->where('vendor_id', SellerAccess::currentVendor()?->getKey() ?? 0)
                        ->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                Action::make('adjust')
                    ->label('Adjust')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->visible(fn (): bool => SellerAccess::can(SellerAccess::INVENTORY_MANAGE))
                    ->schema([
                        TextInput::make('quantity_delta')->label('Quantity Change')
                            ->helperText('স্টক কমাতে negative number ব্যবহার করুন।')
                            ->numeric()->rules(['required', 'integer', 'not_in:0'])->required(),
                        Textarea::make('reason')->required()->rows(3),
                    ])
                    ->action(function (InventoryStock $record, array $data): void {
                        app(InventoryService::class)->apply(
                            $record,
                            InventoryMovementType::Adjustment,
                            quantityDelta: (int) $data['quantity_delta'],
                            performedBy: SellerAccess::user(),
                            reason: $data['reason'],
                        );

                        Notification::make()->title('Inventory updated')->success()->send();
                    }),
                EditAction::make()->visible(fn (InventoryStock $record): bool => static::canEdit($record)),
            ])
            ->defaultSort('updated_at', 'desc')
            ->defaultPaginationPageOption(50);
    }

    public static function getRelations(): array
    {
        return [MovementsRelationManager::class];
    }

    public static function getEloquentQuery(): Builder
    {
        $vendorId = SellerAccess::currentVendor()?->getKey() ?? 0;

        return parent::getEloquentQuery()
            ->whereHas('item', fn (Builder $query): Builder => $query->where('vendor_id', $vendorId));
    }

    public static function canViewAny(): bool
    {
        return SellerAccess::can(SellerAccess::INVENTORY_VIEW);
    }

    public static function canCreate(): bool
    {
        return SellerAccess::can(SellerAccess::INVENTORY_MANAGE);
    }

    public static function canEdit(Model $record): bool
    {
        return SellerAccess::can(SellerAccess::INVENTORY_MANAGE)
            && (int) $record->item?->vendor_id === (int) SellerAccess::currentVendor()?->getKey();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventory::route('/'),
            'create' => CreateInventory::route('/create'),
            'edit' => EditInventory::route('/{record}/edit'),
        ];
    }
}
