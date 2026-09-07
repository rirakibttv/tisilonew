<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VendorStatus;
use App\Filament\Seller\Auth\RegisterSeller;
use App\Models\User;
use App\Models\Vendor;
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
