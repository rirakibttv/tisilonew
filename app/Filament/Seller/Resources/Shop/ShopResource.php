<?php

namespace App\Filament\Seller\Resources\Shop;

use App\Filament\Seller\Resources\Shop\Pages\EditShop;
use App\Filament\Seller\Resources\Shop\Pages\ListShop;
use App\Models\Vendor;
use App\Support\SellerAccess;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ShopResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Store Management';

    protected static ?string $navigationLabel = 'Shop Profile';

    protected static ?string $modelLabel = 'Shop Profile';

    protected static ?string $pluralModelLabel = 'Shop Profile';

    protected static ?string $slug = 'shop-profile';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Store Identity')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Store Name')->required()->maxLength(255),
                    TextInput::make('legal_name')->label('Legal Business Name')->maxLength(255),
                    TextInput::make('email')->email()->maxLength(255),
                    TextInput::make('phone')->tel()->maxLength(32),
                    TextInput::make('website')->url()->maxLength(255)->columnSpanFull(),
                ]),
            Section::make('Storefront')
                ->columns(2)
                ->schema([
                    FileUpload::make('logo')
                        ->image()->imageEditor()->disk('public')->directory('vendors/logos')->visibility('public'),
                    FileUpload::make('banner')
                        ->image()->imageEditor()->disk('public')->directory('vendors/banners')->visibility('public'),
                    RichEditor::make('description')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')->disk('public')->circular(),
                TextColumn::make('name')->label('Store')->searchable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state): string => $state->label()),
                TextColumn::make('commission_rate')->label('Commission')->suffix('%'),
                TextColumn::make('email')->placeholder('—'),
                TextColumn::make('phone')->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (Vendor $record): bool => static::canEdit($record)),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereKey(SellerAccess::currentVendor()?->getKey() ?? 0);
    }

    public static function canViewAny(): bool
    {
        return SellerAccess::can(SellerAccess::SHOP_VIEW);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return SellerAccess::can(SellerAccess::SHOP_MANAGE)
            && (int) $record->getKey() === (int) SellerAccess::currentVendor()?->getKey();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShop::route('/'),
            'edit' => EditShop::route('/{record}/edit'),
        ];
    }
}
