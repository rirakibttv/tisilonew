<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Product;
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

    protected function tearDown(): void
    {
        SiteSetting::forget('shipping');
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
            ->assertSee($published->headline)
            ->assertSee($product->name)
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
        $this->actingAs($admin)->get('/admin/landing-pages/create')->assertOk()->assertSee('Campaign Basics');

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tisilo-landing-'.Str::random(8).'.json';
        try {
            app(DeploymentDataSnapshot::class)->export($path);
            $snapshot = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            $data = collect($snapshot['landing_pages'])->firstWhere('slug', $landingPage->slug);

            $this->assertSame($landingPage->headline, $data['headline']);
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

    private function landingPage(string $slug, string $status): LandingPage
    {
        return LandingPage::query()->create([
            'name' => Str::headline($slug),
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
        ]);
    }

    private function shippingSettings(): void
    {
        SiteSetting::put('shipping', ['zones' => [[
            'name' => 'Inside Dhaka',
            'amount' => 80,
            'estimated_days' => 2,
            'status' => true,
        ]]]);
    }

    /** @return array<string, mixed> */
    private function checkoutData(): array
    {
        return [
            'customer_name' => 'Landing Customer',
            'customer_phone' => '01700000000',
            'customer_email' => 'landing@example.com',
            'address_line' => 'House 10, Road 5',
            'district' => 'Dhaka',
            'shipping_zone' => 'Inside Dhaka',
            'payment_method' => 'cod',
            'terms' => '1',
        ];
    }
}
