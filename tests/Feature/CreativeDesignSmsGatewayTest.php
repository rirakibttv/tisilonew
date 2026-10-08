<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\SendSmsMessage;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\SmsGatewayService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreativeDesignSmsGatewayTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        SiteSetting::forget('sms');

        parent::tearDown();
    }

    public function test_creative_design_configuration_is_visible_and_api_key_is_encrypted(): void
    {
        SiteSetting::put('sms', [
            'enabled' => true,
            'provider' => 'creative_design',
        ], [
            'creative_design_api_key' => 'private-creative-design-key',
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $setting = SiteSetting::query()->where('key', 'sms')->firstOrFail();

        $this->assertSame('private-creative-design-key', SiteSetting::secretsFor('sms')['creative_design_api_key']);
        $this->assertArrayNotHasKey('creative_design_api_key', SiteSetting::valuesFor('sms'));
        $this->assertStringNotContainsString('private-creative-design-key', (string) $setting->getRawOriginal('secret_values'));

        $this->actingAs($admin)
            ->get('/admin/api-integrations?section=sms')
            ->assertOk()
            ->assertSee('Creative Design')
            ->assertSee('Creative Design API URL')
            ->assertSee('Creative Design API Key')
            ->assertSee('Send Test SMS');
    }

    public function test_creative_design_request_uses_the_fixed_https_endpoint_and_expected_query_fields(): void
    {
        SiteSetting::put('sms', [
            'enabled' => true,
            'provider' => 'creative_design',
        ], [
            'creative_design_api_key' => 'test-creative-key',
        ]);
        Http::fake([
            SmsGatewayService::CREATIVE_DESIGN_ENDPOINT.'*' => Http::response([
                'status' => 'success',
                'msg' => 'SMS Sent Successfully',
                'msg_id' => '12345',
            ]),
        ]);

        $result = app(SmsGatewayService::class)->sendNow('+8801700000000', 'This is a test message');

        $this->assertSame('creative_design', $result['provider']);
        $this->assertSame('success', $result['status']);
        $this->assertSame('SMS Sent Successfully', $result['message']);
        $this->assertSame('12345', $result['message_id']);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), SmsGatewayService::CREATIVE_DESIGN_ENDPOINT.'?')
                && ($query['api_key'] ?? null) === 'test-creative-key'
                && ($query['number'] ?? null) === '01700000000'
                && ($query['message'] ?? null) === 'This is a test message'
                && ($query['type'] ?? null) === 'text';
        });
    }

    public function test_new_order_queues_customer_confirmation_and_admin_alert(): void
    {
        Queue::fake();
        SiteSetting::put('sms', [
            'enabled' => true,
            'provider' => 'creative_design',
            'order_confirmation' => true,
            'admin_new_order_alert' => true,
        ], [
            'creative_design_api_key' => 'test-creative-key',
            'admin_phone_list' => '01800000000',
        ]);

        $order = Order::query()->create([
            'customer_name' => 'Test Buyer',
            'customer_phone' => '01700000000',
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'payment_method' => 'cod',
            'subtotal_amount' => 1000,
            'shipping_amount' => 80,
            'total_amount' => 1080,
            'currency' => 'BDT',
            'shipping_address' => ['district' => 'Dhaka'],
        ]);

        Queue::assertPushed(SendSmsMessage::class, 2);
        Queue::assertPushed(SendSmsMessage::class, fn (SendSmsMessage $job): bool => $job->number === '01700000000'
            && str_contains($job->message, $order->order_number)
        );
        Queue::assertPushed(SendSmsMessage::class, fn (SendSmsMessage $job): bool => $job->number === '01800000000'
            && str_contains($job->message, 'Test Buyer')
        );
    }
}
