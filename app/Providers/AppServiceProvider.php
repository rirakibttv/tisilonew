<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Observers\OrderObserver;
use Illuminate\Support\Facades\Schema;
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
        Order::observe(OrderObserver::class);

        try {
            if (! Schema::hasTable('site_settings')) {
                return;
            }

            View::composer('*', function (IlluminateView $view): void {
                $view->with([
                    'generalSettings' => SiteSetting::valuesFor('general'),
                    'seoSettings' => SiteSetting::valuesFor('seo'),
                    'contactSettings' => SiteSetting::valuesFor('contact'),
                    'googleAnalyticsSettings' => SiteSetting::valuesFor('google_analytics'),
                    'socialLinks' => SiteSetting::valuesFor('social')['links'] ?? [],
                    'contentPages' => collect(SiteSetting::valuesFor('pages')['pages'] ?? [])
                        ->where('status', true)
                        ->values()
                        ->all(),
                ]);
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
