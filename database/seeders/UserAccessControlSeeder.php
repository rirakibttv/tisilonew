<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserAccessControlSeeder extends Seeder
{
    /** @var array<string, array<string, string>> */
    private const PERMISSIONS = [
        'Dashboard' => ['dashboard.view' => 'View Dashboard'],
        'POS System' => ['pos.manage' => 'Manage POS System'],
        'Fraud Checker API' => ['fraud.manage' => 'Manage Fraud Checker'],
        'Products Info' => ['products.manage' => 'Manage Products'],
        'Order Panel' => ['orders.manage' => 'Manage Orders'],
        'Shipping' => ['shipping.manage' => 'Manage Shipping'],
        'Offer Panel' => ['offers.manage' => 'Manage Offers'],
        'Vendors' => ['vendors.manage' => 'Manage Vendors'],
        'Refunds' => ['refunds.manage' => 'Manage Refunds'],
        'Coupons' => ['coupons.manage' => 'Manage Coupons'],
        'Blog' => ['blog.manage' => 'Manage Blog'],
        'Accounts' => ['accounts.manage' => 'Manage Accounts'],
        'CRM / HR' => ['crm_hr.manage' => 'Manage CRM / HR'],
        'Reviews' => ['reviews.manage' => 'Manage Reviews'],
        'Landing Page' => ['landing_pages.manage' => 'Manage Landing Pages'],
        'Complaints' => ['complaints.manage' => 'Manage Complaints'],
        'Marketing' => ['marketing.manage' => 'Manage Marketing'],
        'User' => [
            'users.manage' => 'Manage Users',
            'roles.manage' => 'Manage Roles',
            'permissions.manage' => 'Manage Permissions',
            'customers.manage' => 'Manage Customers',
        ],
        'SEO Overview' => ['seo.manage' => 'Manage SEO'],
        'Live Ads Result' => ['ads.manage' => 'Manage Live Ads'],
        'API Integration' => ['api_integrations.manage' => 'Manage API Integrations'],
        'Pages' => ['pages.manage' => 'Manage Pages'],
        'General Settings' => ['settings.manage' => 'Manage General Settings'],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $group => $permissions) {
            foreach ($permissions as $slug => $name) {
                Permission::query()->updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $name, 'group' => $group, 'status' => true],
                );
            }
        }

        $allPermissionIds = Permission::query()->pluck('id');
        $operationalPermissionIds = Permission::query()
            ->whereIn('slug', [
                'dashboard.view', 'pos.manage', 'fraud.manage', 'products.manage',
                'orders.manage', 'shipping.manage', 'offers.manage', 'vendors.manage',
                'refunds.manage', 'coupons.manage', 'reviews.manage', 'customers.manage',
            ])
            ->pluck('id');

        $roles = [
            UserRole::SuperAdmin->value => ['Super Admin', $allPermissionIds],
            UserRole::Admin->value => ['Admin', $allPermissionIds],
            UserRole::OperationsManager->value => ['Operations Manager', $operationalPermissionIds],
            UserRole::VendorOwner->value => ['Vendor Owner', collect()],
            UserRole::VendorStaff->value => ['Vendor Staff', collect()],
            UserRole::Customer->value => ['Customer', collect()],
        ];

        foreach ($roles as $slug => [$name, $permissionIds]) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_system' => true, 'status' => true],
            );
            $role->permissions()->sync($permissionIds);

            User::query()
                ->where('role', $slug)
                ->whereNull('access_role_id')
                ->update(['access_role_id' => $role->id]);
        }
    }
}
