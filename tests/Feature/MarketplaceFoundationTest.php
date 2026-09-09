<?php

namespace Tests\Feature;

use App\Enums\AdminNavigationGroup;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VendorMemberRole;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use Filament\Panel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MarketplaceFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_only_active_back_office_users_can_access_the_admin_panel(): void
    {
        $panel = Panel::make()->id('admin');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $vendor = User::factory()->create([
            'role' => UserRole::VendorOwner,
            'status' => UserStatus::Active,
        ]);

        $suspendedAdmin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'status' => UserStatus::Suspended,
        ]);

        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertFalse($vendor->canAccessPanel($panel));
        $this->assertFalse($suspendedAdmin->canAccessPanel($panel));
    }

    public function test_a_vendor_can_have_an_owner_and_scoped_members(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::VendorOwner,
        ]);

        $staff = User::factory()->create([
            'role' => UserRole::VendorStaff,
        ]);

        $vendor = Vendor::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Test Vendor',
            'slug' => 'test-vendor-'.str()->random(8),
            'status' => VendorStatus::Pending,
            'commission_rate' => 10,
        ]);

        $vendor->members()->attach($owner->id, [
            'role' => VendorMemberRole::Owner->value,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $vendor->members()->attach($staff->id, [
            'role' => VendorMemberRole::Staff->value,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->assertTrue($vendor->owner->is($owner));
        $this->assertCount(2, $vendor->members);
        $this->assertSame(
            VendorMemberRole::Owner->value,
            $vendor->members->firstWhere('id', $owner->id)->pivot->role,
        );
    }

    public function test_an_admin_can_open_vendor_and_user_management(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get('/admin/vendors')
            ->assertOk()
            ->assertSee('All Vendors');

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('All Users');

        $this->actingAs($admin)
            ->get('/admin/vendor-listings')
            ->assertOk()
            ->assertSee('Vendor Listings');

        $this->actingAs($admin)
            ->get('/admin/vendor-warehouses')
            ->assertOk()
            ->assertSee('Warehouses');

        $this->actingAs($admin)
            ->get('/admin/inventory-stocks')
            ->assertOk()
            ->assertSee('Inventory');
    }

    public function test_admin_navigation_displays_every_required_module_in_order(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Congratulations')
            ->assertSee('Marketplace Activity')
            ->assertSee('Recent Products')
            ->assertSee('Recent Customers')
            ->assertSeeInOrder(array_map(
                fn (AdminNavigationGroup $group): string => $group->getLabel(),
                AdminNavigationGroup::cases(),
            ));

        $this->get('/admin/pos')
            ->assertOk()
            ->assertSee('Point of Sale');
    }
}
