<?php

namespace App\Filament\Resources\VendorListings\Schemas;

use App\Enums\FulfillmentType;
use App\Enums\ProductCondition;
use App\Enums\VendorListingStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorListingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Marketplace Listing')
                ->description('Connect a vendor to one product in the central catalog.')
                ->columns(2)
                ->schema([
                    Select::make('vendor_id')
                        ->relationship('vendor', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('product_id')
                        ->relationship('product', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('shipping_class_id')
                        ->label('Shipping Class')
                        ->relationship('shippingClass', 'name', fn ($query) => $query->where('is_active', true))
                        ->searchable()
                        ->preload()
                        ->helperText('Checkout charge is calculated from this class. Leave empty to use the product default.'),

                    Select::make('status')
                        ->options(VendorListingStatus::options())
                        ->default(VendorListingStatus::Draft->value)
                        ->required(),

                    Select::make('condition')
                        ->options(ProductCondition::options())
                        ->default(ProductCondition::New->value)
                        ->required(),

                    Select::make('fulfillment_type')
                        ->label('Fulfillment')
                        ->options(FulfillmentType::options())
                        ->default(FulfillmentType::Vendor->value)
                        ->required(),

                    TextInput::make('warranty')
                        ->maxLength(255)
                        ->placeholder('Example: 1 year seller warranty'),

                    TextInput::make('min_order_quantity')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),

                    TextInput::make('max_order_quantity')
                        ->numeric()
                        ->minValue(1)
                        ->gte('min_order_quantity'),

                    TextInput::make('handling_time_days')
                        ->numeric()
                        ->minValue(0)
                        ->default(1)
                        ->suffix('days')
                        ->required(),

                    TextInput::make('commission_rate_override')
                        ->label('Commission Override')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%'),

                    Toggle::make('is_featured')
                        ->label('Featured Listing')
                        ->default(false),

                    Textarea::make('rejection_reason')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
