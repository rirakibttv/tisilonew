<?php

namespace App\Filament\Resources\InventoryStocks;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\InventoryStocks\Pages\CreateInventoryStock;
use App\Filament\Resources\InventoryStocks\Pages\EditInventoryStock;
use App\Filament\Resources\InventoryStocks\Pages\ListInventoryStocks;
use App\Filament\Resources\InventoryStocks\RelationManagers\MovementsRelationManager;
use App\Filament\Resources\InventoryStocks\Schemas\InventoryStockForm;
use App\Filament\Resources\InventoryStocks\Tables\InventoryStocksTable;
use App\Models\InventoryStock;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class InventoryStockResource extends Resource
{
    protected static ?string $model = InventoryStock::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup =
        AdminNavigationGroup::ProductsInfo;

    protected static ?string $navigationLabel = 'Inventory';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return InventoryStockForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryStocksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [MovementsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryStocks::route('/'),
            'create' => CreateInventoryStock::route('/create'),
            'edit' => EditInventoryStock::route('/{record}/edit'),
        ];
    }
}
