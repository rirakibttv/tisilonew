<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Pages\GeneralSettings;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
}
