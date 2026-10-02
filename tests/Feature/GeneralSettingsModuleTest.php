<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Pages\GeneralSettings;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeneralSettingsModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_active_admin_can_open_every_general_settings_module(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        foreach (GeneralSettings::SECTIONS as $section => $label) {
            $this->actingAs($admin)
                ->get('/admin/general-settings?section='.$section)
                ->assertOk()
                ->assertSee($label);
        }
    }

    public function test_legacy_shipping_zone_settings_are_not_exposed_in_general_settings(): void
    {
        $this->assertArrayNotHasKey('shipping', GeneralSettings::SECTIONS);
    }

    public function test_create_page_and_contact_are_owned_by_pages_and_marketing_navigation(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk()
            ->assertSee('/admin/general-settings?section=contact', false)
            ->assertSee('/admin/general-settings?section=pages&amp;page=create', false)
            ->assertSeeInOrder([
                'Marketing',
                '/admin/general-settings?section=contact',
                'Pages',
                '/admin/general-settings?section=pages&amp;page=create',
                'General Settings',
            ], false);

        $this->actingAs($admin)
            ->get('/admin/general-settings?section=pages&page=create')
            ->assertOk()
            ->assertSee('Create Page');

        $this->actingAs($admin)
            ->get('/admin/general-settings?section=contact')
            ->assertOk()
            ->assertSee('Contact Information');
    }

    public function test_fraud_configuration_only_exists_in_api_integration_master_settings(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $this->assertArrayNotHasKey('fraud', GeneralSettings::SECTIONS);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Fraud API Settings')
            ->assertSee('Manage Fraud Checker');

        $this->actingAs($admin)
            ->get('/admin/general-settings?section=fraud')
            ->assertNotFound();

        $this->actingAs($admin)
            ->get('/admin/api-integrations?section=fraud')
            ->assertOk()
            ->assertSee('Manage Fraud Checker')
            ->assertSee('Fraud Checker Provider')
            ->assertSee('Provider HTTPS Endpoint')
            ->assertSee('Minimum Safe Delivery Rate');
    }

    public function test_active_content_page_is_available_on_the_storefront(): void
    {
        SiteSetting::put('pages', [
            'pages' => [[
                'name' => 'About Us',
                'title' => 'About Tisilo',
                'slug' => 'about-tisilo',
                'description' => '<p>Enterprise marketplace content.</p>',
                'status' => true,
            ]],
        ]);

        $this->get('/page/about-tisilo')
            ->assertOk()
            ->assertSee('<title>About Tisilo — ', false)
            ->assertSee('<meta name="description" content="Enterprise marketplace content.">', false)
            ->assertSee('About Tisilo')
            ->assertSee('Enterprise marketplace content.');
    }

    public function test_inactive_content_page_returns_not_found(): void
    {
        SiteSetting::put('pages', [
            'pages' => [[
                'name' => 'Draft',
                'title' => 'Draft Page',
                'slug' => 'draft-page',
                'description' => '<p>Hidden</p>',
                'status' => false,
            ]],
        ]);

        $this->get('/page/draft-page')->assertNotFound();
    }

    public function test_core_content_pages_have_clean_urls_and_homepage_links(): void
    {
        SiteSetting::put('pages', [
            'pages' => collect([
                ['name' => 'Contact Us', 'slug' => 'contact-us'],
                ['name' => 'Blog', 'slug' => 'blog'],
                ['name' => 'About Us', 'slug' => 'about-us'],
            ])->map(fn (array $page): array => [
                ...$page,
                'title' => $page['name'],
                'description' => '<p>'.$page['name'].' content</p>',
                'status' => true,
            ])->all(),
        ]);

        $this->get('/contact-us')->assertOk()->assertSee('Contact Us content');
        $this->get('/blog')->assertOk()->assertSee('Blog content');
        $this->get('/about-us')->assertOk()->assertSee('About Us content');

        $this->get('/')
            ->assertOk()
            ->assertSee(route('store.contact'), false)
            ->assertSee(route('store.blog'), false)
            ->assertSee(route('store.about'), false);
    }

    public function test_storefront_uses_saved_logo_favicon_and_head_settings(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/icons/store-favicon.png', 'favicon');

        SiteSetting::put('general', [
            'site_name' => 'Tisilo Enterprise',
            'top_headline' => 'Enterprise Marketplace Headline',
            'footer_about_text' => '<p>Automatic enterprise marketplace description.</p>',
            'primary_color' => '#123456',
            'dark_logo' => 'settings/logos/store-logo.png',
            'favicon' => 'settings/icons/store-favicon.png',
            'og_banner' => 'settings/social/store-banner.png',
        ]);
        SiteSetting::put('seo', [
            'meta_title' => 'Tisilo SEO Title',
            'meta_description' => 'Tisilo SEO Description',
            'meta_tags' => 'tisilo, marketplace',
            'search_console_verification' => 'google-site-verification=verification-token',
        ]);

        $response = $this->withSession(['storefront_locale' => 'bn'])->get('/');

        $response->assertOk()
            ->assertSee('<title>Tisilo Enterprise — আপনার প্রয়োজনের সবকিছু, এক সুপারমার্কেটে</title>', false)
            ->assertSee('<meta name="description" content="Automatic enterprise marketplace description.">', false)
            ->assertDontSee('<title>Tisilo SEO Title</title>', false)
            ->assertDontSee('content="Tisilo SEO Description"', false)
            ->assertSee('content="verification-token"', false)
            ->assertSee('Enterprise Marketplace Headline')
            ->assertSee('storage/settings/logos/store-logo.png?v=', false)
            ->assertSee('storage/settings/icons/store-favicon.png?v=', false)
            ->assertSee('storage/settings/social/store-banner.png?v=', false);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('storage/settings/icons/store-favicon.png?v=', false);

        $this->get('/seller/login')
            ->assertOk()
            ->assertSee('storage/settings/icons/store-favicon.png?v=', false);
    }

    public function test_admin_panel_uses_bundled_favicon_when_saved_file_is_unavailable(): void
    {
        Storage::fake('public');
        SiteSetting::put('general', [
            'favicon' => 'settings/icons/missing-favicon.png',
        ]);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('favicon.svg', false);
    }
}
