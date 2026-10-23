<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Widgets\Accounts\ExpenseOverview;
use App\Models\AccountingExpense;
use App\Support\AccountingCsvExporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderWidgets(): array
    {
        return [ExpenseOverview::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Expense'),
            Action::make('export_expenses')
                ->label('Export Report')
                ->icon('heroicon-o-arrow-down-tray')
                ->schema([
                    DatePicker::make('from')->label('From Date'),
                    DatePicker::make('to')->label('To Date')->afterOrEqual('from'),
                ])
                ->action(function (array $data) {
                    $query = AccountingExpense::query()->orderBy('expense_date');
                    if ($data['from'] ?? null) {
                        $query->whereDate('expense_date', '>=', $data['from']);
                    }
                    if ($data['to'] ?? null) {
                        $query->whereDate('expense_date', '<=', $data['to']);
                    }

                    $rows = $query->cursor()->map(fn (AccountingExpense $expense): array => [
                        $expense->expense_number,
                        $expense->expense_date->toDateString(),
                        $expense->category,
                        $expense->payee,
                        $expense->amount,
                        $expense->fundAccount()->value('name'),
                        $expense->reference_number,
                        $expense->description,
                    ]);

                    return AccountingCsvExporter::download('expenses-report.csv', ['Expense #', 'Date', 'Category', 'Paid To', 'Amount (BDT)', 'Fund / Account', 'Reference', 'Note'], $rows);
                }),
        ];
    }
}
