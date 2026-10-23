<?php

namespace App\Filament\Resources\FundAccounts\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Fund Transactions';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Record a Fund Movement')->columns(2)->schema([
                Select::make('direction')->label('Transaction')->options(['in' => 'Deposit / Fund In', 'out' => 'Withdrawal / Fund Out'])->required(),
                TextInput::make('amount')->numeric()->minValue(0.01)->required()->prefix('৳'),
                DatePicker::make('transaction_date')->required()->default(now()),
                TextInput::make('reference_number')->maxLength(120),
                Textarea::make('description')->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make()->mutateDataUsing(fn (array $data): array => [
                    ...$data,
                    'created_by' => auth()->id(),
                ]),
            ])
            ->columns([
                TextColumn::make('transaction_date')->date('d M Y')->sortable(),
                TextColumn::make('direction')->badge()->formatStateUsing(fn (string $state): string => $state === 'in' ? 'Fund In' : 'Fund Out'),
                TextColumn::make('amount')->money('BDT')->sortable(),
                TextColumn::make('reference_number')->searchable(),
                TextColumn::make('description')->limit(60),
                TextColumn::make('creator.name')->label('Recorded By'),
            ])->defaultSort('transaction_date', 'desc');
    }
}
