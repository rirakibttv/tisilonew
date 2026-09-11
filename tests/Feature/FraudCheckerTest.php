<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Pages\FraudChecker;
use App\Models\FraudCheckHistory;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class FraudCheckerTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        SiteSetting::forget('fraud');

        parent::tearDown();
    }

    public function test_fraud_checker_is_a_direct_admin_navigation_page_without_overview_submenu(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(['POS System', 'Fraud Checker API', 'Products Info'])
            ->assertSee('/admin/fraud-checker', false)
            ->assertDontSee('module=fraud-checker-api', false);

        $this->actingAs($admin)
            ->get('/admin/fraud-checker')
            ->assertOk()
            ->assertSee('মোবাইল নম্বর দিয়ে customer যাচাই করুন')
            ->assertSee('Whitelisted IP: 162.0.209.109')
            ->assertSee('সাম্প্রতিক Check History');
    }

    public function test_checker_uses_manage_fraud_checker_configuration_and_records_sanitized_history(): void
    {
        $admin = $this->admin();
        $this->enableFraudProvider();

        Http::fake([
            'https://fraud-provider.test/check' => Http::response([
                'status' => 'success',
                'data' => [
                    'summary' => [
                        'total_parcel' => 10,
                        'success_parcel' => 8,
                        'cancelled_parcel' => 2,
                        'success_ratio' => 80,
                    ],
                    'pathao' => [
                        'total_parcel' => 10,
                        'success_parcel' => 8,
                        'cancelled_parcel' => 2,
                    ],
                ],
                'reports' => [[
                    'courier' => 'Pathao',
                    'status' => 'delivered',
                    'order_id' => 'ORDER-1',
                    'private_token' => 'must-not-be-stored',
                ]],
            ]),
        ]);

        $this->actingAs($admin);
        Livewire::test(FraudChecker::class)
            ->set('mobile', '+880 1766-354548')
            ->call('check')
            ->assertHasNoErrors()
            ->assertSet('result.phone', '01766354548')
            ->assertSet('result.summary.success_ratio', 80.0)
            ->assertSet('errorMessage', null);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer provider-secret')
            && $request->data()['phone'] === '01766354548');

        $history = FraudCheckHistory::query()->latest()->firstOrFail();
        $this->assertSame('success', $history->status);
        $this->assertSame('017*****548', $history->mobile_masked);
        $this->assertSame('01766354548', $history->mobile);
        $this->assertSame(10, $history->total_parcel);
        $this->assertSame('safe', $history->risk_level);
        $this->assertArrayNotHasKey('private_token', $history->response_payload['reports'][0]);
        $this->assertStringNotContainsString('01766354548', (string) $history->getRawOriginal('mobile'));
        $this->assertStringNotContainsString('provider-secret', json_encode($history->response_payload));
    }

    public function test_provider_bot_protection_is_reported_clearly_and_saved_as_blocked(): void
    {
        $admin = $this->admin();
        $this->enableFraudProvider();

        Http::fake([
            'https://fraud-provider.test/check' => Http::response(
                '<html>Access denied by Imunify360 bot-protection</html>',
                403,
            ),
        ]);

        $this->actingAs($admin);
        Livewire::test(FraudChecker::class)
            ->set('mobile', '01766354548')
            ->call('check')
            ->assertSet('result', null)
            ->assertSet('errorType', 'blocked')
            ->assertSee('allowlist/whitelist');

        $history = FraudCheckHistory::query()->latest()->firstOrFail();
        $this->assertSame('blocked', $history->status);
        $this->assertStringContainsString('allowlist/whitelist', $history->message);
        $this->assertStringContainsString('162.0.209.109', $history->message);
    }

    private function enableFraudProvider(): void
    {
        SiteSetting::put('fraud', [
            'enabled' => true,
            'provider' => 'BD Courier',
            'endpoint' => 'https://fraud-provider.test/check',
            'http_method' => 'POST',
            'auth_type' => 'bearer',
            'phone_field' => 'phone',
            'timeout_seconds' => 8,
            'risk_threshold' => 70,
            'whitelisted_server_ip' => '162.0.209.109',
        ], [
            'fraud_api_key' => 'provider-secret',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);
    }
}
