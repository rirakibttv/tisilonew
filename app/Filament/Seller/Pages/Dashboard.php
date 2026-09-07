<?php

namespace App\Filament\Seller\Pages;

use App\Models\Vendor;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.seller.pages.dashboard';

    public function getTitle(): string|Htmlable
    {
        return 'Seller Center';
    }

    public function getSubheading(): ?string
    {
        return 'আপনার Tisilo seller account ও shop application';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = Filament::auth()->user();

        return [
            'seller' => $user,
            'vendor' => $user ? Vendor::query()->where('owner_id', $user->getKey())->first() : null,
        ];
    }
}
