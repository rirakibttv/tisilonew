<?php

namespace App\Filament\Seller\Resources\Products;

use App\Enums\FulfillmentType;
use App\Enums\ProductCondition;
use App\Enums\VendorListingStatus;
use App\Filament\Resources\VendorListings\RelationManagers\ItemsRelationManager;
use App\Filament\Seller\Resources\Products\Pages\CreateProduct;
use App\Filament\Seller\Resources\Products\Pages\EditProduct;
use App\Filament\Seller\Resources\Products\Pages\ListProducts;
use App\Models\VendorListing;
use App\Support\SellerAccess;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = VendorListing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'My Products';

    protected static ?string $modelLabel = 'Product Listing';

    protected static ?string $pluralModelLabel = 'My Products';

    protected static ?string $slug = 'products';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Product Listing')
                ->description('ক্যাটালগ পণ্য নির্বাচন করে আপনার মূল্য, SKU ও fulfillment তথ্য দিন।')
                ->columns(2)
                ->schema([
                    Select::make('product_id')
                        ->label('Catalog Product')
                        ->relationship('product', 'name', fn ($query) => $query->where('status', 'published'))
                        ->searchable()
                        ->preload()
                        ->disabled(fn (string $operation): bool => $operation === 'edit')
                        ->dehydrated()
                        ->required(),
                    Select::make('shipping_class_id')
                        ->label('Shipping Class')
                        ->relationship('shippingClass', 'name', fn ($query) => $query->where('is_active', true))
                        ->searchable()
                        ->preload(),
                    Select::make('status')
                        ->options([
                            VendorListingStatus::Draft->value => 'Save as Draft',
                            VendorListingStatus::Pending->value => 'Submit for Review',
                        ])
                        ->default(VendorListingStatus::Pending->value)
                        ->visible(fn (string $operation): bool => $operation === 'create')
                        ->required(),
                    Placeholder::make('approval_status')
                        ->label('Approval Status')
                        ->content(fn (?VendorListing $record): string => $record?->status?->label() ?? 'Pending Review')
                        ->visible(fn (string $operation): bool => $operation === 'edit'),
                    Select::make('condition')
                        ->options(ProductCondition::options())
                        ->default(ProductCondition::New->value)
                        ->required(),
                    Select::make('fulfillment_type')
                        ->label('Fulfillment')
                        ->options(FulfillmentType::options())
                        ->default(FulfillmentType::Vendor->value)
                        ->required(),
                    TextInput::make('warranty')->maxLength(255),
                    TextInput::make('handling_time_days')->numeric()->minValue(0)->default(1)->suffix('days')->required(),
                    TextInput::make('min_order_quantity')->numeric()->minValue(1)->default(1)->required(),
                    TextInput::make('max_order_quantity')->numeric()->minValue(1)->gte('min_order_quantity'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')->label('Product')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (VendorListingStatus $state): string => $state->label())
                    ->color(fn (VendorListingStatus $state): string => match ($state) {
                        VendorListingStatus::Approved => 'success',
                        VendorListingStatus::Pending => 'warning',
                        VendorListingStatus::Rejected, VendorListingStatus::Suspended => 'danger',
                        VendorListingStatus::Draft => 'gray',
                    }),
                TextColumn::make('items_count')->label('SKUs')->counts('items'),
                TextColumn::make('shippingClass.name')->label('Shipping Class')->placeholder('Product default'),
                IconColumn::make('is_featured')->label('Featured')->boolean(),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(VendorListingStatus::options()),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (VendorListing $record): bool => static::canEdit($record)),
                DeleteAction::make()->visible(fn (VendorListing $record): bool => static::canDelete($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ])->visible(fn (): bool => SellerAccess::can(SellerAccess::PRODUCTS_MANAGE)),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(50);
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getEloquentQuery(): Builder
    {
        $vendorId = SellerAccess::currentVendor()?->getKey();

        return parent::getEloquentQuery()->where('vendor_id', $vendorId ?? 0);
    }

    public static function canViewAny(): bool
    {
        return SellerAccess::can(SellerAccess::PRODUCTS_VIEW);
    }

    public static function canCreate(): bool
    {
        return SellerAccess::can(SellerAccess::PRODUCTS_MANAGE);
    }

    public static function canEdit(Model $record): bool
    {
        return SellerAccess::can(SellerAccess::PRODUCTS_MANAGE)
            && (int) $record->getAttribute('vendor_id') === (int) SellerAccess::currentVendor()?->getKey();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
