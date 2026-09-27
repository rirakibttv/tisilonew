<?php

namespace App\Filament\Seller\Resources\Orders;

use App\Enums\OrderStatus;
use App\Filament\Seller\Resources\Orders\Pages\EditOrder;
use App\Filament\Seller\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Support\SellerAccess;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Orders';

    protected static ?string $recordTitleAttribute = 'order_number';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order Processing')
                ->columns(2)
                ->schema([
                    TextInput::make('order_number')->disabled()->dehydrated(false),
                    Select::make('status')
                        ->options(collect(OrderStatus::options())->except([OrderStatus::Refunded->value])->all())
                        ->required(),
                    TextInput::make('tracking_number')->label('Tracking Number')->maxLength(255),
                    Placeholder::make('payment')
                        ->label('Payment')
                        ->content(fn (?Order $record): string => $record
                            ? ($record->payment_status->label().' · '.ucfirst(str_replace('_', ' ', (string) $record->payment_method)))
                            : '—'),
                    Textarea::make('notes')->label('Vendor / Fulfillment Note')->rows(3)->columnSpanFull(),
                ]),
            Section::make('Customer & Vendor Amount')
                ->columns(3)
                ->schema([
                    Placeholder::make('customer')
                        ->content(fn (?Order $record): string => $record?->customer_name ?? '—'),
                    Placeholder::make('mobile')
                        ->content(fn (?Order $record): string => $record?->customer_phone ?: '—'),
                    Placeholder::make('vendor_total')
                        ->label('Your Order Amount')
                        ->content(fn (?Order $record): string => '৳'.number_format((float) ($record?->items()
                            ->where('vendor_id', SellerAccess::currentVendor()?->getKey() ?? 0)
                            ->sum('total_amount') ?? 0), 2)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')->label('Order')->searchable()->copyable()->sortable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->description(fn (Order $record): string => $record->customer_phone ?: 'No phone')
                    ->searchable(['customer_name', 'customer_phone']),
                TextColumn::make('vendor_items')
                    ->label('Items')
                    ->getStateUsing(fn (Order $record): int => (int) $record->items->sum('quantity')),
                TextColumn::make('vendor_total')
                    ->label('Your Amount')
                    ->getStateUsing(fn (Order $record): float => (float) $record->items->sum('total_amount'))
                    ->money('BDT'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => $state->color()),
                TextColumn::make('placed_at')->label('Placed')->dateTime('d M Y, h:i A')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(OrderStatus::options()),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (Order $record): bool => static::canEdit($record)),
            ])
            ->defaultSort('placed_at', 'desc')
            ->defaultPaginationPageOption(50);
    }

    public static function getEloquentQuery(): Builder
    {
        $vendorId = SellerAccess::currentVendor()?->getKey() ?? 0;

        return parent::getEloquentQuery()
            ->whereHas('items', fn (Builder $query): Builder => $query->where('vendor_id', $vendorId))
            ->with(['items' => fn ($query) => $query->where('vendor_id', $vendorId)]);
    }

    public static function canViewAny(): bool
    {
        return SellerAccess::can(SellerAccess::ORDERS_VIEW);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        $vendorId = SellerAccess::currentVendor()?->getKey() ?? 0;

        return SellerAccess::can(SellerAccess::ORDERS_MANAGE)
            && $record->items()->where('vendor_id', $vendorId)->exists();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
