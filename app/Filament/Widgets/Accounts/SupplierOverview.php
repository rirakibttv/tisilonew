<?php

namespace App\Filament\Widgets\Accounts;

use App\Models\AccountingPurchase;
use App\Models\AccountingSupplier;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SupplierOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Registered Suppliers', AccountingSupplier::query()->count())->description('All suppliers')->color('primary'),
            Stat::make('Active Suppliers', AccountingSupplier::query()->where('is_active', true)->count())->description('Currently active')->color('success'),
            Stat::make('Supplier Due', '৳'.number_format((float) AccountingPurchase::query()->sum('total_amount') - (float) AccountingPurchase::query()->sum('paid_amount'), 2))->description('Outstanding purchase balance')->color('danger'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}
