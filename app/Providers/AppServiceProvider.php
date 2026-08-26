<?php

namespace App\Providers;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
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
        try {
            if (! Schema::hasTable('site_settings')) {
                return;
            }

            $general = SiteSetting::valuesFor('general');
            $seo = SiteSetting::valuesFor('seo');
            $contact = SiteSetting::valuesFor('contact');
            $social = SiteSetting::valuesFor('social')['links'] ?? [];
            $pages = collect(SiteSetting::valuesFor('pages')['pages'] ?? [])->where('status', true)->values()->all();

            View::share([
                'generalSettings' => $general,
                'seoSettings' => $seo,
                'contactSettings' => $contact,
                'socialLinks' => $social,
                'contentPages' => $pages,
            ]);

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
