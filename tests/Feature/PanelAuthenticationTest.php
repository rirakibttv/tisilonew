<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VendorListingStatus;
use App\Enums\VendorMemberRole;
use App\Enums\VendorStatus;
use App\Filament\Seller\Auth\RegisterSeller;
use App\Filament\Seller\Resources\Staff\Pages\CreateStaff;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Support\SellerAccess;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PanelAuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_and_seller_login_pages_show_password_recovery_and_seller_registration(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('/admin/password-reset/request', false);

        $this->get('/seller/login')
            ->assertOk()
            ->assertSee('/seller/password-reset/request', false)
            ->assertSee('/seller/register', false);

        $this->get('/seller/register')
            ->assertOk()
            ->assertSee('নতুন সেলার রেজিস্ট্রেশন');
    }

    public function test_new_seller_registration_creates_an_isolated_vendor_account_pending_review(): void
    {
        Filament::setCurrentPanel('seller');
        $email = 'seller-'.Str::lower(Str::random(10)).'@example.test';

        Livewire::test(RegisterSeller::class)
            ->fillForm([
                'name' => 'New Seller',
                'store_name' => 'New Seller Store',
                'phone' => '01712345678',
                'email' => $email,
                'password' => 'SecurePassword!123',
                'passwordConfirmation' => 'SecurePassword!123',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $seller = User::query()->where('email', $email)->firstOrFail();
        $vendor = Vendor::query()->where('owner_id', $seller->getKey())->firstOrFail();

        $this->assertSame(UserRole::VendorOwner, $seller->role);
        $this->assertSame(UserStatus::Active, $seller->status);
        $this->assertTrue(Hash::check('SecurePassword!123', $seller->password));
        $this->assertSame(VendorStatus::Pending, $vendor->status);
        $this->assertTrue($seller->canAccessPanel(Filament::getPanel('seller')));
        $this->assertFalse($seller->canAccessPanel(Filament::getPanel('admin')));
        $this->assertAuthenticatedAs($seller, 'seller');
        $this->assertDatabaseHas('vendor_user', [
            'vendor_id' => $vendor->getKey(),
            'user_id' => $seller->getKey(),
            'role' => 'owner',
            'status' => 'active',
        ]);
    }

    public function test_vendor_dashboard_shows_the_authenticated_sellers_shop_summary(): void
    {
        $seller = User::factory()->create([
            'role' => UserRole::VendorOwner,
            'status' => UserStatus::Active,
        ]);

        Vendor::query()->create([
            'owner_id' => $seller->getKey(),
            'name' => 'Professional Seller Shop',
            'slug' => 'professional-seller-shop',
            'status' => VendorStatus::Active,
            'commission_rate' => 7.5,
        ]);

        $this->actingAs($seller, 'seller')
            ->get('/seller')
            ->assertOk()
            ->assertSee('Vendor Dashboard')
            ->assertSee('Professional Seller Shop')
            ->assertSee('Total Orders')
            ->assertSee('Delivered Sales Trend')
            ->assertSee('Recent Orders');
    }

    public function test_vendor_owner_can_open_the_complete_seller_center(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::VendorOwner,
            'status' => UserStatus::Active,
        ]);

        $vendor = Vendor::query()->create([
            'owner_id' => $owner->getKey(),
            'name' => 'Owner Store',
            'slug' => 'owner-store-'.Str::lower(Str::random(6)),
            'status' => VendorStatus::Active,
            'commission_rate' => 5,
        ]);

        $vendor->members()->attach($owner->getKey(), [
            'role' => VendorMemberRole::Owner->value,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($owner, 'seller')
            ->get('/seller')
            ->assertOk()
            ->assertSee('My Products')
            ->assertSee('Orders')
            ->assertSee('Fraud Checker')
            ->assertSee('Inventory')
            ->assertSee('Warehouses')
            ->assertSee('Staff &amp; Permissions', false)
            ->assertSee('Shop Profile');

        foreach ([
            '/seller/products', '/seller/products/create',
            '/seller/orders',
            '/seller/fraud-checker',
            '/seller/inventory', '/seller/inventory/create',
            '/seller/warehouses', '/seller/warehouses/create',
            '/seller/staff', '/seller/staff/create',
            '/seller/shop-profile',
        ] as $path) {
            $this->actingAs($owner, 'seller')->get($path)->assertOk();
        }

        $this->actingAs($owner, 'seller')
            ->get('/seller/products/create')
            ->assertOk()
            ->assertSee('Basic Information')
            ->assertSee('Product Offer')
            ->assertSee('Publishing')
            ->assertSee('Shipping &amp; Fulfillment', false);
    }

    public function test_vendor_staff_permissions_control_visible_seller_modules(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::VendorOwner,
            'status' => UserStatus::Active,
        ]);
        $staff = User::factory()->create([
            'role' => UserRole::VendorStaff,
            'status' => UserStatus::Active,
        ]);
        $vendor = Vendor::query()->create([
            'owner_id' => $owner->getKey(),
            'name' => 'Permission Store',
            'slug' => 'permission-store-'.Str::lower(Str::random(6)),
            'status' => VendorStatus::Active,
            'commission_rate' => 5,
        ]);

        $vendor->members()->attach($staff->getKey(), [
            'role' => VendorMemberRole::OrderManager->value,
            'status' => 'active',
            'permissions' => json_encode([SellerAccess::WAREHOUSES_VIEW]),
            'joined_at' => now(),
        ]);

        $this->actingAs($staff, 'seller');

        $this->assertTrue($staff->canAccessPanel(Filament::getPanel('seller')));
        $this->assertTrue(SellerAccess::can(SellerAccess::ORDERS_MANAGE, $staff, $vendor));
        $this->assertTrue(SellerAccess::can(SellerAccess::WAREHOUSES_VIEW, $staff, $vendor));
        $this->assertFalse(SellerAccess::can(SellerAccess::PRODUCTS_VIEW, $staff, $vendor));
        $this->assertFalse(SellerAccess::can(SellerAccess::STAFF_MANAGE, $staff, $vendor));

        $this->get('/seller/orders')->assertOk();
        $this->get('/seller/warehouses')->assertOk();
        $this->get('/seller/products')->assertForbidden();
        $this->get('/seller/staff')->assertForbidden();
    }

    public function test_inactive_vendor_staff_cannot_access_the_seller_panel(): void
    {
        $owner = User::factory()->create(['role' => UserRole::VendorOwner]);
        $staff = User::factory()->create([
            'role' => UserRole::VendorStaff,
            'status' => UserStatus::Active,
        ]);
        $vendor = Vendor::query()->create([
            'owner_id' => $owner->getKey(),
            'name' => 'Inactive Staff Store',
            'slug' => 'inactive-staff-store-'.Str::lower(Str::random(6)),
            'status' => VendorStatus::Active,
            'commission_rate' => 5,
        ]);

        $vendor->members()->attach($staff->getKey(), [
            'role' => VendorMemberRole::Staff->value,
            'status' => 'inactive',
            'joined_at' => now(),
        ]);

        $this->assertFalse($staff->canAccessPanel(Filament::getPanel('seller')));
    }

    public function test_vendor_owner_can_create_staff_with_scoped_permissions(): void
    {
        Filament::setCurrentPanel('seller');
        $owner = User::factory()->create([
            'role' => UserRole::VendorOwner,
            'status' => UserStatus::Active,
        ]);
        $vendor = Vendor::query()->create([
            'owner_id' => $owner->getKey(),
            'name' => 'Staff Management Store',
            'slug' => 'staff-management-store-'.Str::lower(Str::random(6)),
            'status' => VendorStatus::Active,
            'commission_rate' => 5,
        ]);
        $vendor->members()->attach($owner->getKey(), [
            'role' => VendorMemberRole::Owner->value,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $staffEmail = 'vendor-staff-'.Str::lower(Str::random(8)).'@example.test';

        $this->actingAs($owner, 'seller');

        Livewire::test(CreateStaff::class)
            ->fillForm([
                'name' => 'Catalog Staff',
                'email' => $staffEmail,
                'phone' => '01700000001',
                'password' => 'SecureStaff!123',
                'member_role' => VendorMemberRole::CatalogManager->value,
                'membership_status' => 'active',
                'vendor_permissions' => [SellerAccess::STAFF_VIEW],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $staff = User::query()->where('email', $staffEmail)->firstOrFail();
        $membership = $staff->vendors()->whereKey($vendor->getKey())->firstOrFail()->pivot;

        $this->assertSame(UserRole::VendorStaff, $staff->role);
        $this->assertSame(VendorMemberRole::CatalogManager->value, $membership->role);
        $this->assertSame('active', $membership->status);
        $this->assertContains(SellerAccess::STAFF_VIEW, json_decode($membership->permissions, true));
        $this->assertTrue(SellerAccess::can(SellerAccess::PRODUCTS_MANAGE, $staff, $vendor));
        $this->assertTrue(SellerAccess::can(SellerAccess::STAFF_VIEW, $staff, $vendor));
        $this->assertFalse(SellerAccess::can(SellerAccess::STAFF_MANAGE, $staff, $vendor));
    }

    public function test_seller_resources_do_not_expose_another_vendors_records(): void
    {
        $firstOwner = User::factory()->create(['role' => UserRole::VendorOwner, 'status' => UserStatus::Active]);
        $secondOwner = User::factory()->create(['role' => UserRole::VendorOwner, 'status' => UserStatus::Active]);
        $firstVendor = Vendor::query()->create([
            'owner_id' => $firstOwner->getKey(),
            'name' => 'First Secure Store',
            'slug' => 'first-secure-store-'.Str::lower(Str::random(6)),
            'status' => VendorStatus::Active,
            'commission_rate' => 5,
        ]);
        $secondVendor = Vendor::query()->create([
            'owner_id' => $secondOwner->getKey(),
            'name' => 'Second Secure Store',
            'slug' => 'second-secure-store-'.Str::lower(Str::random(6)),
            'status' => VendorStatus::Active,
            'commission_rate' => 5,
        ]);
        $product = Product::query()->create([
            'name' => 'Private Vendor Product',
            'slug' => 'private-vendor-product-'.Str::lower(Str::random(6)),
            'product_type' => 'simple',
            'regular_price' => 100,
            'stock_quantity' => 0,
            'stock_status' => 'out_of_stock',
            'status' => 'published',
        ]);
        $otherListing = VendorListing::query()->create([
            'vendor_id' => $secondVendor->getKey(),
            'product_id' => $product->getKey(),
            'status' => VendorListingStatus::Approved,
        ]);

        $firstVendor->members()->attach($firstOwner->getKey(), [
            'role' => VendorMemberRole::Owner->value,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($firstOwner, 'seller')
            ->get('/seller/products/'.$otherListing->getKey().'/edit')
            ->assertNotFound();
    }

    public function test_admin_password_reset_request_sends_a_panel_reset_link(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $admin->email])
            ->call('request')
            ->assertHasNoFormErrors();

        Notification::assertSentTo($admin, ResetPassword::class);
    }

    public function test_seller_password_reset_request_sends_a_seller_panel_link(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('seller');
        $seller = User::factory()->create([
            'role' => UserRole::VendorOwner,
            'status' => UserStatus::Active,
        ]);
        $vendor = Vendor::query()->create([
            'owner_id' => $seller->getKey(),
            'name' => 'Password Reset Store',
            'slug' => 'password-reset-store-'.Str::lower(Str::random(6)),
            'status' => VendorStatus::Active,
            'commission_rate' => 5,
        ]);
        $vendor->members()->attach($seller->getKey(), [
            'role' => VendorMemberRole::Owner->value,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $seller->email])
            ->call('request')
            ->assertHasNoFormErrors();

        Notification::assertSentTo(
            $seller,
            ResetPassword::class,
            fn (ResetPassword $notification): bool => str_contains($notification->url, '/seller/password-reset/reset'),
        );
    }
}
