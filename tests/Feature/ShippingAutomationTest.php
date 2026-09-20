<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Product;
use App\Models\ShippingClass;
use App\Models\ShippingPartner;
use App\Models\ShippingRegion;
use App\Models\ShippingRegionRate;
use App\Models\User;
use App\Services\DeploymentDataSnapshot;
use App\Services\ShippingRateService;
use App\Services\ShippingRegionDuplicator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShippingAutomationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_has_exactly_the_three_shipping_management_modules(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSeeInOrder(['Shipping Class', 'Shipping Region', 'Shipping Partner']);
        $this->actingAs($admin)->get('/admin/shipping-classes')->assertOk()->assertSee('Shipping Classes');
        $this->actingAs($admin)->get('/admin/shipping-regions')->assertOk()
            ->assertSee('Shipping Regions')
            ->assertSeeInOrder(['SL', 'Division', 'District']);
        $this->actingAs($admin)->get('/admin/shipping-partners')->assertOk()->assertSee('Shipping Partners');
        $this->actingAs($admin)->get('/admin/shipping-regions/create')->assertOk()->assertDontSee('Minimum Days');
        $this->actingAs($admin)->get('/admin/shipping-partners/create')->assertOk()
            ->assertSee('Minimum Days')
            ->assertSee('Maximum Days');
        $this->actingAs($admin)->get('/admin/products/create')->assertOk()->assertSee('Shipping Class');
    }

    public function test_region_and_product_class_automatically_calculate_shipping_charge(): void
    {
        $class = ShippingClass::query()->create([
            'name' => 'Fragile', 'code' => 'fragile-'.Str::lower(Str::random(6)), 'is_active' => true,
        ]);
        $partner = ShippingPartner::query()->create([
            'name' => 'Fast Courier', 'code' => 'fast-'.Str::lower(Str::random(6)), 'is_active' => true,
            'estimated_min_days' => 2, 'estimated_max_days' => 4,
        ]);
        $region = ShippingRegion::query()->create([
            'division' => 'Chattogram', 'district' => 'Cumilla', 'upazila' => 'Daudkandi',
            'location_key' => 'daudkandi-'.Str::lower(Str::random(6)), 'is_active' => true,
        ]);
        ShippingRegionRate::query()->create([
            'shipping_region_id' => $region->id,
            'shipping_class_id' => $class->id,
            'shipping_partner_id' => $partner->id,
            'base_charge' => 100,
            'additional_item_charge' => 25,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Fragile Product',
            'slug' => 'fragile-product-'.Str::lower(Str::random(6)),
            'product_type' => 'simple',
            'regular_price' => 500,
            'shipping_class_id' => $class->id,
            'status' => 'published',
        ]);

        $quotes = app(ShippingRateService::class)->quotesForCart(collect([
            'catalog-'.$product->id.'-base' => [
                'product_id' => $product->id, 'name' => $product->name, 'quantity' => 2, 'price' => 500,
            ],
        ]));

        $quote = $quotes->get($region->id);
        $this->assertSame(125.0, $quote['amount']);
        $this->assertSame('Daudkandi', $quote['upazila']);
        $this->assertSame($partner->id, $quote['partner_id']);
        $this->assertSame('Fragile', $quote['breakdown'][0]['shipping_class']);
        $this->assertSame(2, $quote['estimated_min_days']);
        $this->assertSame(4, $quote['estimated_max_days']);

        $snapshot = app(DeploymentDataSnapshot::class)->build();
        $snapshotRegion = collect($snapshot['shipping_regions'])->firstWhere('location_key', $region->location_key);
        $snapshotPartner = collect($snapshot['shipping_partners'])->firstWhere('code', $partner->code);
        $snapshotProduct = collect($snapshot['products'])->firstWhere('slug', $product->slug);
        $this->assertSame($class->code, $snapshotProduct['shipping_class_code']);
        $this->assertSame($class->code, $snapshotRegion['rates'][0]['shipping_class_code']);
        $this->assertSame($partner->code, $snapshotRegion['rates'][0]['shipping_partner_code']);
        $this->assertArrayNotHasKey('estimated_min_days', $snapshotRegion['rates'][0]);
        $this->assertArrayNotHasKey('estimated_max_days', $snapshotRegion['rates'][0]);
        $this->assertSame(2, $snapshotPartner['estimated_min_days']);
        $this->assertSame(4, $snapshotPartner['estimated_max_days']);
    }

    public function test_shipping_region_is_duplicated_with_all_rates_as_inactive(): void
    {
        $partner = ShippingPartner::query()->create([
            'name' => 'Duplicate Test Courier',
            'code' => 'duplicate-test-courier-'.Str::lower(Str::random(6)),
            'estimated_min_days' => 2,
            'estimated_max_days' => 5,
            'is_active' => true,
        ]);
        $classes = collect(['Small Parcel', 'Large Parcel'])->map(fn (string $name): ShippingClass => ShippingClass::query()->create([
            'name' => $name,
            'code' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]));
        $region = ShippingRegion::query()->create([
            'division' => 'Khulna',
            'district' => 'Bagerhat',
            'upazila' => 'Bagerhat Sadar',
            'postal_code' => '9300',
            'is_active' => true,
            'sort_order' => 12,
        ]);

        foreach ($classes as $index => $class) {
            ShippingRegionRate::query()->create([
                'shipping_region_id' => $region->id,
                'shipping_class_id' => $class->id,
                'shipping_partner_id' => $partner->id,
                'base_charge' => 100 + ($index * 50),
                'additional_item_charge' => 20 + ($index * 10),
                'is_active' => $index === 0,
            ]);
        }

        // The Filament list loads this aggregate before invoking the row action.
        $region = ShippingRegion::query()->withCount('rates')->findOrFail($region->id);
        $this->assertSame(2, $region->rates_count);

        $duplicate = app(ShippingRegionDuplicator::class)->duplicate($region);

        $this->assertNotSame($region->id, $duplicate->id);
        $this->assertSame('Bagerhat Sadar (Copy)', $duplicate->upazila);
        $this->assertSame('Khulna', $duplicate->division);
        $this->assertSame('Bagerhat', $duplicate->district);
        $this->assertSame('9300', $duplicate->postal_code);
        $this->assertSame(12, $duplicate->sort_order);
        $this->assertFalse($duplicate->is_active);
        $this->assertNotSame($region->location_key, $duplicate->location_key);
        $this->assertCount(2, $duplicate->rates);

        foreach ($region->rates()->orderBy('shipping_class_id')->get() as $index => $originalRate) {
            $duplicateRate = $duplicate->rates->sortBy('shipping_class_id')->values()[$index];

            $this->assertNotSame($originalRate->id, $duplicateRate->id);
            $this->assertSame($originalRate->shipping_class_id, $duplicateRate->shipping_class_id);
            $this->assertSame($originalRate->shipping_partner_id, $duplicateRate->shipping_partner_id);
            $this->assertSame($originalRate->base_charge, $duplicateRate->base_charge);
            $this->assertSame($originalRate->additional_item_charge, $duplicateRate->additional_item_charge);
            $this->assertSame($originalRate->is_active, $duplicateRate->is_active);
        }

        $this->assertSame(2, $duplicate->rates->first()->partner->estimated_min_days);
        $this->assertSame(5, $duplicate->rates->first()->partner->estimated_max_days);
    }

    public function test_shipping_migrations_are_safe_to_resume_after_a_partial_run(): void
    {
        $shippingMigration = require database_path('migrations/2026_09_01_000001_create_shipping_automation_tables.php');
        $vendorMigration = require database_path('migrations/2026_09_01_000002_add_shipping_class_to_vendor_listings.php');

        $shippingMigration->up();
        $vendorMigration->up();

        $this->assertTrue(Schema::hasTable('shipping_region_rates'));
        $this->assertTrue(Schema::hasColumn('shipping_partners', 'estimated_min_days'));
        $this->assertTrue(Schema::hasColumn('shipping_partners', 'estimated_max_days'));
        $this->assertTrue(Schema::hasColumn('products', 'shipping_class_id'));
        $this->assertTrue(Schema::hasColumn('orders', 'shipping_region_id'));
        $this->assertTrue(Schema::hasColumn('vendor_listings', 'shipping_class_id'));
    }
}
