<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Attribute;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProfessionalProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'xl' => 4])
                ->schema([
                    Grid::make(1)
                        ->schema([
                            static::basicInformationSection(),
                            static::variationAttributesSection(),
                            static::productVariantsSection(),
                            static::pricingSection(),
                            static::descriptionSection(),
                            static::shippingSection(),
                        ])
                        ->columnSpan(['default' => 1, 'xl' => 3]),

                    Grid::make(1)
                        ->schema([
                            static::publishingSection(),
                            static::inventorySection(),
                            static::imagesSection(),
                            static::codesSection(),
                        ])
                        ->columnSpan(['default' => 1, 'xl' => 1]),
                ])
                ->columnSpanFull(),
        ]);
    }

    protected static function basicInformationSection(): Section
    {
        return Section::make('Basic Information')
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->label('Product Name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state)))
                    ->columnSpanFull(),

                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),

                Select::make('product_type')
                    ->label('Product Type')
                    ->options([
                        'simple' => 'Simple Product',
                        'variable' => 'Variable Product',
                    ])
                    ->default('simple')
                    ->live()
                    ->required(),

                Select::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),

                Select::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                Select::make('tags')
                    ->label('Product Tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ]);
    }

    protected static function variationAttributesSection(): Section
    {
        return Section::make('Variation Attributes')
            ->description('Select the attributes that will be used for this variable product.')
            ->schema([
                Select::make('attributes')
                    ->label('Product Attributes')
                    ->relationship('attributes', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->helperText('Example: Color, Size, Storage, RAM')
                    ->columnSpanFull(),
            ])
            ->visible(fn (Get $get): bool => $get('product_type') === 'variable');
    }

    protected static function productVariantsSection(): Section
    {
        return Section::make('Product Variants')
            ->description('Add every purchasable variation before creating the product.')
            ->schema([
                Repeater::make('variants')
                    ->label('Variants')
                    ->addActionLabel('Add Variant')
                    ->defaultItems(0)
                    ->minItems(1)
                    ->reorderable()
                    ->collapsible()
                    ->itemNumbers()
                    ->itemLabel(fn (array $state): ?string => filled($state['sku'] ?? null)
                        ? $state['sku']
                        : 'New Variant')
                    ->schema([
                        Section::make('Variant Attributes')
                            ->description('Select exactly one value from each product attribute.')
                            ->columns(2)
                            ->schema(function (Get $get): array {
                                $attributeIds = collect($get('../../attributes') ?? [])
                                    ->filter()
                                    ->map(fn ($id) => (int) $id)
                                    ->unique()
                                    ->values()
                                    ->all();

                                if (empty($attributeIds)) {
                                    return [];
                                }

                                return Attribute::query()
                                    ->whereIn('id', $attributeIds)
                                    ->where('status', true)
                                    ->with([
                                        'values' => fn ($query) => $query
                                            ->where('status', true)
                                            ->orderBy('sort_order')
                                            ->orderBy('id'),
                                    ])
                                    ->orderBy('sort_order')
                                    ->orderBy('id')
                                    ->get()
                                    ->map(fn (Attribute $attribute) => Select::make('attribute_'.$attribute->id)
                                        ->label($attribute->name)
                                        ->options($attribute->values->pluck('value', 'id')->all())
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->native(false))
                                    ->all();
                            })
                            ->columnSpanFull(),

                        TextInput::make('sku')
                            ->label('SKU')
                            ->maxLength(255)
                            ->live(onBlur: true),

                        TextInput::make('barcode')
                            ->label('Barcode')
                            ->maxLength(255),

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
                            ->options(static::stockStatusOptions())
                            ->default('in_stock')
                            ->required()
                            ->native(false),

                        FileUpload::make('image')
                            ->label('Variation Image')
                            ->image()
                            ->disk('public')
                            ->directory('products/variations')
                            ->visibility('public')
                            ->imagePreviewHeight('180')
                            ->panelLayout('integrated')
                            ->columnSpanFull(),

                        TextInput::make('weight')
                            ->label('Weight')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('kg'),

                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Toggle::make('is_default')
                            ->label('Default Variation')
                            ->default(false)
                            ->helperText('Only one variation will remain default.'),

                        Toggle::make('status')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ])
            ->visible(fn (Get $get, string $operation): bool => $operation === 'create'
                && $get('product_type') === 'variable');
    }

    protected static function pricingSection(): Section
    {
        return Section::make('Pricing')
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
            ])
            ->visible(fn (Get $get): bool => $get('product_type') === 'simple');
    }

    protected static function descriptionSection(): Section
    {
        return Section::make('Product Description')
            ->schema([
                Textarea::make('short_description')
                    ->label('Short Description')
                    ->rows(4)
                    ->columnSpanFull(),

                RichEditor::make('description')
                    ->label('Full Description')
                    ->columnSpanFull(),
            ]);
    }

    protected static function shippingSection(): Section
    {
        return Section::make('Shipping')
            ->columns(4)
            ->schema([
                Select::make('shipping_class_id')
                    ->label('Shipping Class')
                    ->relationship('shippingClass', 'name', fn ($query) => $query->where('is_active', true))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Checkout delivery charge is calculated from this class and the customer’s selected region.')
                    ->columnSpanFull(),

                TextInput::make('weight')
                    ->label('Weight')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('kg'),

                TextInput::make('length')
                    ->label('Length')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('cm'),

                TextInput::make('width')
                    ->label('Width')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('cm'),

                TextInput::make('height')
                    ->label('Height')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('cm'),
            ]);
    }

    protected static function publishingSection(): Section
    {
        return Section::make('Publishing')
            ->columns(1)
            ->schema([
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'pending' => 'Pending',
                        'published' => 'Published',
                    ])
                    ->default('draft')
                    ->required()
                    ->native(false),

                Toggle::make('featured')
                    ->label('Featured Product')
                    ->default(false),

                TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ]);
    }

    protected static function inventorySection(): Section
    {
        return Section::make('Inventory')
            ->columns(1)
            ->schema([
                Toggle::make('manage_stock')
                    ->label('Manage Stock')
                    ->default(true)
                    ->live(),

                TextInput::make('stock_quantity')
                    ->label('Stock Quantity')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->visible(fn (Get $get): bool => (bool) $get('manage_stock')),

                Select::make('stock_status')
                    ->label('Stock Status')
                    ->options(static::stockStatusOptions())
                    ->default('in_stock')
                    ->required()
                    ->native(false),

                TextInput::make('low_stock_threshold')
                    ->label('Low Stock Threshold')
                    ->numeric()
                    ->default(5)
                    ->minValue(0)
                    ->visible(fn (Get $get): bool => (bool) $get('manage_stock')),
            ])
            ->visible(fn (Get $get): bool => $get('product_type') === 'simple');
    }

    protected static function imagesSection(): Section
    {
        return Section::make('Product Images')
            ->columns(1)
            ->schema([
                FileUpload::make('featured_image')
                    ->label('Featured Image')
                    ->image()
                    ->disk('public')
                    ->directory('products/featured')
                    ->visibility('public')
                    ->imagePreviewHeight('220')
                    ->panelLayout('integrated'),

                FileUpload::make('gallery_images')
                    ->label('Gallery Images')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->disk('public')
                    ->directory('products/gallery')
                    ->visibility('public')
                    ->imagePreviewHeight('110')
                    ->panelLayout('grid'),
            ]);
    }

    protected static function codesSection(): Section
    {
        return Section::make('Product Codes')
            ->columns(1)
            ->schema([
                TextInput::make('sku')
                    ->label('SKU')
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                TextInput::make('barcode')
                    ->label('Barcode')
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
            ])
            ->visible(fn (Get $get): bool => $get('product_type') === 'simple');
    }

    /** @return array<string, string> */
    protected static function stockStatusOptions(): array
    {
        return [
            'in_stock' => 'In Stock',
            'out_of_stock' => 'Out of Stock',
            'on_backorder' => 'On Backorder',
        ];
    }
}
