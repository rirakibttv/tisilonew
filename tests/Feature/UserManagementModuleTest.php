<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\UserAccessControlSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserManagementModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_submenu_and_management_pages_are_available_in_the_requested_order(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(['User', 'Roles', 'Permission', 'Customer']);

        foreach ([
            '/admin/users', '/admin/users/create',
            '/admin/roles', '/admin/roles/create',
            '/admin/permissions', '/admin/permissions/create',
            '/admin/customers', '/admin/customers/create',
        ] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_customer_module_only_lists_customer_accounts(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create([
            'name' => 'Visible Customer Account',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
        ]);
        $operationsUser = User::factory()->create([
            'name' => 'Hidden Operations Account',
            'role' => UserRole::OperationsManager,
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get('/admin/customers')
            ->assertOk()
            ->assertSee($customer->name)
            ->assertDontSee('Hidden Operations Account');

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee($operationsUser->name)
            ->assertDontSee($customer->name);

        $this->actingAs($admin)
            ->get('/admin/users/'.$customer->getRouteKey().'/edit')
            ->assertNotFound();
    }

    public function test_roles_can_receive_permissions_and_be_assigned_to_users(): void
    {
        $this->seed(UserAccessControlSeeder::class);

        $role = Role::query()->create([
            'name' => 'Customer Care',
            'slug' => 'customer-care',
            'status' => true,
        ]);
        $permission = Permission::query()->where('slug', 'customers.manage')->firstOrFail();
        $role->permissions()->sync([$permission->id]);

        $user = User::factory()->create([
            'role' => UserRole::OperationsManager,
            'status' => UserStatus::Active,
            'rbac_role_id' => $role->id,
        ]);

        $this->assertTrue($user->hasPermission('customers.manage'));
        $this->assertFalse($user->hasPermission('settings.manage'));
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);
    }
}
