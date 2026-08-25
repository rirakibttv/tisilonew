<?php

namespace App\Filament\Resources\VendorWarehouses;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\VendorWarehouses\Pages\CreateVendorWarehouse;
use App\Filament\Resources\VendorWarehouses\Pages\EditVendorWarehouse;
use App\Filament\Resources\VendorWarehouses\Pages\ListVendorWarehouses;
use App\Filament\Resources\VendorWarehouses\Schemas\VendorWarehouseForm;
use App\Filament\Resources\VendorWarehouses\Tables\VendorWarehousesTable;
use App\Models\VendorWarehouse;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class VendorWarehouseResource extends Resource
{
    protected static ?string $model = VendorWarehouse::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup =
        AdminNavigationGroup::Vendors;

    protected static ?string $navigationLabel = 'Warehouses';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return VendorWarehouseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorWarehousesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorWarehouses::route('/'),
            'create' => CreateVendorWarehouse::route('/create'),
            'edit' => EditVendorWarehouse::route('/{record}/edit'),
        ];
    }
}
