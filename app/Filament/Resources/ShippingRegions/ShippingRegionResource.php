<?php

namespace App\Filament\Resources\ShippingRegions;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\ShippingRegions\Pages\CreateShippingRegion;
use App\Filament\Resources\ShippingRegions\Pages\EditShippingRegion;
use App\Filament\Resources\ShippingRegions\Pages\ListShippingRegions;
use App\Models\ShippingRegion;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ShippingRegionResource extends Resource
{
    protected static ?string $model = ShippingRegion::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;
    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Shipping;
    protected static ?string $navigationLabel = 'Shipping Region';
    protected static ?string $modelLabel = 'Shipping Region';
    protected static ?string $pluralModelLabel = 'Shipping Regions';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'upazila';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Division, District & Upazila / Police Station')->columns(2)->schema([
                TextInput::make('division')->required()->maxLength(120),
                TextInput::make('district')->required()->maxLength(120),
                TextInput::make('upazila')->label('Upazila / Police Station')->required()->maxLength(120),
                TextInput::make('postal_code')->maxLength(20),
                TextInput::make('sort_order')->numeric()->minValue(0)->default(0),
                Toggle::make('is_active')->label('Active')->default(true),
            ]),
            Section::make('Shipping Class Rates')
                ->description('For each product class, configure the first-item charge and every additional-item charge for this Upazila.')
                ->schema([
                    Repeater::make('rates')->relationship('rates')->defaultItems(0)->reorderable(false)->columns(4)->schema([
                        Select::make('shipping_class_id')->label('Shipping Class')
                            ->relationship('shippingClass', 'name', fn ($query) => $query->where('is_active', true))
                            ->searchable()->preload()->required()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        Select::make('shipping_partner_id')->label('Shipping Partner')
                            ->relationship('partner', 'name', fn ($query) => $query->where('is_active', true))
                            ->searchable()->preload(),
                        TextInput::make('base_charge')->label('First Item Charge')->numeric()->prefix('৳')->minValue(0)->required(),
                        TextInput::make('additional_item_charge')->label('Each Additional Item')->numeric()->prefix('৳')->minValue(0)->default(0)->required(),
                        TextInput::make('estimated_min_days')->label('Minimum Days')->numeric()->minValue(1)->default(1)->required(),
                        TextInput::make('estimated_max_days')->label('Maximum Days')->numeric()->minValue(1)->gte('estimated_min_days')->default(3)->required(),
                        Toggle::make('is_active')->label('Rate Active')->default(true),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('division')->searchable()->sortable(),
            TextColumn::make('district')->searchable()->sortable(),
            TextColumn::make('upazila')->label('Upazila / Police Station')->searchable()->sortable(),
            TextColumn::make('postal_code')->label('Post Code'),
            TextColumn::make('rates_count')->label('Class Rates')->counts('rates')->sortable(),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingRegions::route('/'),
            'create' => CreateShippingRegion::route('/create'),
            'edit' => EditShippingRegion::route('/{record}/edit'),
        ];
    }
}
