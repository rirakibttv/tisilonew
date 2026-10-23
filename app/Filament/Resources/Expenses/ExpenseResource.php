<?php

namespace App\Filament\Resources\Expenses;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\AccountingExpense;
use App\Models\FundAccount;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ExpenseResource extends Resource
{
    protected static ?string $model = AccountingExpense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Accounts;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Expense';

    protected static ?string $pluralModelLabel = 'Expenses';

    protected static ?string $recordTitleAttribute = 'expense_number';

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
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Expense Details')->columns(2)->schema([
                TextInput::make('category')->label('Expense Category')->required()->maxLength(120)->placeholder('Rent, utilities, marketing...'),
                DatePicker::make('expense_date')->required()->default(now()),
                TextInput::make('payee')->label('Paid To')->maxLength(255),
                TextInput::make('amount')->numeric()->minValue(0.01)->required()->prefix('৳'),
                Select::make('fund_account_id')
                    ->label('Paid From Fund / Account')
                    ->options(fn (): array => FundAccount::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->preload()->required(),
                TextInput::make('reference_number')->label('Receipt / Reference #')->maxLength(120),
                Textarea::make('description')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('expense_number')->label('Expense #')->searchable()->copyable()->sortable(),
            TextColumn::make('category')->searchable()->sortable(),
            TextColumn::make('payee')->label('Paid To')->searchable(),
            TextColumn::make('expense_date')->date('d M Y')->sortable(),
            TextColumn::make('amount')->money('BDT')->sortable(),
            TextColumn::make('fundAccount.name')->label('Paid From')->searchable(),
            TextColumn::make('reference_number')->label('Reference #')->searchable(),
        ])->filters([
            SelectFilter::make('fund_account_id')->label('Fund / Account')->relationship('fundAccount', 'name')->searchable()->preload(),
        ])->recordActions([EditAction::make()])
            ->defaultSort('expense_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenses::route('/'),
            'create' => CreateExpense::route('/create'),
            'edit' => EditExpense::route('/{record}/edit'),
        ];
    }
}
