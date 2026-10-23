<?php

namespace App\Filament\Widgets\Accounts;

use App\Models\AccountingExpense;
use App\Models\FundAccount;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExpenseOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $expenses = AccountingExpense::query();
        $balance = FundAccount::query()->where('is_active', true)->get()->sum(fn (FundAccount $account): float => $account->balance);

        return [
            Stat::make('Available Balance', '৳'.number_format((float) $balance, 2))
                ->description('Current balance across active funds')->color('success'),
            Stat::make('This Year ('.now()->year.')', '৳'.number_format((float) (clone $expenses)->whereYear('expense_date', now()->year)->sum('amount'), 2))
                ->description('Total expenses this year')->color('primary'),
            Stat::make('This Month ('.now()->format('F').')', '৳'.number_format((float) (clone $expenses)->whereYear('expense_date', now()->year)->whereMonth('expense_date', now()->month)->sum('amount'), 2))
                ->description('Total expenses this month')->color('info'),
            Stat::make('Today ('.now()->format('d M Y').')', '৳'.number_format((float) (clone $expenses)->whereDate('expense_date', now()->toDateString())->sum('amount'), 2))
                ->description('Total expenses today')->color('danger'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
