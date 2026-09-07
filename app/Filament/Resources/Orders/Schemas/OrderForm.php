<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order Status')->columns(3)->schema([
                TextInput::make('order_number')->label('Order Number')->disabled()->dehydrated(false),
                Select::make('status')->options(OrderStatus::options())->required(),
                Select::make('payment_status')->label('Payment Status')->options(PaymentStatus::options())->required(),
                Select::make('payment_method')
                    ->label('Payment Method')
                    ->options(['cod' => 'Cash on Delivery', 'bkash' => 'bKash'])
                    ->required(),
                TextInput::make('channel')->required()->maxLength(30),
                TextInput::make('tracking_number')->label('Tracking Number')->maxLength(255),
                DateTimePicker::make('placed_at')->label('Placed At'),
            ]),

            Section::make('Customer')->columns(2)->schema([
                Select::make('user_id')
                    ->label('Customer Account')
                    ->relationship('customer', 'name')
                    ->getOptionLabelFromRecordUsing(fn (User $record): string => "{$record->name} ({$record->email})")
                    ->searchable(['name', 'email', 'phone'])
                    ->preload(),
                TextInput::make('customer_name')->required()->maxLength(255),
                TextInput::make('customer_email')->email()->maxLength(255),
                TextInput::make('customer_phone')->tel()->maxLength(32),
            ]),

            Section::make('Amounts')->columns(3)->schema([
                TextInput::make('subtotal_amount')->numeric()->minValue(0)->required()->prefix('৳'),
                TextInput::make('discount_amount')->numeric()->minValue(0)->required()->prefix('৳'),
                TextInput::make('shipping_amount')->numeric()->minValue(0)->required()->prefix('৳'),
                TextInput::make('shipping_zone')->label('Shipping Zone')->maxLength(255),
                Select::make('shipping_region_id')->label('Shipping Region')
                    ->relationship('shippingRegion', 'upazila')->disabled()->dehydrated(false),
                Select::make('shipping_partner_id')->label('Shipping Partner')
                    ->relationship('shippingPartner', 'name')->disabled()->dehydrated(false),
                TextInput::make('tax_amount')->numeric()->minValue(0)->required()->prefix('৳'),
                TextInput::make('total_amount')->numeric()->minValue(0)->required()->prefix('৳'),
                TextInput::make('currency')->required()->maxLength(3),
            ]),

            Section::make('Internal Notes')->schema([
                Textarea::make('notes')->rows(4)->columnSpanFull(),
            ]),
        ]);
    }
}
