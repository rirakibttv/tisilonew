<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class GeneralSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'general' => [
                'site_name' => 'Tisilo',
                'top_headline' => 'সারাদেশে দ্রুত ডেলিভারি',
                'footer_about_text' => 'বিশ্বস্ত বিক্রেতা, মানসম্মত পণ্য এবং নিরাপদ কেনাকাটার আধুনিক বাংলাদেশি মার্কেটপ্লেস।',
                'primary_color' => '#f97316',
                'secondary_color' => '#0f172a',
                'footer_color' => '#020617',
                'copyright_color' => '#0f172a',
                'show_all_products' => true,
                'show_category_wise_products' => true,
                'vendor_enabled' => true,
                'reseller_enabled' => false,
                'reseller_deposit_min' => 100,
                'reseller_deposit_max' => 1000000,
                'reseller_wallet_min_balance' => 0,
            ],
            'seo' => [
                'meta_title' => 'Tisilo — আপনার বিশ্বস্ত অনলাইন মার্কেটপ্লেস',
                'meta_tags' => 'tisilo, marketplace, ecommerce, bangladesh',
                'meta_description' => 'Tisilo—বিশ্বস্ত মাল্টি-ভেন্ডর অনলাইন মার্কেটপ্লেস। সেরা পণ্য, সেরা দাম ও নিরাপদ কেনাকাটা।',
            ],
            'social' => ['links' => []],
            'contact' => ['status' => true],
            'pages' => ['pages' => []],
            'order_restriction' => ['order_limit_time' => 24, 'order_limit_qty' => 2, 'enabled' => false],
            'email' => [
                'MAIL_MAILER' => 'smtp',
                'MAIL_PORT' => 587,
                'MAIL_ENCRYPTION' => 'tls',
                'MAIL_FROM_NAME' => 'Tisilo',
            ],
            'cronjob' => [
                'scheduler_enabled' => true,
                'frequency_minutes' => 1,
                'batch_size' => 50,
                'server_command' => '* * * * * php artisan schedule:run',
                'github_deploy_frequency_minutes' => 1,
                'github_deploy_branch' => 'main',
                'github_deploy_command' => '* * * * * scripts/deploy-production.sh',
            ],
            'sitemap' => [
                'auto_generate' => true,
                'include_products' => true,
                'include_pages' => true,
                'change_frequency' => 'daily',
                'path' => 'sitemap.xml',
            ],
            'fraud' => [
                'enabled' => false,
                'provider' => 'BD Courier',
                'endpoint' => 'https://api.bdcourier.com/courier-check',
            ],
            'shipping' => [
                'zones' => [
                    ['name' => 'Inside Dhaka', 'amount' => 80, 'estimated_days' => 2, 'status' => true],
                    ['name' => 'Outside Dhaka', 'amount' => 130, 'estimated_days' => 4, 'status' => true],
                ],
            ],
        ];

        foreach ($defaults as $key => $values) {
            if (! SiteSetting::query()->where('key', $key)->exists()) {
                SiteSetting::put($key, $values);
            }
        }
    }
}
