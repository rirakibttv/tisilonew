<?php

namespace App\Filament\Resources\VendorListings\RelationManagers;

use App\Enums\VendorListingItemStatus;
use App\Models\ProductVariation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Vendor SKUs & Prices';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('SKU Identity')
                ->columns(2)
                ->schema([
                    Select::make('product_variation_id')
                        ->label('Catalog Variation')
                        ->helperText('Leave empty for a simple product.')
                        ->options(fn (): array => ProductVariation::query()
                            ->where('product_id', $this->getOwnerRecord()->product_id)
                            ->with(['attributeValues.attribute'])
                            ->get()
                            ->mapWithKeys(function (ProductVariation $variation): array {
                                $options = $variation->attributeValues
                                    ->map(fn ($value): string => ($value->attribute?->name ?? 'Option').': '.$value->value)
                                    ->implode(', ');

                                $label = $options ?: ($variation->sku ?: 'Variation #'.$variation->id);

                                return [$variation->id => $label];
                            })
                            ->all())
                        ->searchable()
                        ->preload(),

                    TextInput::make('seller_sku')
                        ->label('Seller SKU')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('barcode')
                        ->maxLength(255),

                    Select::make('status')
                        ->options(VendorListingItemStatus::options())
                        ->default(VendorListingItemStatus::Active->value)
                        ->required(),
                ]),

            Section::make('Pricing & Availability')
                ->columns(3)
                ->schema([
                    TextInput::make('purchase_price')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('৳'),

                    TextInput::make('regular_price')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('৳')
                        ->required(),

                    TextInput::make('sale_price')
                        ->numeric()
                        ->minValue(0)
                        ->lte('regular_price')
                        ->prefix('৳'),

                    TextInput::make('low_stock_threshold')
                        ->numeric()
                        ->minValue(0)
                        ->default(5)
                        ->required(),

                    Toggle::make('backorders_allowed')
                        ->label('Allow Backorders')
                        ->default(false),

                    Toggle::make('is_default')
                        ->label('Default SKU')
                        ->default(false),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('stocks'))
            ->columns([
                TextColumn::make('seller_sku')
                    ->label('Seller SKU')
                    ->searchable(),

                TextColumn::make('productVariation.sku')
                    ->label('Catalog SKU')
                    ->placeholder('Base product'),

                TextColumn::make('regular_price')
                    ->money('BDT'),

                TextColumn::make('sale_price')
                    ->money('BDT')
                    ->placeholder('—'),

                TextColumn::make('available_quantity')
                    ->label('Available Stock'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn (VendorListingItemStatus $state): string => $state->label(),
                    ),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
