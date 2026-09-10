<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class GeneralSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPages = [
            [
                'name' => 'Contact Us',
                'title' => 'আমাদের সঙ্গে যোগাযোগ করুন',
                'slug' => 'contact-us',
                'description' => '<p>Tisilo-এর পণ্য, অর্ডার, ডেলিভারি, রিটার্ন অথবা বিক্রেতা-সংক্রান্ত যেকোনো সহায়তার জন্য আমাদের সঙ্গে যোগাযোগ করুন।</p><h2>গ্রাহক সহায়তা</h2><p>আমাদের সাপোর্ট টিম আপনার প্রশ্নের দ্রুত ও নির্ভরযোগ্য উত্তর দিতে প্রস্তুত। ফোন, WhatsApp অথবা ইমেইলের মাধ্যমে যোগাযোগ করতে পারেন।</p><h2>ব্যবসায়িক যোগাযোগ</h2><p>ভেন্ডর নিবন্ধন, ব্র্যান্ড পার্টনারশিপ ও কর্পোরেট অর্ডারের জন্য যোগাযোগের সময় আপনার প্রতিষ্ঠান ও প্রয়োজনের সংক্ষিপ্ত তথ্য দিন।</p>',
                'status' => true,
            ],
            [
                'name' => 'Blog',
                'title' => 'Tisilo Blog',
                'slug' => 'blog',
                'description' => '<p>স্মার্ট অনলাইন কেনাকাটা, নতুন পণ্য, অফার, লাইফস্টাইল ও মার্কেটপ্লেসের প্রয়োজনীয় আপডেট জানতে Tisilo Blog অনুসরণ করুন।</p><h2>সর্বশেষ গল্প ও গাইড</h2><p>আমাদের সম্পাদকীয় টিম নিয়মিত পণ্য বাছাইয়ের গাইড, ব্যবহারিক পরামর্শ এবং বিশেষ ক্যাম্পেইনের খবর প্রকাশ করবে। নতুন লেখা শিগগিরই আসছে।</p>',
                'status' => true,
            ],
            [
                'name' => 'About Us',
                'title' => 'Tisilo সম্পর্কে',
                'slug' => 'about-us',
                'description' => '<p>Tisilo বাংলাদেশের গ্রাহক ও বিশ্বস্ত বিক্রেতাদের এক প্ল্যাটফর্মে যুক্ত করা একটি আধুনিক মাল্টি-ভেন্ডর মার্কেটপ্লেস।</p><h2>আমাদের লক্ষ্য</h2><p>যাচাইকৃত বিক্রেতা, মানসম্মত পণ্য, স্বচ্ছ মূল্য এবং নির্ভরযোগ্য ডেলিভারির মাধ্যমে অনলাইন কেনাকাটাকে সহজ ও নিরাপদ করা।</p><h2>কেন Tisilo</h2><p>আমরা গ্রাহকসেবা, নিরাপদ পেমেন্ট, সহজ রিটার্ন এবং সারা দেশে কার্যকর ডেলিভারিকে অগ্রাধিকার দিই।</p>',
                'status' => true,
            ],
        ];

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
                'meta_tags' => 'tisilo, marketplace, ecommerce, bangladesh',
            ],
            'social' => ['links' => []],
            'contact' => ['status' => true],
            'pages' => ['pages' => $defaultPages],
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
                'http_method' => 'POST',
                'auth_type' => 'bearer',
                'phone_field' => 'phone',
                'api_key_header' => 'X-API-Key',
                'api_key_parameter' => 'api_key',
                'timeout_seconds' => 8,
                'risk_threshold' => 70,
            ],
            'payment' => ['cod_enabled' => true, 'default_gateway' => 'cod', 'currency' => 'BDT'],
            'sms' => ['enabled' => false, 'provider' => 'bulksmsbd', 'order_confirmation' => true, 'password_reset' => true, 'admin_new_order_alert' => true],
            'courier' => ['steadfast_enabled' => false, 'pathao_enabled' => false, 'redx_enabled' => false],
            'facebook_capi' => ['enabled' => false, 'api_version' => 'v23.0', 'events' => [
                'PageView', 'ViewContent', 'AddToCart', 'InitiateCheckout', 'AddPaymentInfo', 'Purchase',
                'OrderConfirmed', 'OrderProcessing', 'OrderShipped', 'OrderDelivered', 'OrderCancelled', 'OrderRefunded',
            ]],
            'facebook_auto_post' => ['enabled' => false, 'post_on_product_publish' => false, 'api_version' => 'v23.0'],
            'google_analytics' => ['enabled' => false, 'enhanced_ecommerce' => true, 'anonymize_ip' => true],
            'google_tag_manager' => ['enabled' => false],
        ];

        foreach ($defaults as $key => $values) {
            if ($key === 'pages') {
                $current = SiteSetting::valuesFor('pages');
                $pages = collect($current['pages'] ?? []);

                foreach ($defaultPages as $page) {
                    if (! $pages->contains(fn (array $existing): bool => ($existing['slug'] ?? null) === $page['slug'])) {
                        $pages->push($page);
                    }
                }

                SiteSetting::put('pages', [
                    ...$current,
                    'pages' => $pages->values()->all(),
                ]);

                continue;
            }

            if (! SiteSetting::query()->where('key', $key)->exists()) {
                SiteSetting::put($key, $values);
            }
        }
    }
}
