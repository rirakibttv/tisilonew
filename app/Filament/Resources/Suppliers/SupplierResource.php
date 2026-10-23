<?php

namespace App\Filament\Resources\Suppliers;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\AccountingSupplier;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SupplierResource extends Resource
{
    protected static ?string $model = AccountingSupplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Accounts;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Supplier';

    protected static ?string $pluralModelLabel = 'Suppliers';

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

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && ! $record->purchases()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Supplier Details')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('contact_person')->label('Contact Person')->maxLength(255),
                TextInput::make('phone')->tel()->maxLength(32),
                TextInput::make('email')->email()->maxLength(255),
                Textarea::make('address')->rows(3)->columnSpanFull(),
                Toggle::make('is_active')->label('Active')->default(true),
                Textarea::make('notes')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('contact_person')->label('Contact')->searchable(),
            TextColumn::make('phone')->searchable()->copyable(),
            TextColumn::make('purchases_count')->counts('purchases')->label('Purchases')->sortable(),
            TextColumn::make('purchases_sum_due_amount')
                ->label('Outstanding')
                ->state(fn (AccountingSupplier $record): float => $record->outstanding_balance)
                ->money('BDT'),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ])->filters([TernaryFilter::make('is_active')->label('Status')])
            ->recordActions([EditAction::make()])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
