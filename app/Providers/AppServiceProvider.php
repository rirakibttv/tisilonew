<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Order;
use App\Models\PopupOffer;
use App\Models\SiteSetting;
use App\Observers\OrderObserver;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as IlluminateView;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $canonicalRoot = rtrim((string) config('app.canonical_url'), '/');
        $canonicalScheme = parse_url($canonicalRoot, PHP_URL_SCHEME);

        if ($canonicalRoot !== '' && filter_var($canonicalRoot, FILTER_VALIDATE_URL) && is_string($canonicalScheme)) {
            URL::forceRootUrl($canonicalRoot);
            URL::forceScheme($canonicalScheme);
        }

        Order::observe(OrderObserver::class);

        View::composer('storefront.partials.header', function (IlluminateView $view): void {
            try {
                $view->with(
                    'navigationCategories',
                    Schema::hasTable('categories') ? Category::storefrontNavigation() : collect(),
                );
            } catch (Throwable $exception) {
                report($exception);
                $view->with('navigationCategories', collect());
            }
        });

        try {
            if (! Schema::hasTable('site_settings')) {
                return;
            }

            View::composer('*', function (IlluminateView $view): void {
                $metaPixel = SiteSetting::valuesFor('facebook_capi');

                $view->with([
                    'generalSettings' => SiteSetting::valuesFor('general'),
                    'seoSettings' => SiteSetting::valuesFor('seo'),
                    'contactSettings' => SiteSetting::valuesFor('contact'),
                    'googleAnalyticsSettings' => SiteSetting::valuesFor('google_analytics'),
                    'metaPixelSettings' => [
                        'enabled' => filter_var($metaPixel['enabled'] ?? false, FILTER_VALIDATE_BOOL),
                        'pixel_id' => $metaPixel['pixel_id'] ?? null,
                        'events' => is_array($metaPixel['events'] ?? null) ? $metaPixel['events'] : [],
                    ],
                    'socialLinks' => SiteSetting::valuesFor('social')['links'] ?? [],
                    'contentPages' => collect(SiteSetting::valuesFor('pages')['pages'] ?? [])
                        ->where('status', true)
                        ->values()
                        ->all(),
                ]);
            });

            View::composer('layouts.storefront', function (IlluminateView $view): void {
                $view->with(
                    'activePopupOffer',
                    Schema::hasTable('popup_offers') ? PopupOffer::activeForStorefront() : null,
                );
            });

            $mail = SiteSetting::valuesFor('email');
            $mailSecrets = SiteSetting::secretsFor('email');
            config([
                'mail.default' => $mail['MAIL_MAILER'] ?? config('mail.default'),
                'mail.mailers.smtp.host' => $mail['MAIL_HOST'] ?? config('mail.mailers.smtp.host'),
                'mail.mailers.smtp.port' => $mail['MAIL_PORT'] ?? config('mail.mailers.smtp.port'),
                'mail.mailers.smtp.encryption' => ($mail['MAIL_ENCRYPTION'] ?? null) === 'none'
                    ? null
                    : ($mail['MAIL_ENCRYPTION'] ?? config('mail.mailers.smtp.encryption')),
                'mail.mailers.smtp.username' => $mail['MAIL_USERNAME'] ?? config('mail.mailers.smtp.username'),
                'mail.mailers.smtp.password' => $mailSecrets['MAIL_PASSWORD'] ?? config('mail.mailers.smtp.password'),
                'mail.from.address' => $mail['MAIL_FROM_ADDRESS'] ?? config('mail.from.address'),
                'mail.from.name' => $mail['MAIL_FROM_NAME'] ?? config('mail.from.name'),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
