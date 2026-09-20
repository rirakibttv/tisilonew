<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Pages\PosSystem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingClass;
use App\Models\ShippingPartner;
use App\Models\ShippingRegion;
use App\Models\ShippingRegionRate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PosSystemTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pos_is_a_direct_searchable_workspace_with_district_shipping(): void
    {
        $admin = $this->admin();
        [$product] = $this->catalog();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('/admin/pos', false)
            ->assertDontSee('module=pos-system', false);

        $this->actingAs($admin)
            ->get('/admin/pos')
            ->assertOk()
            ->assertSee('Point of Sale')
            ->assertSee($product->name)
            ->assertSee('জেলা লিখে নির্বাচন করুন')
            ->assertDontSee('ডেলিভারি এরিয়া নির্বাচন করুন');
    }

    public function test_pos_calculates_district_shipping_and_creates_a_confirmed_inventory_safe_order(): void
    {
        $admin = $this->admin();
        [$product, $region] = $this->catalog();

        $this->actingAs($admin);

        Livewire::test(PosSystem::class)
            ->call('addProduct', $product->id, 0)
            ->assertSet('shippingQuotes.'.$region->id.'.amount', 80.0)
            ->call('selectDistrict', $region->id)
            ->assertSet('districtSearch', 'Dhaka')
            ->assertSet('shippingRegionId', $region->id)
            ->set('customerName', 'POS Customer')
            ->set('customerPhone', '01700000000')
            ->set('addressLine', 'House 10, Road 5')
            ->set('thana', 'Mirpur Model')
            ->call('completeSale')
            ->assertHasNoErrors()
            ->assertSet('cart', []);

        $order = Order::query()
            ->where('channel', 'pos')
            ->where('customer_phone', '01700000000')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('pos', $order->channel);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame('cod', $order->payment_method);
        $this->assertSame('580.00', $order->total_amount);
        $this->assertSame($region->id, $order->shipping_region_id);
        $this->assertSame('Dhaka', $order->shipping_address['district']);
        $this->assertSame('Mirpur Model', $order->shipping_address['upazila']);
        $this->assertSame(4, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'total_amount' => 500,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);
    }

    /** @return array{Product, ShippingRegion} */
    private function catalog(): array
    {
        $suffix = Str::lower(Str::random(6));
        $class = ShippingClass::query()->create([
            'name' => 'POS Standard',
            'code' => 'pos-standard-'.$suffix,
            'is_active' => true,
        ]);
        $partner = ShippingPartner::query()->create([
            'name' => 'POS Courier',
            'code' => 'pos-courier-'.$suffix,
            'estimated_min_days' => 1,
            'estimated_max_days' => 2,
            'is_active' => true,
        ]);
        $region = ShippingRegion::query()->create([
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'upazila' => 'All areas',
            'location_key' => 'pos-dhaka-'.$suffix,
            'is_active' => true,
        ]);
        ShippingRegionRate::query()->create([
            'shipping_region_id' => $region->id,
            'shipping_class_id' => $class->id,
            'shipping_partner_id' => $partner->id,
            'base_charge' => 80,
            'additional_item_charge' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'POS Cotton Bed Sheet '.$suffix,
            'slug' => 'pos-cotton-bed-sheet-'.$suffix,
            'product_type' => 'simple',
            'sku' => 'POS-'.$suffix,
            'regular_price' => 500,
            'manage_stock' => true,
            'stock_quantity' => 5,
            'stock_status' => 'in_stock',
            'shipping_class_id' => $class->id,
            'status' => 'published',
        ]);

        return [$product, $region];
    }
}
