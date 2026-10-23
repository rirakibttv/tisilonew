<?php

namespace App\Filament\Widgets\Accounts;

use App\Models\FundAccount;
use App\Models\FundTransaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FundOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $accounts = FundAccount::query()->where('is_active', true)->get();
        $balance = $accounts->sum(fn (FundAccount $account): float => $account->balance);
        $transactions = FundTransaction::query();

        return [
            Stat::make('Available Balance', '৳'.number_format((float) $balance, 2))->description('In − Out across active accounts')->color('success'),
            Stat::make('This Year ('.now()->year.')', '৳'.number_format((float) (clone $transactions)->where('direction', 'in')->whereYear('transaction_date', now()->year)->sum('amount'), 2))->description('Fund added this year')->color('primary'),
            Stat::make('This Month ('.now()->format('F').')', '৳'.number_format((float) (clone $transactions)->where('direction', 'in')->whereYear('transaction_date', now()->year)->whereMonth('transaction_date', now()->month)->sum('amount'), 2))->description('Fund added this month')->color('info'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}
