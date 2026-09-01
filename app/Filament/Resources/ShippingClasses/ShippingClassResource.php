<?php

namespace App\Filament\Resources\ShippingClasses;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\ShippingClasses\Pages\CreateShippingClass;
use App\Filament\Resources\ShippingClasses\Pages\EditShippingClass;
use App\Filament\Resources\ShippingClasses\Pages\ListShippingClasses;
use App\Models\ShippingClass;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class ShippingClassResource extends Resource
{
    protected static ?string $model = ShippingClass::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;
    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Shipping;
    protected static ?string $navigationLabel = 'Shipping Class';
    protected static ?string $modelLabel = 'Shipping Class';
    protected static ?string $pluralModelLabel = 'Shipping Classes';
    protected static ?int $navigationSort = 0;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Shipping Class')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120)->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set) => $set('code', Str::slug((string) $state))),
                TextInput::make('code')->required()->maxLength(80)->unique(ignoreRecord: true),
                Textarea::make('description')->rows(3)->columnSpanFull(),
                TextInput::make('sort_order')->numeric()->minValue(0)->default(0),
                Toggle::make('is_active')->label('Active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('code')->badge()->searchable(),
            TextColumn::make('products_count')->label('Products')->counts('products')->sortable(),
            TextColumn::make('rates_count')->label('Region Rates')->counts('rates')->sortable(),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingClasses::route('/'),
            'create' => CreateShippingClass::route('/create'),
            'edit' => EditShippingClass::route('/{record}/edit'),
        ];
    }
}
