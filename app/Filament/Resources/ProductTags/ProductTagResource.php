<?php

namespace App\Filament\Resources\ProductTags;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\ProductTags\Pages\CreateProductTag;
use App\Filament\Resources\ProductTags\Pages\EditProductTag;
use App\Filament\Resources\ProductTags\Pages\ListProductTags;
use App\Filament\Resources\ProductTags\Schemas\ProductTagForm;
use App\Filament\Resources\ProductTags\Tables\ProductTagsTable;
use App\Models\ProductTag;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProductTagResource extends Resource
{
    protected static ?string $model = ProductTag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Product Tags';

    protected static ?string $modelLabel = 'Product Tag';

    protected static ?string $pluralModelLabel = 'Product Tags';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ProductsInfo;

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProductTagForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductTagsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductTags::route('/'),
            'create' => CreateProductTag::route('/create'),
            'edit' => EditProductTag::route('/{record}/edit'),
        ];
    }
}
