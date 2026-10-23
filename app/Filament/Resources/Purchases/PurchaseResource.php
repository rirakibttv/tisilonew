<?php

namespace App\Filament\Resources\Purchases;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Purchases\Pages\EditPurchase;
use App\Filament\Resources\Purchases\Pages\ListPurchases;
use App\Models\AccountingPurchase;
use App\Models\AccountingSupplier;
use App\Models\FundAccount;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PurchaseResource extends Resource
{
    protected static ?string $model = AccountingPurchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Accounts;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Purchase';

    protected static ?string $pluralModelLabel = 'Purchases';

    protected static ?string $recordTitleAttribute = 'purchase_number';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('accounts.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Purchase Invoice')->columns(2)->schema([
                Select::make('supplier_id')
                    ->label('Supplier')
                    ->options(fn (): array => AccountingSupplier::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->preload()->required(),
                DatePicker::make('purchase_date')->required()->default(now()),
                TextInput::make('invoice_number')->label('Supplier Invoice Number')->maxLength(120),
                Repeater::make('line_items')
                    ->label('Product Details')
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()->required(),
                        TextInput::make('quantity')->numeric()->integer()->minValue(1)->default(1)->required()->live(onBlur: true),
                        TextInput::make('unit_cost')->label('Unit Cost (৳)')->numeric()->minValue(0.01)->required()->prefix('৳')->live(onBlur: true),
                        TextInput::make('discount')->numeric()->minValue(0)->default(0)->prefix('৳')->live(onBlur: true),
                        TextInput::make('shipping_cost')->label('Shipping Cost')->numeric()->minValue(0)->default(0)->prefix('৳')->live(onBlur: true),
                    ])
                    ->columns(5)->minItems(1)->defaultItems(1)->columnSpanFull(),
                Placeholder::make('purchase_total_preview')
                    ->label('Purchase Total')
                    ->content(function (Get $get): string {
                        $total = collect($get('line_items') ?? [])->sum(function (array $item): float {
                            return max(0, ((int) ($item['quantity'] ?? 0) * (float) ($item['unit_cost'] ?? 0))
                                - (float) ($item['discount'] ?? 0) + (float) ($item['shipping_cost'] ?? 0));
                        });

                        return '৳'.number_format($total, 2);
                    }),
                TextInput::make('paid_amount')
                    ->label('Paid Now')
                    ->numeric()->minValue(0)->default(0)->required()->prefix('৳')
                    ->helperText('Unpaid remainder is shown as supplier due.'),
                Select::make('payment_account_id')
                    ->label('Paid From Fund / Account')
                    ->options(fn (): array => FundAccount::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->preload()
                    ->required(fn (Get $get): bool => (float) ($get('paid_amount') ?: 0) > 0),
                Textarea::make('description')->label('Note')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('purchase_number')->label('Purchase #')->searchable()->copyable()->sortable(),
            TextColumn::make('supplier.name')->searchable()->sortable(),
            TextColumn::make('purchase_date')->date('d M Y')->sortable(),
            TextColumn::make('invoice_number')->label('Invoice #')->searchable(),
            TextColumn::make('line_items')
                ->label('Products')
                ->state(fn (AccountingPurchase $record): string => collect($record->line_items ?? [])
                    ->map(fn (array $item): string => ($item['product_name'] ?? 'Product').' × '.($item['quantity'] ?? 0))
                    ->implode(', '))
                ->wrap()->limit(80),
            TextColumn::make('total_amount')->money('BDT')->sortable(),
            TextColumn::make('paid_amount')->label('Paid')->money('BDT'),
            TextColumn::make('due_amount')->label('Due')->state(fn (AccountingPurchase $record): float => $record->due_amount)->money('BDT')->color(fn (AccountingPurchase $record): string => $record->due_amount > 0 ? 'danger' : 'success'),
            TextColumn::make('paymentAccount.name')->label('Paid From'),
        ])->filters([
            SelectFilter::make('supplier_id')->label('Supplier')->relationship('supplier', 'name')->searchable()->preload(),
        ])->recordActions([EditAction::make()])->defaultSort('purchase_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchases::route('/'),
            'create' => CreatePurchase::route('/create'),
            'edit' => EditPurchase::route('/{record}/edit'),
        ];
    }
}
