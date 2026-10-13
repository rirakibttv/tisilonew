<?php

namespace App\Filament\Resources\Coupons\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Coupon Information')
                ->description('Create a code customers can apply securely during checkout.')
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label('Coupon Code')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        ->maxLength(64)
                        ->helperText('Letters, numbers, dashes and underscores are allowed.'),
                    TextInput::make('name')
                        ->label('Campaign Name')
                        ->maxLength(255),
                    Textarea::make('description')
                        ->rows(3)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ]),

            Section::make('Discount Rules')
                ->columns(2)
                ->schema([
                    Select::make('discount_type')
                        ->label('Discount Type')
                        ->options([
                            'fixed' => 'Fixed Amount',
                            'percentage' => 'Percentage',
                        ])
                        ->default('fixed')
                        ->required()
                        ->live(),
                    TextInput::make('discount_value')
                        ->label('Discount Value')
                        ->numeric()
                        ->minValue(0.01)
                        ->required()
                        ->prefix(fn ($get): ?string => $get('discount_type') === 'percentage' ? null : '৳')
                        ->suffix(fn ($get): ?string => $get('discount_type') === 'percentage' ? '%' : null)
                        ->rules(fn ($get): array => $get('discount_type') === 'percentage'
                            ? ['numeric', 'min:0.01', 'max:100']
                            : ['numeric', 'min:0.01', 'max:999999999']),
                    TextInput::make('minimum_order_amount')
                        ->label('Minimum Order Amount')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->prefix('৳')
                        ->required(),
                    TextInput::make('maximum_discount_amount')
                        ->label('Maximum Discount')
                        ->numeric()
                        ->minValue(0.01)
                        ->prefix('৳')
                        ->helperText('Optional. Most useful for percentage coupons.'),
                ]),

            Section::make('Usage & Schedule')
                ->columns(2)
                ->schema([
                    TextInput::make('usage_limit')
                        ->label('Total Usage Limit')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->helperText('Leave blank for unlimited use.'),
                    TextInput::make('usage_limit_per_customer')
                        ->label('Limit Per Customer')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->helperText('Customers are matched securely by account or phone.'),
                    DateTimePicker::make('starts_at')
                        ->label('Starts At')
                        ->seconds(false),
                    DateTimePicker::make('expires_at')
                        ->label('Expires At')
                        ->seconds(false)
                        ->after('starts_at'),
                ]),
        ]);
    }
}
