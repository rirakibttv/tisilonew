<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductVariation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class VariationsRelationManager extends RelationManager
{
    protected static string $relationship = 'variations';

    protected static ?string $title = 'Product Variations';

    /*
    |--------------------------------------------------------------------------
    | Visibility
    |--------------------------------------------------------------------------
    */

    public static function canViewForRecord(
        Model $ownerRecord,
        string $pageClass
    ): bool {
        return $ownerRecord->product_type === 'variable';
    }

    /*
    |--------------------------------------------------------------------------
    | Product Attributes
    |--------------------------------------------------------------------------
    */

    protected function getProductAttributes()
    {
        return $this->getOwnerRecord()
            ->attributes()
            ->where('attributes.status', true)
            ->with([
                'values' => fn ($query) => $query
                    ->where('status', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->orderBy('attributes.sort_order')
            ->orderBy('attributes.id')
            ->get();
    }

    protected function getVariationAttributeFields(): array
    {
        return $this->getProductAttributes()
            ->map(function (Attribute $attribute) {
                return Select::make(
                    'variation_attribute_'.$attribute->id
                )
                    ->label($attribute->name)
                    ->options(
                        $attribute->values
                            ->pluck('value', 'id')
                            ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->required();
            })
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Attribute Helpers
    |--------------------------------------------------------------------------
    */

    protected function extractAttributeValueIds(array $data): array
    {
        $ids = [];

        foreach ($this->getProductAttributes() as $attribute) {
            $field = 'variation_attribute_'.$attribute->id;

            if (filled($data[$field] ?? null)) {
                $ids[] = (int) $data[$field];
            }
        }

        sort($ids);

        return array_values(array_unique($ids));
    }

    protected function removeAttributeFields(array $data): array
    {
        foreach ($this->getProductAttributes() as $attribute) {
            unset(
                $data['variation_attribute_'.$attribute->id]
            );
        }

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | Attribute Validation
    |--------------------------------------------------------------------------
    */

    protected function ensureCompleteAttributes(
        array $attributeValueIds
    ): void {
        $expectedCount = $this->getProductAttributes()->count();

        if (count($attributeValueIds) === $expectedCount) {
            return;
        }

        Notification::make()
            ->title('Incomplete Variation')
            ->body(
                'Please select one value from every product attribute.'
            )
            ->danger()
            ->persistent()
            ->send();

        throw new Halt;
    }

    protected function ensureValidAttributeValues(
        array $attributeValueIds
    ): void {
        $attributes = $this->getProductAttributes();

        $validValueIds = $attributes
            ->flatMap(
                fn (Attribute $attribute) => $attribute->values
            )
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($attributeValueIds as $id) {
            if (in_array($id, $validValueIds, true)) {
                continue;
            }

            Notification::make()
                ->title('Invalid Variation')
                ->body(
                    'One or more selected attribute values are invalid.'
                )
                ->danger()
                ->persistent()
                ->send();

            throw new Halt;
        }
    }

    protected function ensureUniqueCombination(
        array $attributeValueIds,
        ?int $ignoreVariationId = null
    ): void {
        sort($attributeValueIds);

        $variations = $this->getOwnerRecord()
            ->variations()
            ->with('attributeValues:id')
            ->when(
                $ignoreVariationId !== null,
                fn ($query) => $query->where(
                    'id',
                    '!=',
                    $ignoreVariationId
                )
            )
            ->get();

        foreach ($variations as $variation) {
            $existingIds = $variation
                ->attributeValues
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            if ($existingIds !== $attributeValueIds) {
                continue;
            }

            Notification::make()
                ->title('Duplicate Variation')
                ->body(
                    'This attribute combination already exists for this product.'
                )
                ->danger()
                ->persistent()
                ->send();

            throw new Halt;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Price Validation
    |--------------------------------------------------------------------------
    */

    protected function validatePrices(array $data): void
    {
        $purchasePrice = filled($data['purchase_price'] ?? null)
            ? (float) $data['purchase_price']
            : null;

        $regularPrice = filled($data['regular_price'] ?? null)
            ? (float) $data['regular_price']
            : null;

        $salePrice = filled($data['sale_price'] ?? null)
            ? (float) $data['sale_price']
            : null;

        if (
            ($purchasePrice !== null && $purchasePrice < 0) ||
            ($regularPrice !== null && $regularPrice < 0) ||
            ($salePrice !== null && $salePrice < 0)
        ) {
            Notification::make()
                ->title('Invalid Price')
                ->body('Product prices cannot be negative.')
                ->danger()
                ->persistent()
                ->send();

            throw new Halt;
        }

        if (
            $salePrice !== null &&
            $regularPrice !== null &&
            $salePrice > $regularPrice
        ) {
            Notification::make()
                ->title('Invalid Sale Price')
                ->body(
                    'Sale Price cannot be greater than Regular Price.'
                )
                ->danger()
                ->persistent()
                ->send();

            throw new Halt;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Inventory Validation / Normalization
    |--------------------------------------------------------------------------
    */

    protected function normalizeInventory(array $data): array
    {
        $stockQuantity = (int) ($data['stock_quantity'] ?? 0);

        $lowStockThreshold = (int) (
            $data['low_stock_threshold'] ?? 0
        );

        if ($stockQuantity < 0) {
            Notification::make()
                ->title('Invalid Stock Quantity')
                ->body('Stock Quantity cannot be negative.')
                ->danger()
                ->persistent()
                ->send();

            throw new Halt;
        }

        if ($lowStockThreshold < 0) {
            Notification::make()
                ->title('Invalid Low Stock Threshold')
                ->body(
                    'Low Stock Threshold cannot be negative.'
                )
                ->danger()
                ->persistent()
                ->send();

            throw new Halt;
        }

        $stockStatus = $data['stock_status'] ?? 'in_stock';

        /*
         * Keep "On Backorder" as a manual status.
         *
         * Otherwise:
         * stock = 0  -> Out of Stock
         * stock > 0  -> In Stock
         */
        if ($stockStatus !== 'on_backorder') {
            $data['stock_status'] = $stockQuantity > 0
                ? 'in_stock'
                : 'out_of_stock';
        }

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | Default Variation Rules
    |--------------------------------------------------------------------------
    */

    protected function ensureDefaultVariationIsActive(
        array $data
    ): void {
        $isDefault = (bool) ($data['is_default'] ?? false);
        $isActive = (bool) ($data['status'] ?? false);

        if (! $isDefault || $isActive) {
            return;
        }

        Notification::make()
            ->title('Invalid Default Variation')
            ->body(
                'A default variation must be active.'
            )
            ->danger()
            ->persistent()
            ->send();

        throw new Halt;
    }

    protected function ensureSingleDefault(
        ProductVariation $variation
    ): void {
        if (! $variation->is_default) {
            return;
        }

        $this->getOwnerRecord()
            ->variations()
            ->where(
                'id',
                '!=',
                $variation->getKey()
            )
            ->update([
                'is_default' => false,
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Form
    |--------------------------------------------------------------------------
    */

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Variation Attributes')
                    ->description(
                        'Select one value from each attribute used by this product.'
                    )
                    ->columns(2)
                    ->schema(
                        $this->getVariationAttributeFields()
                    ),

                Section::make('Variation Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('sku')
                            ->label('SKU')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('barcode')
                            ->label('Barcode')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        FileUpload::make('image')
                            ->label('Variation Image')
                            ->image()
                            ->disk('public')
                            ->directory('products/variations')
                            ->visibility('public')
                            ->columnSpanFull(),
                    ]),

                Section::make('Pricing')
                    ->columns(3)
                    ->schema([
                        TextInput::make('purchase_price')
                            ->label('Purchase Price')
                            ->numeric()
                            ->prefix('৳')
                            ->minValue(0),

                        TextInput::make('regular_price')
                            ->label('Regular Price')
                            ->numeric()
                            ->prefix('৳')
                            ->minValue(0)
                            ->required(),

                        TextInput::make('sale_price')
                            ->label('Sale Price')
                            ->numeric()
                            ->prefix('৳')
                            ->minValue(0)
                            ->lte('regular_price')
                            ->validationMessages([
                                'lte' => 'Sale Price cannot be greater than Regular Price.',
                            ]),
                    ]),

                Section::make('Inventory')
                    ->columns(3)
                    ->schema([
                        TextInput::make('stock_quantity')
                            ->label('Stock Quantity')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),

                        TextInput::make('low_stock_threshold')
                            ->label('Low Stock Threshold')
                            ->numeric()
                            ->default(5)
                            ->minValue(0)
                            ->required(),

                        Select::make('stock_status')
                            ->label('Stock Status')
                            ->options([
                                'in_stock' => 'In Stock',
                                'out_of_stock' => 'Out of Stock',
                                'on_backorder' => 'On Backorder',
                            ])
                            ->default('in_stock')
                            ->required()
                            ->helperText(
                                'Stock status will be normalized automatically unless On Backorder is selected.'
                            ),
                    ]),

                Section::make('Additional Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('weight')
                            ->label('Weight')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('kg'),

                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),

                        Toggle::make('is_default')
                            ->label('Default Variation')
                            ->default(false)
                            ->helperText(
                                'Only one variation can be the default.'
                            ),

                        Toggle::make('status')
                            ->label('Active')
                            ->default(true)
                            ->helperText(
                                'A default variation must remain active.'
                            ),
                    ]),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')

            ->columns([
                ImageColumn::make('image')
                    ->label('Image')
                    ->disk('public')
                    ->square(),

                TextColumn::make('id')
                    ->label('Attributes')
                    ->formatStateUsing(
                        function (
                            ProductVariation $record
                        ): string {
                            return $record
                                ->attributeValues
                                ->unique('id')
                                ->sortBy(
                                    function (
                                        AttributeValue $value
                                    ) {
                                        return sprintf(
                                            '%010d-%010d-%010d',
                                            $value
                                                ->attribute
                                                ?->sort_order ?? 0,
                                            $value->attribute_id ?? 0,
                                            $value->sort_order ?? 0
                                        );
                                    }
                                )
                                ->map(
                                    fn (
                                        AttributeValue $value
                                    ): string => (
                                        $value
                                            ->attribute
                                            ?->name
                                        ?? 'Attribute'
                                    )
                                        .': '
                                        .$value->value
                                )
                                ->implode(', ');
                        }
                    )
                    ->wrap(),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('regular_price')
                    ->label('Regular Price')
                    ->money('BDT')
                    ->sortable(),

                TextColumn::make('sale_price')
                    ->label('Sale Price')
                    ->money('BDT')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('stock_status')
                    ->label('Stock Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'in_stock' => 'In Stock',

                            'out_of_stock' => 'Out of Stock',

                            'on_backorder' => 'On Backorder',

                            default => ucfirst(
                                str_replace(
                                    '_',
                                    ' ',
                                    $state ?? ''
                                )
                            ),
                        }
                    ),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                IconColumn::make('status')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->sortable(),
            ])

            ->filters([
                SelectFilter::make('stock_status')
                    ->label('Stock Status')
                    ->options([
                        'in_stock' => 'In Stock',
                        'out_of_stock' => 'Out of Stock',
                        'on_backorder' => 'On Backorder',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])

            /*
            |--------------------------------------------------------------------------
            | Create
            |--------------------------------------------------------------------------
            */

            ->headerActions([
                CreateAction::make()
                    ->label('Add Variation')
                    ->using(
                        function (
                            array $data
                        ): ProductVariation {
                            $attributeValueIds =
                                $this->extractAttributeValueIds(
                                    $data
                                );

                            $this->ensureCompleteAttributes(
                                $attributeValueIds
                            );

                            $this->ensureValidAttributeValues(
                                $attributeValueIds
                            );

                            $this->ensureUniqueCombination(
                                $attributeValueIds
                            );

                            $this->validatePrices($data);

                            $data = $this->normalizeInventory(
                                $data
                            );

                            $this->ensureDefaultVariationIsActive(
                                $data
                            );

                            $variationData =
                                $this->removeAttributeFields(
                                    $data
                                );

                            /** @var ProductVariation $variation */
                            $variation = $this
                                ->getOwnerRecord()
                                ->variations()
                                ->create(
                                    $variationData
                                );

                            $variation
                                ->attributeValues()
                                ->sync(
                                    $attributeValueIds
                                );

                            $this->ensureSingleDefault(
                                $variation
                            );

                            return $variation;
                        }
                    ),
            ])

            /*
            |--------------------------------------------------------------------------
            | Edit / Delete
            |--------------------------------------------------------------------------
            */

            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(
                        function (
                            array $data,
                            ProductVariation $record
                        ): array {
                            $record->load(
                                'attributeValues'
                            );

                            foreach (
                                $record
                                    ->attributeValues
                                    ->unique('id') as $attributeValue
                            ) {
                                $data[
                                    'variation_attribute_'
                                    .$attributeValue
                                        ->attribute_id
                                ] = $attributeValue->id;
                            }

                            return $data;
                        }
                    )

                    ->using(
                        function (
                            ProductVariation $record,
                            array $data
                        ): ProductVariation {
                            $attributeValueIds =
                                $this->extractAttributeValueIds(
                                    $data
                                );

                            $this->ensureCompleteAttributes(
                                $attributeValueIds
                            );

                            $this->ensureValidAttributeValues(
                                $attributeValueIds
                            );

                            $this->ensureUniqueCombination(
                                $attributeValueIds,
                                (int) $record->getKey()
                            );

                            $this->validatePrices($data);

                            $data = $this->normalizeInventory(
                                $data
                            );

                            $this->ensureDefaultVariationIsActive(
                                $data
                            );

                            $variationData =
                                $this->removeAttributeFields(
                                    $data
                                );

                            $record->update(
                                $variationData
                            );

                            $record
                                ->attributeValues()
                                ->sync(
                                    $attributeValueIds
                                );

                            $record->refresh();

                            $this->ensureSingleDefault(
                                $record
                            );

                            return $record;
                        }
                    ),

                DeleteAction::make(),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])

            ->defaultSort('sort_order');
    }
}
