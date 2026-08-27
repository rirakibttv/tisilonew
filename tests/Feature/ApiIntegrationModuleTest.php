<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Pages\ApiIntegrationSettings;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ApiIntegrationModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_active_admin_can_open_every_api_integration_module_in_the_requested_order(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(array_values(ApiIntegrationSettings::SECTIONS));

        foreach (ApiIntegrationSettings::SECTIONS as $section => $label) {
            $this->actingAs($admin)
                ->get('/admin/api-integrations?section='.$section)
                ->assertOk()
                ->assertSee($label);
        }
    }

    public function test_api_credentials_are_encrypted_and_not_returned_as_public_values(): void
    {
        SiteSetting::put('facebook_capi', [
            'enabled' => false,
            'pixel_id' => '123456789',
        ], [
            'access_token' => 'private-meta-token',
        ]);

        $setting = SiteSetting::query()->where('key', 'facebook_capi')->firstOrFail();

        $this->assertSame('123456789', SiteSetting::valuesFor('facebook_capi')['pixel_id']);
        $this->assertSame('private-meta-token', SiteSetting::secretsFor('facebook_capi')['access_token']);
        $this->assertStringNotContainsString('private-meta-token', (string) $setting->getRawOriginal('secret_values'));
        $this->assertArrayNotHasKey('access_token', SiteSetting::valuesFor('facebook_capi'));
    }
}
