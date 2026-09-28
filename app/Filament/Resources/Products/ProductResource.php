<?php

namespace App\Filament\Resources\Products;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\PendingProducts;
use App\Filament\Resources\Products\RelationManagers\VariationsRelationManager;
use App\Filament\Resources\Products\Schemas\ProfessionalProductForm;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'All Products';

    protected static string|UnitEnum|null $navigationGroup =
        AdminNavigationGroup::ProductsInfo;

    protected static ?int $navigationSort = 3;

    /*
    |--------------------------------------------------------------------------
    | Labels
    |--------------------------------------------------------------------------
    */

    protected static ?string $modelLabel = 'Product';

    protected static ?string $pluralModelLabel = 'Products';

    protected static ?string $recordTitleAttribute = 'name';

    /*
    |--------------------------------------------------------------------------
    | Form
    |--------------------------------------------------------------------------
    */

    public static function form(Schema $schema): Schema
    {
        return ProfessionalProductForm::configure($schema);
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public static function getRelations(): array
    {
        return [
            VariationsRelationManager::class,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),

            'create' => CreateProduct::route('/create'),

            'pending' => PendingProducts::route('/pending'),

            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
