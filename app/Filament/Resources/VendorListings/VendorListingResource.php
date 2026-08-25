<?php

namespace App\Filament\Resources\VendorListings;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\VendorListings\Pages\CreateVendorListing;
use App\Filament\Resources\VendorListings\Pages\EditVendorListing;
use App\Filament\Resources\VendorListings\Pages\ListVendorListings;
use App\Filament\Resources\VendorListings\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\VendorListings\Schemas\VendorListingForm;
use App\Filament\Resources\VendorListings\Tables\VendorListingsTable;
use App\Models\VendorListing;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class VendorListingResource extends Resource
{
    protected static ?string $model = VendorListing::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup =
        AdminNavigationGroup::ProductsInfo;

    protected static ?string $navigationLabel = 'Vendor Listings';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?int $navigationSort = 9;

    public static function form(Schema $schema): Schema
    {
        return VendorListingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorListingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorListings::route('/'),
            'create' => CreateVendorListing::route('/create'),
            'edit' => EditVendorListing::route('/{record}/edit'),
        ];
    }
}
