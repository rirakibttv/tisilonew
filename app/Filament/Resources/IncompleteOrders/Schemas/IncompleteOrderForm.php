<?php

namespace App\Filament\Resources\IncompleteOrders\Schemas;

use App\Enums\IncompleteOrderStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IncompleteOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Recovery Status')->columns(2)->schema([
                Select::make('status')->options(IncompleteOrderStatus::options())->required(),
                DateTimePicker::make('last_activity_at')->label('Last Activity'),
                Select::make('converted_order_id')
                    ->label('Converted Order')
                    ->relationship('convertedOrder', 'order_number')
                    ->searchable()
                    ->preload(),
                TextInput::make('total_amount')->numeric()->minValue(0)->prefix('৳')->required(),
            ]),
            Section::make('Customer')->columns(2)->schema([
                TextInput::make('customer_name')->maxLength(255),
                TextInput::make('customer_phone')->tel()->maxLength(32),
                TextInput::make('customer_email')->email()->maxLength(255),
                Textarea::make('customer_address')->rows(3),
            ]),
            Section::make('Recovery Notes')->schema([
                Textarea::make('notes')->rows(4),
            ]),
        ]);
    }
}
