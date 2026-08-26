<?php

namespace App\Filament\Resources\IncompleteOrders;

use App\Filament\Resources\IncompleteOrders\Pages\EditIncompleteOrder;
use App\Filament\Resources\IncompleteOrders\Pages\ListIncompleteOrders;
use App\Filament\Resources\IncompleteOrders\Schemas\IncompleteOrderForm;
use App\Filament\Resources\IncompleteOrders\Tables\IncompleteOrdersTable;
use App\Models\IncompleteOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class IncompleteOrderResource extends Resource
{
    protected static ?string $model = IncompleteOrder::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $modelLabel = 'Incomplete Order';

    protected static ?string $pluralModelLabel = 'Incomplete Orders';

    public static function form(Schema $schema): Schema
    {
        return IncompleteOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IncompleteOrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIncompleteOrders::route('/'),
            'edit' => EditIncompleteOrder::route('/{record}/edit'),
        ];
    }
}
