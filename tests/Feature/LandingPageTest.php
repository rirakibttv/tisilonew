<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\ShippingClass;
use App\Models\ShippingRegion;
use App\Models\ShippingRegionRate;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\DeploymentDataSnapshot;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use DatabaseTransactions;

    private ?int $shippingClassId = null;

    private ?int $shippingRegionId = null;

    protected function tearDown(): void
    {
        SiteSetting::forget('payment');
        parent::tearDown();
    }

    public function test_published_campaign_is_public_and_draft_requires_signed_preview(): void
    {
        $product = $this->product();
        $published = $this->landingPage('published-campaign', 'published');
        $published->products()->attach($product);
        $draft = $this->landingPage('draft-campaign', 'draft');
        $draft->products()->attach($product);

        $this->get(route('store.landing.show', $published))
            ->assertOk()
            ->assertSee($published->header_title)
            ->assertDontSee($published->name)
            ->assertSee($published->headline)
            ->assertSee($product->name)
            ->assertSee('class="storefront-shell grid items-stretch', false)
            ->assertSee('id="order-now" class="storefront-shell', false)
            ->assertSee('এখনই অর্ডার করুন');

        $this->get(route('store.landing.show', $draft))->assertNotFound();

        $previewUrl = URL::temporarySignedRoute(
            'store.landing.preview',
            now()->addHour(),
            ['landingPage' => $draft],
        );
        $this->get($previewUrl)
            ->assertOk()
            ->assertSee('PREVIEW MODE')
            ->assertSee($draft->headline);
    }

    public function test_landing_page_order_preserves_campaign_and_first_party_attribution(): void
    {
        $this->shippingSettings();
        $product = $this->product();
        $landingPage = $this->landingPage('facebook-campaign', 'published');
        $landingPage->products()->attach($product);

        $this->get(route('store.landing.show', $landingPage).'?utm_source=facebook&utm_medium=paid_social&utm_campaign=summer-sale&fbclid=test-click')
            ->assertOk();

        $this->post(route('store.cart.store'), [
            'product_id' => $product->id,
            'landing_page_id' => $landingPage->id,
            'redirect_to' => 'checkout',
            'quantity' => 1,
        ])->assertRedirect(route('store.checkout.index'))
            ->assertSessionHas('store_cart.catalog-'.$product->id.'-base.landing_page_id', $landingPage->id);

        $attribution = rawurlencode(json_encode([
            'source' => 'facebook',
            'medium' => 'paid_social',
            'campaign' => 'summer-sale',
            'click_source' => 'facebook',
        ], JSON_THROW_ON_ERROR));

        $this->withCookie('tisilo_attr', $attribution)
            ->post(route('store.checkout.store'), $this->checkoutData())
            ->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame($landingPage->id, $order->landing_page_id);
        $this->assertSame('facebook', $order->marketing_attribution['source']);
        $this->assertSame('summer-sale', $order->marketing_attribution['campaign']);
    }

    public function test_admin_can_manage_landing_pages_and_snapshot_preserves_campaign(): void
    {
        $product = $this->product();
        $landingPage = $this->landingPage('snapshot-campaign', 'published');
        $landingPage->products()->attach($product);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(['Create Landing Page', 'All Landing Pages']);
        $this->actingAs($admin)->get('/admin/landing-pages')->assertOk()->assertSee($landingPage->name);
        $this->actingAs($admin)->get('/admin/landing-pages/create')
            ->assertOk()
            ->assertSee('Campaign Basics')
            ->assertSee('Header Title')
            ->assertDontSee('Publish At')
            ->assertDontSee('Meta Pixel ID');

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tisilo-landing-'.Str::random(8).'.json';
        try {
            app(DeploymentDataSnapshot::class)->export($path);
            $snapshot = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            $data = collect($snapshot['landing_pages'])->firstWhere('slug', $landingPage->slug);

            $this->assertSame($landingPage->headline, $data['headline']);
            $this->assertSame($landingPage->header_title, $data['header_title']);
            $this->assertArrayNotHasKey('facebook_pixel_id', $data);
            $this->assertSame([$product->slug], $data['product_slugs']);

            $landingPage->update(['headline' => 'Changed headline']);
            $landingPage->products()->detach();
            app(DeploymentDataSnapshot::class)->import($path);

            $this->assertSame('Facebook-ready campaign headline', $landingPage->fresh()->headline);
            $this->assertTrue($landingPage->fresh()->products->contains($product));
        } finally {
            File::delete($path);
        }
    }

    public function test_status_is_the_only_publish_control_for_a_landing_page(): void
    {
        $landingPage = $this->landingPage('status-only-campaign', 'draft');
        $landingPage->products()->attach($this->product());

        $this->assertNull($landingPage->published_at);
        $this->get(route('store.landing.show', $landingPage))->assertNotFound();

        $landingPage->update(['status' => 'published']);

        $this->assertNotNull($landingPage->fresh()->published_at);
        $this->get(route('store.landing.show', $landingPage))->assertOk();

        $landingPage->update(['status' => 'draft']);

        $this->assertNull($landingPage->fresh()->published_at);
        $this->get(route('store.landing.show', $landingPage))->assertNotFound();
    }

    public function test_inline_form_places_one_order_without_changing_the_shopping_cart(): void
    {
        SiteSetting::put('payment', [
            'cod_enabled' => true,
            'bkash_enabled' => true,
            'default_gateway' => 'cod',
            'currency' => 'BDT',
        ]);
        $this->shippingSettings();
        $product = $this->product();
        $product->update(['manage_stock' => true]);
        $campaign = $this->landingPage('inline-checkout', 'published');
        $campaign->products()->attach($product);
        $cart = ['unrelated' => ['product_id' => 123, 'quantity' => 4]];
        $this->withSession(['store_cart' => $cart])
            ->get(route('store.landing.show', $campaign))->assertOk()
            ->assertSee('অফারটি সীমিত সময়ের জন্য')
            ->assertSee('name="customer_name"', false)
            ->assertSee('name="district_search"', false)
            ->assertSee('data-district-options', false)
            ->assertSee('name="thana"', false)
            ->assertSee('name="shipping_region_id"', false)
            ->assertSee('value="cod"', false)
            ->assertSee('value="bkash"', false)
            ->assertSee('bKash')
            ->assertSee(route('store.landing.order', $campaign), false)
            ->assertDontSee('name="terms"', false)
            ->assertDontSee('আমি অর্ডার, ডেলিভারি ও রিটার্ন সংক্রান্ত শর্তাবলিতে সম্মত।')
            ->assertDontSee('name="redirect_to"', false);
        $token = session('landing_checkout.'.$campaign->id.'.token');
        $payload = [...$this->checkoutData(), 'checkout_token' => $token, 'product_id' => $product->id, 'quantity' => 2, 'price' => 1, 'shipping_amount' => 0];
        $attribution = rawurlencode(json_encode(['source' => 'facebook', 'campaign' => 'inline-ad']));
        $before = Order::count();

        $response = $this->withCookie('tisilo_attr', $attribution)->post(route('store.landing.order', $campaign), $payload);
        $response->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('store_cart', $cart);
        $order = Order::latest('id')->first();
        $this->assertSame($before + 1, Order::count());
        $this->assertSame($campaign->id, $order->landing_page_id);
        $this->assertSame('1998.00', $order->subtotal_amount);
        $this->assertSame('2078.00', $order->total_amount);
        $this->assertSame('Dhaka', $order->shipping_address['district']);
        $this->assertSame('Mirpur', $order->shipping_address['upazila']);
        $this->assertSame(18, $product->fresh()->stock_quantity);
        $this->assertSame('facebook', $order->marketing_attribution['source']);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee($order->order_number);

        $this->post(route('store.landing.order', $campaign), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($before + 1, Order::count());
        $this->assertSame(18, $product->fresh()->stock_quantity);
        // A lost session completion marker must not recreate the already-committed order.
        $this->withSession(['landing_checkout' => [$campaign->id => ['token' => $token]]])
            ->post(route('store.landing.order', $campaign), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($before + 1, Order::count());
        $this->assertSame(18, $product->fresh()->stock_quantity);
    }

    public function test_inline_quote_uses_variation_price_and_quantity_based_shipping(): void
    {
        $this->shippingSettings();
        ShippingRegionRate::where('shipping_region_id', $this->shippingRegionId)->update(['additional_item_charge' => 20]);
        $product = $this->product();
        $product->update(['product_type' => 'variable']);
        $variation = ProductVariation::create([
            'product_id' => $product->id, 'sku' => 'INLINE-'.Str::random(8),
            'regular_price' => 800, 'sale_price' => 700, 'stock_quantity' => 4,
            'stock_status' => 'in_stock', 'status' => true, 'image' => 'products/variations/blue.jpg',
        ]);
        $campaign = $this->landingPage('variation-quote', 'published');
        $campaign->products()->attach($product);
        $selection = ['product_id' => $product->id, 'product_variation_id' => $variation->id, 'quantity' => 2, 'price' => 1];
        $this->get(route('store.landing.show', $campaign))->assertOk()
            ->assertSee('data-variation-options class="mt-2 grid grid-cols-1', false)
            ->assertSee('data-variation-option="'.$variation->id.'"', false)
            ->assertSee(asset('storage/products/variations/blue.jpg'), false);
        $this->postJson(route('store.landing.quote', $campaign), $selection)->assertOk()
            ->assertJsonPath('subtotal', 1400)
            ->assertJsonPath('regions.0.amount', 100);

        $this->get(route('store.landing.show', $campaign))->assertOk();
        $this->post(route('store.landing.order', $campaign), [
            ...$this->checkoutData(), ...$selection, 'checkout_token' => session('landing_checkout.'.$campaign->id.'.token'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $order = Order::latest('id')->first();
        $this->assertSame('1500.00', $order->total_amount);
        $this->assertSame($variation->id, $order->items()->first()->product_variation_id);
        $this->assertSame(2, $variation->fresh()->stock_quantity);
    }

    public function test_inline_checkout_rejects_products_outside_campaign_and_wrong_variations(): void
    {
        $this->shippingSettings();
        $product = $this->product();
        $outside = $this->product();
        $campaign = $this->landingPage('scoped-products', 'published');
        $campaign->products()->attach($product);
        $this->postJson(route('store.landing.quote', $campaign), ['product_id' => $outside->id, 'quantity' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $product->update(['product_type' => 'variable']);
        $wrongVariation = ProductVariation::create([
            'product_id' => $outside->id, 'regular_price' => 500, 'stock_quantity' => 5, 'status' => true,
        ]);
        $this->postJson(route('store.landing.quote', $campaign), [
            'product_id' => $product->id, 'product_variation_id' => $wrongVariation->id, 'quantity' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('product_variation_id');
    }

    public function test_invalid_inline_form_stays_on_campaign_and_preserves_customer_input(): void
    {
        $this->shippingSettings();
        $product = $this->product();
        $campaign = $this->landingPage('inline-errors', 'published');
        $campaign->products()->attach($product);
        $this->get(route('store.landing.show', $campaign))->assertOk();
        $payload = [...$this->checkoutData(), 'product_id' => $product->id, 'quantity' => 1,
            'checkout_token' => session('landing_checkout.'.$campaign->id.'.token'), 'customer_phone' => 'invalid'];
        $before = Order::count();
        $this->post(route('store.landing.order', $campaign), $payload)
            ->assertRedirect(route('store.landing.show', $campaign).'#order-now')
            ->assertSessionHasErrors('customer_phone')
            ->assertSessionHasInput('customer_name', 'Landing Customer');
        $this->assertSame($before, Order::count());
        $this->get(route('store.landing.show', $campaign))->assertOk()->assertSee('সঠিক মোবাইল নম্বর লিখুন।');
    }

    public function test_inline_checkout_blocks_unavailable_stock_missing_shipping_and_invalid_tokens(): void
    {
        $this->shippingSettings();
        $product = $this->product();
        $product->update(['manage_stock' => true, 'stock_quantity' => 1]);
        $campaign = $this->landingPage('inline-safety', 'published');
        $campaign->products()->attach($product);
        $this->get(route('store.landing.show', $campaign))->assertOk();
        $payload = [...$this->checkoutData(), 'product_id' => $product->id, 'quantity' => 2,
            'checkout_token' => session('landing_checkout.'.$campaign->id.'.token')];
        $before = Order::count();
        $this->post(route('store.landing.order', $campaign), $payload)->assertSessionHasErrors('quantity');
        $payload['quantity'] = 1;
        $this->post(route('store.landing.order', $campaign), [...$payload, 'checkout_token' => 'invalid'])
            ->assertSessionHasErrors('checkout_token');
        ShippingRegionRate::where('shipping_region_id', $this->shippingRegionId)->update(['is_active' => false]);
        $this->post(route('store.landing.order', $campaign), $payload)->assertSessionHasErrors('shipping_region_id');
        $this->assertSame($before, Order::count());
        $this->assertSame(1, $product->fresh()->stock_quantity);
        $product->update(['shipping_class_id' => null]);
        $this->get(route('store.landing.show', $campaign))->assertOk()->assertSee('Shipping Class');
    }

    public function test_preview_cannot_place_or_quote_a_draft_order(): void
    {
        $product = $this->product();
        $draft = $this->landingPage('disabled-preview', 'draft');
        $draft->products()->attach($product);
        $this->postJson(route('store.landing.quote', $draft), ['product_id' => $product->id, 'quantity' => 1])->assertNotFound();
        $this->post(route('store.landing.order', $draft), [])->assertNotFound();
        $this->get(URL::temporarySignedRoute('store.landing.preview', now()->addHour(), ['landingPage' => $draft]))
            ->assertOk()->assertSee('প্রিভিউতে অর্ডার বন্ধ আছে।');
    }

    private function landingPage(string $slug, string $status): LandingPage
    {
        return LandingPage::query()->create([
            'name' => Str::headline($slug),
            'header_title' => 'Header title for '.$slug,
            'slug' => $slug.'-'.Str::lower(Str::random(6)),
            'status' => $status,
            'hero_badge' => 'বিশেষ অফার',
            'headline' => 'Facebook-ready campaign headline',
            'subheadline' => 'A focused mobile-first campaign page.',
            'cta_text' => 'এখনই অর্ডার করুন',
            'theme_color' => '#f97316',
            'benefits' => [['title' => 'Fast Delivery', 'description' => 'Nationwide delivery']],
            'published_at' => $status === 'published' ? now() : null,
        ]);
    }

    private function product(): Product
    {
        return Product::query()->create([
            'name' => 'Landing Product '.Str::random(6),
            'slug' => 'landing-product-'.Str::lower(Str::random(8)),
            'product_type' => 'simple',
            'regular_price' => 1200,
            'sale_price' => 999,
            'stock_quantity' => 20,
            'stock_status' => 'in_stock',
            'status' => 'published',
            'shipping_class_id' => $this->shippingClassId ?? ShippingClass::query()->where('code', 'standard')->value('id'),
        ]);
    }

    private function shippingSettings(): void
    {
        $class = ShippingClass::query()->create([
            'name' => 'Landing Class',
            'code' => 'landing-class-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        $region = ShippingRegion::query()->create([
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'upazila' => 'Mirpur',
            'location_key' => 'landing-mirpur-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
        ShippingRegionRate::query()->create([
            'shipping_region_id' => $region->id,
            'shipping_class_id' => $class->id,
            'base_charge' => 80,
            'estimated_min_days' => 1,
            'estimated_max_days' => 2,
            'is_active' => true,
        ]);
        $this->shippingClassId = $class->id;
        $this->shippingRegionId = $region->id;
    }

    /** @return array<string, mixed> */
    private function checkoutData(): array
    {
        return [
            'customer_name' => 'Landing Customer',
            'customer_phone' => '01700000000',
            'customer_email' => 'landing@example.com',
            'address_line' => 'House 10, Road 5',
            'district_search' => 'Dhaka',
            'thana' => 'Mirpur',
            'shipping_region_id' => $this->shippingRegionId,
            'payment_method' => 'cod',
        ];
    }
}
