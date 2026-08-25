<?php

namespace App\Providers\Filament;

use App\Enums\AdminNavigationGroup;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ModuleOverview;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\PendingProducts;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Tisilo')
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('15.5rem')
            ->darkMode(false)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn () => view('filament.partials.visit-site'),
            )
            ->colors([
                'primary' => Color::Indigo,
            ])

            ->navigationGroups(AdminNavigationGroup::class)

            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\Filament\Resources'
            )

            ->discoverPages(
                in: app_path('Filament/Pages'),
                for: 'App\Filament\Pages'
            )

            ->pages([
                Dashboard::class,
            ])

            ->navigationItems([
                ...array_map(
                    fn (AdminNavigationGroup $group): NavigationItem => NavigationItem::make('Overview')
                        ->key('module-overview-'.$group->name)
                        ->group($group)
                        ->icon($group->getIcon())
                        ->sort(-100)
                        ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.module-overview')
                            && request()->string('module')->toString() === $group->slug())
                        ->url(fn (): string => ModuleOverview::getUrl(['module' => $group->slug()])),
                    AdminNavigationGroup::cases(),
                ),

                NavigationItem::make('Add New Product')
                    ->group(AdminNavigationGroup::ProductsInfo)
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->sort(1)
                    ->url(fn (): string => CreateProduct::getUrl()),

                NavigationItem::make('Pending Products')
                    ->group(AdminNavigationGroup::ProductsInfo)
                    ->icon(Heroicon::OutlinedClock)
                    ->sort(2)
                    ->url(fn (): string => PendingProducts::getUrl()),

                NavigationItem::make('All Products')
                    ->group(AdminNavigationGroup::ProductsInfo)
                    ->icon(Heroicon::OutlinedShoppingBag)
                    ->sort(3)
                    ->url(fn (): string => ListProducts::getUrl()),
            ])

            ->discoverWidgets(
                in: app_path('Filament/Widgets'),
                for: 'App\Filament\Widgets'
            )

            ->widgets([
                AccountWidget::class,
            ])

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

            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
