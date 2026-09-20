<?php

namespace App\Filament\Resources\AttributeValues;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\AttributeValues\Pages\CreateAttributeValue;
use App\Filament\Resources\AttributeValues\Pages\EditAttributeValue;
use App\Filament\Resources\AttributeValues\Pages\ListAttributeValues;
use App\Filament\Resources\AttributeValues\Schemas\AttributeValueForm;
use App\Filament\Resources\AttributeValues\Tables\AttributeValuesTable;
use App\Models\AttributeValue;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AttributeValueResource extends Resource
{
    protected static ?string $model = AttributeValue::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $navigationLabel = 'Attribute Values';

    protected static ?string $modelLabel = 'Attribute Value';

    protected static ?string $pluralModelLabel = 'Attribute Values';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ProductsInfo;

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'value';

    public static function form(Schema $schema): Schema
    {
        return AttributeValueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttributeValuesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributeValues::route('/'),
            'create' => CreateAttributeValue::route('/create'),
            'edit' => EditAttributeValue::route('/{record}/edit'),
        ];
    }
}
