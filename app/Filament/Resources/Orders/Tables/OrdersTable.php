<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')->label('Order')->searchable()->copyable()->sortable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->description(fn ($record): string => $record->customer_phone ?: ($record->customer_email ?: 'Guest customer'))
                    ->searchable(['customer_name', 'customer_email', 'customer_phone'])
                    ->sortable(),
                TextColumn::make('items_count')->label('Items')->counts('items')->sortable(),
                TextColumn::make('items.vendor.name')
                    ->label('Vendor')
                    ->badge()
                    ->separator(', ')
                    ->limitList(2)
                    ->toggleable(),
                TextColumn::make('landingPage.name')
                    ->label('Landing Campaign')
                    ->placeholder('Direct / Storefront')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cod' => 'Cash on Delivery',
                        'bkash' => 'bKash',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->toggleable(),
                TextColumn::make('shippingRegion.upazila')
                    ->label('Delivery Upazila')
                    ->placeholder('Legacy zone')
                    ->toggleable(),
                TextColumn::make('shippingPartner.name')
                    ->label('Shipping Partner')
                    ->placeholder('Not assigned')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money(fn ($record): string => $record->currency ?: 'BDT')
                    ->sortable(),
                TextColumn::make('placed_at')->label('Placed')->dateTime('d M Y, h:i A')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(OrderStatus::options()),
                SelectFilter::make('payment_status')->label('Payment Status')->options(PaymentStatus::options()),
                SelectFilter::make('channel')->options([
                    'website' => 'Website',
                    'pos' => 'POS',
                    'manual' => 'Manual',
                ]),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('placed_at', 'desc')
            ->defaultPaginationPageOption(25);
    }
}
