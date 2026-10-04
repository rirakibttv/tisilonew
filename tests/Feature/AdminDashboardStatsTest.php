<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VendorListingStatus;
use App\Enums\VendorStatus;
use App\Filament\Pages\Dashboard;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorListing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class AdminDashboardStatsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_marketplace_summary_cards_are_grouped_and_count_live_records(): void
    {
        $this->travelTo('2026-10-04 12:00:00');

        $before = $this->dashboardStats();
        $suffix = Str::lower(Str::random(10));

        Order::query()->create([
            'customer_name' => 'Today Pending Customer',
            'status' => OrderStatus::Pending,
        ]);
        Order::query()->create([
            'customer_name' => 'Today Confirmed Customer',
            'status' => OrderStatus::Confirmed,
        ]);
        $oldOrder = Order::query()->create([
            'customer_name' => 'Older Pending Customer',
            'status' => OrderStatus::Pending,
        ]);
        $oldOrder->forceFill(['placed_at' => now()->subDays(2)])->saveQuietly();

        $todayProduct = Product::query()->create([
            'name' => 'Today Pending Product',
            'slug' => 'today-pending-product-'.$suffix,
            'status' => 'pending',
        ]);
        $recentProduct = Product::query()->create([
            'name' => 'Recent Published Product',
            'slug' => 'recent-published-product-'.$suffix,
            'status' => 'published',
        ]);
        $this->moveCreatedAt($recentProduct, now()->subDays(3));
        $oldProduct = Product::query()->create([
            'name' => 'Older Draft Product',
            'slug' => 'older-draft-product-'.$suffix,
            'status' => 'draft',
        ]);
        $this->moveCreatedAt($oldProduct, now()->subDays(8));

        $owner = User::factory()->create([
            'role' => UserRole::VendorOwner,
            'status' => UserStatus::Active,
        ]);
        Vendor::query()->create([
            'owner_id' => $owner->getKey(),
            'name' => 'Today Pending Vendor',
            'slug' => 'today-pending-vendor-'.$suffix,
            'status' => VendorStatus::Pending,
        ]);
        $oldPendingVendor = Vendor::query()->create([
            'owner_id' => $owner->getKey(),
            'name' => 'Older Pending Vendor',
            'slug' => 'older-pending-vendor-'.$suffix,
            'status' => VendorStatus::Pending,
        ]);
        $this->moveCreatedAt($oldPendingVendor, now()->subDays(2));
        $approvedVendor = Vendor::query()->create([
            'owner_id' => $owner->getKey(),
            'name' => 'Approved Vendor',
            'slug' => 'approved-vendor-'.$suffix,
            'status' => VendorStatus::Active,
        ]);
        $this->moveCreatedAt($approvedVendor, now()->subDays(2));

        VendorListing::query()->create([
            'vendor_id' => $owner->ownedVendors()->firstOrFail()->getKey(),
            'product_id' => $todayProduct->getKey(),
            'status' => VendorListingStatus::Pending,
        ]);
        $recentVendorProduct = VendorListing::query()->create([
            'vendor_id' => $owner->ownedVendors()->firstOrFail()->getKey(),
            'product_id' => $recentProduct->getKey(),
            'status' => VendorListingStatus::Approved,
        ]);
        $this->moveCreatedAt($recentVendorProduct, now()->subDays(3));
        $oldVendorProduct = VendorListing::query()->create([
            'vendor_id' => $owner->ownedVendors()->firstOrFail()->getKey(),
            'product_id' => $oldProduct->getKey(),
            'status' => VendorListingStatus::Draft,
        ]);
        $this->moveCreatedAt($oldVendorProduct, now()->subDays(8));

        User::factory()->create([
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
        ]);
        $inactiveCustomer = User::factory()->create([
            'role' => UserRole::Customer,
            'status' => UserStatus::Inactive,
        ]);
        $this->moveCreatedAt($inactiveCustomer, now()->subDays(2));
        $activeCustomer = User::factory()->create([
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
        ]);
        $this->moveCreatedAt($activeCustomer, now()->subDays(2));

        $after = $this->dashboardStats();

        $this->assertSame([
            "Today's Total Order",
            'Pending Order',
            'Confirmed Order',
            'Total Order',
            "Today's New Product",
            'New Products',
            'Pending Products',
            'All Products',
            "Today's New Vendor Request",
            'Pending Vendor',
            'Approved Vendor',
            'Total Vendor',
            "Today's Vendor New Product",
            'Vendor New Products',
            'Vendor Pending Products',
            'Vendor All Products',
            "Today's New Customer",
            'Active Customers',
            'Inactive Customers',
            'Total Customers',
        ], array_keys($after));

        $expectedIncreases = [
            "Today's Total Order" => 2,
            'Pending Order' => 2,
            'Confirmed Order' => 1,
            'Total Order' => 3,
            "Today's New Product" => 1,
            'New Products' => 2,
            'Pending Products' => 1,
            'All Products' => 3,
            "Today's New Vendor Request" => 1,
            'Pending Vendor' => 2,
            'Approved Vendor' => 1,
            'Total Vendor' => 3,
            "Today's Vendor New Product" => 1,
            'Vendor New Products' => 2,
            'Vendor Pending Products' => 1,
            'Vendor All Products' => 3,
            "Today's New Customer" => 1,
            'Active Customers' => 2,
            'Inactive Customers' => 1,
            'Total Customers' => 3,
        ];

        foreach ($expectedIncreases as $label => $increase) {
            $this->assertSame($before[$label] + $increase, $after[$label], $label);
        }

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertDontSeeText('Hi! Welcome To Dashboard')
            ->assertDontSeeText('Home → Marketplace Dashboard')
            ->assertSeeText('Tisilo enterprise marketplace')
            ->assertSeeText("Congratulations {$admin->name} 🎉")
            ->assertSeeText('Your marketplace control center is ready. Keep building a trusted shopping experience.')
            ->assertSeeTextInOrder(array_keys($after));
    }

    /** @return array<string, int> */
    private function dashboardStats(): array
    {
        $method = new ReflectionMethod(Dashboard::class, 'getViewData');
        $data = $method->invoke(new Dashboard);

        return collect($data['stats'])
            ->mapWithKeys(fn (array $stat): array => [
                $stat['label'] => (int) str_replace(',', '', $stat['value']),
            ])
            ->all();
    }

    private function moveCreatedAt(Model $model, mixed $createdAt): void
    {
        $model->timestamps = false;
        $model->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();
    }
}
