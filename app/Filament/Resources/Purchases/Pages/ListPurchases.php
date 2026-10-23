<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Widgets\Accounts\PurchaseOverview;
use App\Models\AccountingPurchase;
use App\Support\AccountingCsvExporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;

class ListPurchases extends ListRecords
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderWidgets(): array
    {
        return [PurchaseOverview::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Purchase Entry'),
            Action::make('export_purchases')
                ->label('Export Report')
                ->icon('heroicon-o-arrow-down-tray')
                ->schema([
                    DatePicker::make('from')->label('From Date'),
                    DatePicker::make('to')->label('To Date')->afterOrEqual('from'),
                ])
                ->action(function (array $data) {
                    $query = AccountingPurchase::query()->with('supplier')->orderBy('purchase_date');
                    if ($data['from'] ?? null) {
                        $query->whereDate('purchase_date', '>=', $data['from']);
                    }
                    if ($data['to'] ?? null) {
                        $query->whereDate('purchase_date', '<=', $data['to']);
                    }

                    $rows = $query->cursor()->map(fn (AccountingPurchase $purchase): array => [
                        $purchase->purchase_number,
                        $purchase->purchase_date->toDateString(),
                        $purchase->invoice_number,
                        $purchase->supplier?->name,
                        $purchase->total_amount,
                        $purchase->paid_amount,
                        $purchase->due_amount,
                        $purchase->description,
                    ]);

                    return AccountingCsvExporter::download('purchases-report.csv', ['Purchase #', 'Date', 'Invoice', 'Supplier', 'Total (BDT)', 'Paid (BDT)', 'Due (BDT)', 'Note'], $rows);
                }),
        ];
    }
}
