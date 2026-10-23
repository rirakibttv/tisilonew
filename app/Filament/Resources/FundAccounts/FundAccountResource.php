<?php

namespace App\Filament\Resources\FundAccounts;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\FundAccounts\Pages\CreateFundAccount;
use App\Filament\Resources\FundAccounts\Pages\EditFundAccount;
use App\Filament\Resources\FundAccounts\Pages\ListFundAccounts;
use App\Filament\Resources\FundAccounts\RelationManagers\TransactionsRelationManager;
use App\Models\FundAccount;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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

class FundAccountResource extends Resource
{
    protected static ?string $model = FundAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Accounts;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Fund / Account';

    protected static ?string $pluralModelLabel = 'Fund / Accounts';

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
            Section::make('Fund / Account Details')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(255),
                Select::make('type')->options([
                    'cash' => 'Cash',
                    'bank' => 'Bank',
                    'mobile_wallet' => 'Mobile Wallet',
                    'other' => 'Other',
                ])->required()->default('cash'),
                TextInput::make('account_number')->label('Account / Wallet Number')->maxLength(120),
                TextInput::make('opening_balance')->numeric()->minValue(0)->required()->default(0)->prefix('৳'),
                Toggle::make('is_active')->label('Active')->default(true),
                Textarea::make('notes')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('type')->badge()->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title()->toString()),
            TextColumn::make('account_number')->label('Account / Wallet')->searchable(),
            TextColumn::make('opening_balance')->money('BDT')->sortable(),
            TextColumn::make('balance')->label('Current Balance')->state(fn (FundAccount $record): float => $record->balance)->money('BDT'),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ])->filters([TernaryFilter::make('is_active')->label('Status')])
            ->recordActions([EditAction::make()])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [TransactionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFundAccounts::route('/'),
            'create' => CreateFundAccount::route('/create'),
            'edit' => EditFundAccount::route('/{record}/edit'),
        ];
    }
}
