<?php

namespace App\Filament\Seller\Pages;

use App\Models\FraudCheckHistory;
use App\Support\SellerAccess;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class FraudChecker extends \App\Filament\Pages\FraudChecker
{
    protected static bool $shouldRegisterNavigation = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Fraud Checker';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return SellerAccess::can(SellerAccess::FRAUD_CHECK);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Fraud Checker';
    }

    public function getSubheading(): ?string
    {
        return 'অর্ডার নিশ্চিত করার আগে customer-এর courier delivery history যাচাই করুন';
    }

    protected function historyQuery(): Builder
    {
        return FraudCheckHistory::query()
            ->where('vendor_id', SellerAccess::currentVendor()?->getKey() ?? 0);
    }

    protected function historyVendorId(): ?int
    {
        return SellerAccess::currentVendor()?->getKey();
    }
}
