<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendMetaConversionEvent;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\MetaConversionsApiService;
use App\Services\VisitorAnalyticsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class MetaConversionsApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_page_view_uses_the_same_event_id_for_the_conversions_api_job(): void
    {
        $this->enableMeta(['PageView']);
        Queue::fake();
        $request = Request::create(
            'https://www.tisilo.com/shop?utm_source=facebook',
            'GET',
            [],
            ['_fbp' => 'fb.1.1234567890.browser'],
            [],
            ['REMOTE_ADDR' => '203.0.113.10', 'HTTP_USER_AGENT' => 'Tisilo Test Browser'],
        );

        app(VisitorAnalyticsService::class)->record($request, [
            'visitor_id' => (string) Str::uuid(),
            'event_type' => 'page_view',
            'event_id' => 'page-view-browser-and-server-1',
            'path' => '/shop',
        ]);

        Queue::assertPushed(SendMetaConversionEvent::class, fn (SendMetaConversionEvent $job): bool => $job->eventName === 'PageView'
            && $job->event['event_id'] === 'page-view-browser-and-server-1'
            && $job->event['event_source_url'] === 'https://www.tisilo.com/shop?utm_source=facebook'
            && $job->event['user_data']['fbp'] === 'fb.1.1234567890.browser'
        );
    }

    public function test_storefront_loads_the_meta_pixel_without_exposing_the_access_token(): void
    {
        $this->enableMeta(['PageView', 'ViewContent']);

        $this->get('/')
            ->assertOk()
            ->assertSee('var metaPixelEnabled = true;', false)
            ->assertSee('var metaPixelId = "123456789";', false)
            ->assertSee('https://connect.facebook.net/en_US/fbevents.js', false)
            ->assertSee('{ eventID: eventId }', false)
            ->assertDontSee('private-meta-token');
    }

    public function test_product_event_includes_the_tisilo_category_hierarchy(): void
    {
        $this->enableMeta(['ViewContent']);
        Queue::fake();
        $parent = Category::query()->create([
            'name' => 'Home & Kitchen',
            'slug' => 'meta-event-home-'.Str::lower(Str::random(8)),
            'status' => true,
        ]);
        $child = Category::query()->create([
            'parent_id' => $parent->id,
            'name' => 'Bed Sheet',
            'slug' => 'meta-event-bed-sheet-'.Str::lower(Str::random(8)),
            'status' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Tracked Category Product',
            'slug' => 'tracked-category-product-'.Str::lower(Str::random(8)),
            'category_id' => $child->id,
            'regular_price' => 1200,
            'status' => 'published',
        ]);
        $request = Request::create(route('store.products.show', $product), 'GET');

        app(VisitorAnalyticsService::class)->record($request, [
            'visitor_id' => (string) Str::uuid(),
            'event_type' => 'product_view',
            'event_id' => 'category-view-1',
            'path' => '/products/'.$product->slug,
            'product_id' => $product->id,
        ]);

        Queue::assertPushed(SendMetaConversionEvent::class, fn (SendMetaConversionEvent $job): bool => $job->eventName === 'ViewContent'
            && $job->event['custom_data']['content_ids'] === [(string) $product->id]
            && $job->event['custom_data']['content_category'] === 'Home & Kitchen › Bed Sheet'
        );
    }

    public function test_purchase_is_queued_with_hashed_customer_and_complete_order_data(): void
    {
        $this->enableMeta(['Purchase']);
        Queue::fake();
        $order = $this->order();
        $order->items()->create([
            'product_id' => null,
            'product_name' => 'Tracked Product',
            'sku' => 'TRACK-1',
            'quantity' => 2,
            'unit_price' => 500,
            'total_amount' => 1000,
        ]);
        $request = Request::create('https://www.tisilo.com/checkout/success', 'GET', [], [
            '_fbp' => 'fb.1.1234567890.browser',
            '_fbc' => 'fb.1.1234567890.click',
        ], [], ['REMOTE_ADDR' => '203.0.113.10', 'HTTP_USER_AGENT' => 'Tisilo Test Browser']);

        app(VisitorAnalyticsService::class)->record($request, [
            'visitor_id' => (string) Str::uuid(),
            'event_type' => 'purchase',
            'event_id' => 'purchase-'.$order->order_number,
            'order_id' => $order->id,
            'value' => 1080,
            'metadata' => ['currency' => 'BDT'],
        ]);

        Queue::assertPushed(SendMetaConversionEvent::class, function (SendMetaConversionEvent $job) use ($order): bool {
            $encoded = json_encode($job->event, JSON_THROW_ON_ERROR);

            return $job->eventName === 'Purchase'
                && $job->event['event_id'] === 'purchase-'.$order->order_number
                && $job->event['custom_data']['order_id'] === $order->order_number
                && $job->event['custom_data']['contents'][0]['quantity'] === 2
                && $job->event['user_data']['ph'] === [hash('sha256', '8801700000000')]
                && $job->event['user_data']['fbc'] === 'fb.1.1234567890.click'
                && ! str_contains($encoded, '01700000000')
                && ! str_contains($encoded, 'buyer@example.com');
        });
    }

    public function test_all_order_lifecycle_changes_are_queued_from_the_order_observer(): void
    {
        $events = [
            OrderStatus::Confirmed->value => 'OrderConfirmed',
            OrderStatus::Processing->value => 'OrderProcessing',
            OrderStatus::Shipped->value => 'OrderShipped',
            OrderStatus::Delivered->value => 'OrderDelivered',
            OrderStatus::Cancelled->value => 'OrderCancelled',
            OrderStatus::Refunded->value => 'OrderRefunded',
        ];
        $this->enableMeta(array_values($events));
        Queue::fake();
        $order = $this->order();

        foreach ($events as $status => $eventName) {
            $order->update(['status' => $status]);
            Queue::assertPushed(SendMetaConversionEvent::class, fn (SendMetaConversionEvent $job): bool => $job->eventName === $eventName
                && $job->event['custom_data']['status'] === $status
                && $job->event['custom_data']['order_id'] === $order->order_number
            );
        }
    }

    public function test_meta_service_posts_to_the_configured_dataset_without_exposing_token_in_payload(): void
    {
        $this->enableMeta(['Purchase']);
        Http::fake([
            'https://graph.facebook.com/v23.0/123456789/events' => Http::response(['events_received' => 1], 200),
        ]);

        app(MetaConversionsApiService::class)->sendNow('Purchase', [
            'event_name' => 'Purchase',
            'event_time' => now()->getTimestamp(),
            'event_id' => 'purchase-test-1',
            'action_source' => 'website',
            'user_data' => ['ph' => [hash('sha256', '8801700000000')]],
            'custom_data' => ['currency' => 'BDT', 'value' => 1000],
        ]);

        Http::assertSent(function (HttpRequest $request): bool {
            return $request->url() === 'https://graph.facebook.com/v23.0/123456789/events'
                && $request->hasHeader('Authorization', 'Bearer private-meta-token')
                && $request['data'][0]['event_id'] === 'purchase-test-1'
                && ! array_key_exists('access_token', $request->data());
        });
    }

    /** @param array<int, string> $events */
    private function enableMeta(array $events): void
    {
        SiteSetting::put('facebook_capi', [
            'enabled' => true,
            'pixel_id' => '123456789',
            'api_version' => 'v23.0',
            'events' => $events,
        ], ['access_token' => 'private-meta-token']);
    }

    private function order(): Order
    {
        return Order::query()->create([
            'customer_name' => 'Test Buyer',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '01700000000',
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'payment_method' => 'cod',
            'subtotal_amount' => 1000,
            'shipping_amount' => 80,
            'total_amount' => 1080,
            'currency' => 'BDT',
            'shipping_address' => ['division' => 'Dhaka', 'district' => 'Dhaka', 'upazila' => 'Mirpur'],
            'marketing_attribution' => ['fbc' => 'fb.1.saved.click'],
        ]);
    }
}
