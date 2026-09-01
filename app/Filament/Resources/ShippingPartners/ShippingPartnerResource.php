<?php

namespace App\Filament\Resources\ShippingPartners;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\ShippingPartners\Pages\CreateShippingPartner;
use App\Filament\Resources\ShippingPartners\Pages\EditShippingPartner;
use App\Filament\Resources\ShippingPartners\Pages\ListShippingPartners;
use App\Models\ShippingPartner;
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

class ShippingPartnerResource extends Resource
{
    protected static ?string $model = ShippingPartner::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;
    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Shipping;
    protected static ?string $navigationLabel = 'Shipping Partner';
    protected static ?string $modelLabel = 'Shipping Partner';
    protected static ?string $pluralModelLabel = 'Shipping Partners';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Partner Identity')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120)->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set) => $set('code', Str::slug((string) $state))),
                TextInput::make('code')->required()->maxLength(80)->unique(ignoreRecord: true),
                TextInput::make('contact_name')->maxLength(120),
                TextInput::make('phone')->tel()->maxLength(32),
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('api_provider')->label('Courier API Provider')->maxLength(120),
                TextInput::make('tracking_url')->url()->maxLength(500)->columnSpanFull(),
                Textarea::make('notes')->rows(3)->columnSpanFull(),
                Toggle::make('is_active')->label('Active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('code')->badge()->searchable(),
            TextColumn::make('phone')->searchable(),
            TextColumn::make('api_provider')->label('API Provider'),
            TextColumn::make('rates_count')->label('Assigned Rates')->counts('rates')->sortable(),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingPartners::route('/'),
            'create' => CreateShippingPartner::route('/create'),
            'edit' => EditShippingPartner::route('/{record}/edit'),
        ];
    }
}
