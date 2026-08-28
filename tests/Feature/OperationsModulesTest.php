<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Pages\OperationsModule;
use App\Models\OperationalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class OperationsModulesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_remaining_backend_submenus_are_visible_and_open_operational_pages(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSeeInOrder([
            'POS System', 'Fraud Checker API', 'Products Info', 'Order Panel', 'Shipping',
            'Offer Panel', 'Vendors', 'Refunds', 'Coupons', 'Blog', 'Accounts', 'CRM / HR',
            'Reviews', 'Landing Page', 'Complaints', 'Marketing', 'User', 'SEO Overview',
            'Live Ads Result', 'API Integration', 'Pages', 'General Settings',
        ]);

        foreach ([
            ['shipping', 'charges', 'Shipping Charge'],
            ['offer-panel', 'banners', 'Banner &amp; Sliders'],
            ['refunds', 'pending', 'Pending Refunds'],
            ['coupons', 'all', 'All Coupons'],
            ['blog', 'all', 'All Blogs'],
            ['accounts', 'expenses', 'Expenses'],
            ['crm-hr', 'employees', 'Employees'],
            ['reviews', 'pending', 'Pending Reviews'],
            ['complaints', 'all', 'All Complaints'],
            ['marketing', 'newsletter', 'Newsletter Subscribers'],
            ['live-ads-result', 'facebook', 'Facebook Ads'],
            ['pages', 'all', 'All Pages'],
        ] as [$module, $section, $label]) {
            $this->actingAs($admin)
                ->get(OperationsModule::getUrl(compact('module', 'section')))
                ->assertOk()
                ->assertSee($label, false);
        }
    }

    public function test_operations_page_can_create_update_and_delete_records(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $this->actingAs($admin);

        $component = Livewire::withQueryParams(['module' => 'coupons', 'section' => 'all'])
            ->test(OperationsModule::class)
            ->set('recordTitle', 'Festival 20 Percent')
            ->set('reference', 'FEST20')
            ->set('status', 'active')
            ->set('amount', '20')
            ->call('save')
            ->assertHasNoErrors();

        $record = OperationalRecord::query()->where('reference', 'FEST20')->firstOrFail();
        $component->call('edit', $record->id)
            ->set('recordTitle', 'Festival 25 Percent')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Festival 25 Percent', $record->fresh()->title);
        $component->call('delete', $record->id);
        $this->assertSoftDeleted($record);
    }
}
