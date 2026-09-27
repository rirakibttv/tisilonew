<?php

namespace App\Filament\Seller\Resources\Warehouses;

use App\Filament\Seller\Resources\Warehouses\Pages\CreateWarehouse;
use App\Filament\Seller\Resources\Warehouses\Pages\EditWarehouse;
use App\Filament\Seller\Resources\Warehouses\Pages\ListWarehouses;
use App\Models\VendorWarehouse;
use App\Support\SellerAccess;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class WarehouseResource extends Resource
{
    protected static ?string $model = VendorWarehouse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Store Management';

    protected static ?string $navigationLabel = 'Warehouses';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Warehouse')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('code')->required()->maxLength(60),
                    TextInput::make('contact_name')->maxLength(255),
                    TextInput::make('phone')->tel()->maxLength(32),
                    Toggle::make('is_default')->label('Default Warehouse'),
                    Toggle::make('status')->label('Active')->default(true),
                ]),
            Section::make('Address')
                ->columns(2)
                ->schema([
                    TextInput::make('address_line_1')->label('Address')->required()->maxLength(255)->columnSpanFull(),
                    TextInput::make('address_line_2')->label('Address Details')->maxLength(255)->columnSpanFull(),
                    TextInput::make('district')->required()->maxLength(100),
                    TextInput::make('upazila')->label('Thana / Upazila')->maxLength(100),
                    TextInput::make('postal_code')->maxLength(20),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('code')->searchable(),
                TextColumn::make('district')->searchable(),
                TextColumn::make('upazila')->label('Thana / Upazila')->placeholder('—'),
                IconColumn::make('is_default')->label('Default')->boolean(),
                IconColumn::make('status')->label('Active')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (VendorWarehouse $record): bool => static::canEdit($record)),
                DeleteAction::make()->visible(fn (VendorWarehouse $record): bool => static::canDelete($record)),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('vendor_id', SellerAccess::currentVendor()?->getKey() ?? 0);
    }

    public static function canViewAny(): bool
    {
        return SellerAccess::can(SellerAccess::WAREHOUSES_VIEW);
    }

    public static function canCreate(): bool
    {
        return SellerAccess::can(SellerAccess::WAREHOUSES_MANAGE);
    }

    public static function canEdit(Model $record): bool
    {
        return SellerAccess::can(SellerAccess::WAREHOUSES_MANAGE)
            && (int) $record->getAttribute('vendor_id') === (int) SellerAccess::currentVendor()?->getKey();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record) && ! $record->getAttribute('is_default');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouses::route('/'),
            'create' => CreateWarehouse::route('/create'),
            'edit' => EditWarehouse::route('/{record}/edit'),
        ];
    }
}
