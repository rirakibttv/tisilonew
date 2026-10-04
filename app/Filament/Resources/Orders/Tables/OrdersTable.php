<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ShippingPartner;
use App\Services\CourierShipmentService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use Throwable;

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
                TextColumn::make('confirmedBy.name')
                    ->label('Confirmed By')
                    ->placeholder('Not confirmed')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('shippingPartner.name')
                    ->label('Shipping')
                    ->placeholder('Not assigned')
                    ->toggleable(),
                TextColumn::make('tracking_number')
                    ->label('Shipping ID')
                    ->placeholder('Not booked')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('shipping_status')
                    ->label('Shipping Status')
                    ->placeholder('Not booked')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => filled($state)
                        ? str($state)->replace('_', ' ')->headline()->toString()
                        : 'Not booked')
                    ->color(fn (?string $state): string => match ($state) {
                        'delivered' => 'success',
                        'cancelled', 'returned', 'return_completed' => 'danger',
                        'picked_up', 'in_transit', 'shipped', 'out_for_delivery' => 'info',
                        default => 'warning',
                    })
                    ->toggleable(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money(fn ($record): string => $record->currency ?: 'BDT')
                    ->sortable(),
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
            ->recordActions([
                Action::make('confirmAndShip')
                    ->label('Confirm & Ship')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending)
                    ->modalHeading(fn (Order $record): string => "Confirm & ship {$record->order_number}")
                    ->modalDescription('Shipping partner নির্বাচন করলে courier API-তে shipment তৈরি হবে। সফল হলে তবেই order Confirmed হবে।')
                    ->modalSubmitActionLabel('Create Shipment')
                    ->schema([
                        Select::make('shipping_partner_id')
                            ->label('Shipping Partner')
                            ->options(fn (): array => app(CourierShipmentService::class)->availablePartnerOptions())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('শুধু active ও Courier API settings-এ enabled partner দেখানো হচ্ছে।'),
                    ])
                    ->action(function (Order $record, array $data, CourierShipmentService $shipments): void {
                        $record->refresh();

                        if ($record->status !== OrderStatus::Pending) {
                            throw ValidationException::withMessages([
                                'shipping_partner_id' => 'এই order ইতোমধ্যে confirm করা হয়েছে।',
                            ]);
                        }

                        $partner = ShippingPartner::query()
                            ->whereKey($data['shipping_partner_id'])
                            ->where('is_active', true)
                            ->first();

                        if (! $partner) {
                            throw ValidationException::withMessages([
                                'shipping_partner_id' => 'একটি active shipping partner নির্বাচন করুন।',
                            ]);
                        }

                        try {
                            $shipment = $shipments->book($record, $partner);

                            $record->update([
                                'status' => OrderStatus::Confirmed,
                                'confirmed_at' => now(),
                                'confirmed_by' => auth()->id(),
                                'shipping_partner_id' => $partner->getKey(),
                                'tracking_number' => $shipment['tracking_number'],
                                'shipping_status' => $shipment['shipping_status'],
                                'shipping_status_synced_at' => now(),
                            ]);

                            Notification::make()
                                ->title('Order confirmed and shipment created')
                                ->body("Shipping ID: {$shipment['tracking_number']}")
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);

                            throw ValidationException::withMessages([
                                'shipping_partner_id' => $exception->getMessage(),
                            ]);
                        }
                    }),
                Action::make('syncShipping')
                    ->label('Sync Shipping')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (Order $record): bool => filled($record->tracking_number) && filled($record->shipping_partner_id))
                    ->action(function (Order $record, CourierShipmentService $shipments): void {
                        try {
                            $status = $shipments->fetchStatus($record);
                            $record->update([
                                'shipping_status' => $status,
                                'shipping_status_synced_at' => now(),
                            ]);

                            Notification::make()
                                ->title('Shipping status updated')
                                ->body(str($status)->replace('_', ' ')->headline()->toString())
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title('Shipping status sync failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->defaultSort('placed_at', 'desc')
            ->defaultPaginationPageOption(25);
    }
}
