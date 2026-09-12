<?php

namespace App\Providers\Filament;

use App\Filament\Seller\Auth\RegisterSeller;
use App\Filament\Seller\Pages\Dashboard;
use App\Support\SiteBranding;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SellerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('seller')
            ->path('seller')
            ->authGuard('seller')
            ->authPasswordBroker('users')
            ->login()
            ->registration(RegisterSeller::class)
            ->passwordReset()
            ->profile()
            ->brandName('Tisilo Seller Center')
            ->favicon(fn (): string => SiteBranding::faviconUrl())
            ->darkMode(false)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors(['primary' => Color::Indigo])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn () => view('filament.partials.visit-site'),
            )
            ->pages([Dashboard::class])
            ->widgets([AccountWidget::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
