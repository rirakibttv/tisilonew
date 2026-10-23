<?php

namespace App\Filament\Resources\FundAccounts\Pages;

use App\Filament\Resources\FundAccounts\FundAccountResource;
use App\Filament\Widgets\Accounts\FundOverview;
use App\Models\FundAccount;
use App\Models\FundTransaction;
use App\Support\AccountingCsvExporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListFundAccounts extends ListRecords
{
    protected static string $resource = FundAccountResource::class;

    protected function getHeaderWidgets(): array
    {
        return [FundOverview::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Account'),
            $this->fundMovementAction('in', 'Add Fund'),
            $this->fundMovementAction('out', 'Withdraw'),
            Action::make('export_fund_report')
                ->label('Export Report')
                ->icon('heroicon-o-arrow-down-tray')
                ->schema([
                    DatePicker::make('from')->label('From Date'),
                    DatePicker::make('to')->label('To Date')->afterOrEqual('from'),
                ])
                ->action(function (array $data) {
                    $query = FundTransaction::query()->with('account')->orderBy('transaction_date');
                    if ($data['from'] ?? null) {
                        $query->whereDate('transaction_date', '>=', $data['from']);
                    }
                    if ($data['to'] ?? null) {
                        $query->whereDate('transaction_date', '<=', $data['to']);
                    }

                    $rows = $query->cursor()->map(fn (FundTransaction $transaction): array => [
                        $transaction->id,
                        $transaction->transaction_date->toDateTimeString(),
                        $transaction->direction,
                        $transaction->source_type ? class_basename($transaction->source_type) : 'Manual',
                        $transaction->account?->name,
                        $transaction->amount,
                        $transaction->reference_number,
                        $transaction->description,
                    ]);

                    return AccountingCsvExporter::download('fund-transactions.csv', ['#', 'Date', 'Type', 'Source', 'Fund / Account', 'Amount (BDT)', 'Reference', 'Note'], $rows);
                }),
        ];
    }

    private function fundMovementAction(string $direction, string $label): Action
    {
        return Action::make('fund-'.$direction)
            ->label($label)
            ->icon($direction === 'in' ? 'heroicon-o-plus' : 'heroicon-o-minus')
            ->color($direction === 'in' ? 'success' : 'danger')
            ->schema([
                Select::make('fund_account_id')
                    ->label('Fund / Account')
                    ->options(fn (): array => FundAccount::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->required(),
                TextInput::make('amount')->numeric()->minValue(0.01)->required()->prefix('৳'),
                DatePicker::make('transaction_date')->required()->default(now()),
                Textarea::make('description')->label('Note')->rows(2)->maxLength(1000),
            ])
            ->action(function (array $data) use ($direction): void {
                FundTransaction::query()->create([
                    ...$data,
                    'direction' => $direction,
                    'created_by' => auth()->id(),
                ]);
                Notification::make()->title($direction === 'in' ? 'Fund added' : 'Withdrawal recorded')->success()->send();
            });
    }
}
