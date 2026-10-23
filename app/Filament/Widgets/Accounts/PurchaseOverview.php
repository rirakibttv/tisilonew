<?php

namespace App\Filament\Widgets\Accounts;

use App\Models\AccountingPurchase;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PurchaseOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $today = now()->toDateString();
        $base = AccountingPurchase::query();

        return [
            Stat::make('This Year ('.now()->year.')', '৳'.number_format((float) (clone $base)->whereYear('purchase_date', now()->year)->sum('total_amount'), 2))
                ->description('Total purchase value this year')->color('success'),
            Stat::make(now()->format('F'), '৳'.number_format((float) (clone $base)->whereYear('purchase_date', now()->year)->whereMonth('purchase_date', now()->month)->sum('total_amount'), 2))
                ->description('Monthly purchase value')->color('info'),
            Stat::make('Today ('.now()->format('d M').')', '৳'.number_format((float) (clone $base)->whereDate('purchase_date', $today)->sum('total_amount'), 2))
                ->description('Purchase value today')->color('primary'),
            Stat::make('Supplier Due', '৳'.number_format((float) (clone $base)->sum('total_amount') - (float) (clone $base)->sum('paid_amount'), 2))
                ->description('Outstanding supplier balance')->color('danger'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
