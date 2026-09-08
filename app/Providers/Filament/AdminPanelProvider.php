<?php

namespace App\Providers\Filament;

use App\Enums\AdminNavigationGroup;
use App\Filament\Pages\ApiIntegrationSettings;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\FraudChecker;
use App\Filament\Pages\GeneralSettings;
use App\Filament\Pages\ModuleOverview;
use App\Filament\Pages\SeoOverview;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\IncompleteOrders\Pages\ListIncompleteOrders;
use App\Filament\Resources\LandingPages\Pages\CreateLandingPage;
use App\Filament\Resources\LandingPages\Pages\ListLandingPages;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ListPendingOrders;
use App\Filament\Resources\Orders\Pages\ListVendorOrders;
use App\Filament\Resources\Permissions\Pages\ListPermissions;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\PendingProducts;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Users\Pages\ListUsers;
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
            ->passwordReset()
            ->brandName('Tisilo')
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('15.5rem')
            ->darkMode(false)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn () => view('filament.partials.visit-site'),
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn () => view('filament.partials.sidebar-accordion'),
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
                    array_values(array_filter(
                        AdminNavigationGroup::cases(),
                        fn (AdminNavigationGroup $group): bool => ! in_array($group, [
                            AdminNavigationGroup::PosSystem,
                            AdminNavigationGroup::FraudCheckerApi,
                            AdminNavigationGroup::OrderPanel,
                            AdminNavigationGroup::Shipping,
                            AdminNavigationGroup::LandingPage,
                            AdminNavigationGroup::SeoOverview,
                            AdminNavigationGroup::ApiIntegration,
                            AdminNavigationGroup::User,
                            AdminNavigationGroup::Pages,
                            AdminNavigationGroup::GeneralSettings,
                        ], true),
                    )),
                ),

                NavigationItem::make('POS System')
                    ->key('pos-system-direct')
                    ->icon(Heroicon::OutlinedCalculator)
                    ->sort(-1)
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.module-overview')
                        && request()->string('module')->toString() === AdminNavigationGroup::PosSystem->slug())
                    ->url(fn (): string => ModuleOverview::getUrl(['module' => AdminNavigationGroup::PosSystem->slug()])),

                NavigationItem::make('Fraud Checker API')
                    ->key('fraud-checker-direct')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->sort(0)
                    ->visible(fn (): bool => FraudChecker::canAccess())
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.fraud-checker'))
                    ->url(fn (): string => FraudChecker::getUrl()),

                ...$this->orderPanelNavigationItems(),

                ...$this->landingPageNavigationItems(),

                ...$this->apiIntegrationNavigationItems(),

                ...$this->userNavigationItems(),

                ...$this->contentPageNavigationItems(),

                ...$this->generalSettingsNavigationItems(),

                NavigationItem::make('Visitor Analytics')
                    ->key('seo-overview-master')
                    ->group(AdminNavigationGroup::SeoOverview)
                    ->icon(Heroicon::OutlinedChartBarSquare)
                    ->sort(-100)
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.seo-overview')
                        && request()->string('platform', 'all')->toString() === 'all')
                    ->url(fn (): string => SeoOverview::getUrl(['platform' => 'all'])),

                NavigationItem::make('Facebook Overview')
                    ->key('seo-overview-facebook')
                    ->group(AdminNavigationGroup::SeoOverview)
                    ->icon(Heroicon::OutlinedShare)
                    ->sort(-99)
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.seo-overview')
                        && request()->string('platform')->toString() === 'facebook')
                    ->url(fn (): string => SeoOverview::getUrl(['platform' => 'facebook'])),

                NavigationItem::make('Google Overview')
                    ->key('seo-overview-google')
                    ->group(AdminNavigationGroup::SeoOverview)
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->sort(-98)
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.seo-overview')
                        && request()->string('platform')->toString() === 'google')
                    ->url(fn (): string => SeoOverview::getUrl(['platform' => 'google'])),

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

    /** @return array<NavigationItem> */
    private function landingPageNavigationItems(): array
    {
        return [
            NavigationItem::make('Create Landing Page')
                ->key('landing-page-create')
                ->group(AdminNavigationGroup::LandingPage)
                ->icon(Heroicon::OutlinedPlusCircle)
                ->sort(0)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.landing-pages.create'))
                ->url(fn (): string => CreateLandingPage::getUrl()),

            NavigationItem::make('All Landing Pages')
                ->key('landing-page-all')
                ->group(AdminNavigationGroup::LandingPage)
                ->icon(Heroicon::OutlinedWindow)
                ->sort(1)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.landing-pages.index'))
                ->url(fn (): string => ListLandingPages::getUrl()),
        ];
    }

    /** @return array<NavigationItem> */
    private function orderPanelNavigationItems(): array
    {
        return [
            NavigationItem::make('Pending Order')
                ->key('order-panel-pending')
                ->group(AdminNavigationGroup::OrderPanel)
                ->icon(Heroicon::OutlinedClock)
                ->sort(0)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.orders.pending'))
                ->url(fn (): string => ListPendingOrders::getUrl()),

            NavigationItem::make('Incomplete Order')
                ->key('order-panel-incomplete')
                ->group(AdminNavigationGroup::OrderPanel)
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->sort(1)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.incomplete-orders.*'))
                ->url(fn (): string => ListIncompleteOrders::getUrl()),

            NavigationItem::make('Vendor Order')
                ->key('order-panel-vendor')
                ->group(AdminNavigationGroup::OrderPanel)
                ->icon(Heroicon::OutlinedBuildingStorefront)
                ->sort(2)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.orders.vendor'))
                ->url(fn (): string => ListVendorOrders::getUrl()),

            NavigationItem::make('All Order')
                ->key('order-panel-all')
                ->group(AdminNavigationGroup::OrderPanel)
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->sort(3)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.orders.index'))
                ->url(fn (): string => ListOrders::getUrl()),
        ];
    }

    /** @return array<NavigationItem> */
    private function apiIntegrationNavigationItems(): array
    {
        $items = [
            ['payment', 'Payment Gateway', Heroicon::OutlinedCreditCard],
            ['sms', 'SMS Gateway', Heroicon::OutlinedChatBubbleLeftRight],
            ['courier', 'Courier API', Heroicon::OutlinedTruck],
            ['facebook_capi', 'Facebook CAPI', Heroicon::OutlinedShare],
            ['facebook_auto_post', 'FB Auto Post', Heroicon::OutlinedPaperAirplane],
            ['search_console', 'Google Search Console', Heroicon::OutlinedMagnifyingGlass],
            ['fraud', 'Manage Fraud Checker', Heroicon::OutlinedShieldCheck],
            ['google_analytics', 'Google Analytics', Heroicon::OutlinedChartBarSquare],
            ['google_tag_manager', 'Google Tag Manager', Heroicon::OutlinedTag],
            ['cloudflare', 'Cloudflare API', Heroicon::OutlinedGlobeAlt],
        ];

        return array_map(
            fn (array $item, int $sort): NavigationItem => NavigationItem::make($item[1])
                ->key('api-integration-'.$item[0])
                ->group(AdminNavigationGroup::ApiIntegration)
                ->icon($item[2])
                ->sort($sort)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.api-integrations')
                    && request()->string('section', 'payment')->toString() === $item[0])
                ->url(fn (): string => ApiIntegrationSettings::getUrl(['section' => $item[0]])),
            $items,
            array_keys($items),
        );
    }

    /** @return array<NavigationItem> */
    private function generalSettingsNavigationItems(): array
    {
        $items = [
            ['general', 'General Setting', Heroicon::OutlinedCog6Tooth],
            ['seo', 'SEO Settings', Heroicon::OutlinedMagnifyingGlass],
            ['social', 'Social Media', Heroicon::OutlinedShare],
            ['contact', 'Contact', Heroicon::OutlinedPhone],
            ['pages', 'Create Page', Heroicon::OutlinedDocumentPlus],
            ['order_restriction', 'Order Restriction', Heroicon::OutlinedAdjustmentsHorizontal],
            ['email', 'Email Settings', Heroicon::OutlinedEnvelope],
            ['cronjob', 'Cronjob', Heroicon::OutlinedClock],
            ['sitemap', 'Sitemap Settings', Heroicon::OutlinedGlobeAlt],
        ];

        return array_map(
            fn (array $item, int $sort): NavigationItem => NavigationItem::make($item[1])
                ->key('general-settings-'.$item[0])
                ->group(AdminNavigationGroup::GeneralSettings)
                ->icon($item[2])
                ->sort($sort)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.general-settings')
                    && request()->string('section', 'general')->toString() === $item[0])
                ->url(fn (): string => GeneralSettings::getUrl(['section' => $item[0]])),
            $items,
            array_keys($items),
        );
    }

    /** @return array<NavigationItem> */
    private function contentPageNavigationItems(): array
    {
        $items = [
            [null, 'All Pages', Heroicon::OutlinedDocumentText],
            ['contact-us', 'Contact Us', Heroicon::OutlinedPhone],
            ['blog', 'Blog', Heroicon::OutlinedBookOpen],
            ['about-us', 'About Us', Heroicon::OutlinedInformationCircle],
        ];

        return array_map(
            fn (array $item, int $sort): NavigationItem => NavigationItem::make($item[1])
                ->key('content-pages-'.($item[0] ?? 'all'))
                ->group(AdminNavigationGroup::Pages)
                ->icon($item[2])
                ->sort($sort)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.general-settings')
                    && request()->string('section')->toString() === 'pages'
                    && request()->string('page')->toString() === ($item[0] ?? ''))
                ->url(fn (): string => GeneralSettings::getUrl(array_filter([
                    'section' => 'pages',
                    'page' => $item[0],
                ]))),
            $items,
            array_keys($items),
        );
    }

    /** @return array<NavigationItem> */
    private function userNavigationItems(): array
    {
        return [
            NavigationItem::make('User')
                ->key('user-all')
                ->group(AdminNavigationGroup::User)
                ->icon(Heroicon::OutlinedUser)
                ->sort(0)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.users.*'))
                ->url(fn (): string => ListUsers::getUrl()),

            NavigationItem::make('Roles')
                ->key('user-roles')
                ->group(AdminNavigationGroup::User)
                ->icon(Heroicon::OutlinedIdentification)
                ->sort(1)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.roles.*'))
                ->url(fn (): string => ListRoles::getUrl()),

            NavigationItem::make('Permission')
                ->key('user-permissions')
                ->group(AdminNavigationGroup::User)
                ->icon(Heroicon::OutlinedKey)
                ->sort(2)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.permissions.*'))
                ->url(fn (): string => ListPermissions::getUrl()),

            NavigationItem::make('Customer')
                ->key('user-customers')
                ->group(AdminNavigationGroup::User)
                ->icon(Heroicon::OutlinedUsers)
                ->sort(3)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.customers.*'))
                ->url(fn (): string => ListCustomers::getUrl()),
        ];
    }
}
