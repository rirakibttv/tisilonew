<?php

namespace App\Support;

use App\Enums\VendorMemberRole;
use App\Models\User;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class SellerAccess
{
    public const DASHBOARD_VIEW = 'dashboard.view';

    public const PRODUCTS_VIEW = 'products.view';

    public const PRODUCTS_MANAGE = 'products.manage';

    public const ORDERS_VIEW = 'orders.view';

    public const ORDERS_MANAGE = 'orders.manage';

    public const FRAUD_CHECK = 'fraud.check';

    public const INVENTORY_VIEW = 'inventory.view';

    public const INVENTORY_MANAGE = 'inventory.manage';

    public const WAREHOUSES_VIEW = 'warehouses.view';

    public const WAREHOUSES_MANAGE = 'warehouses.manage';

    public const STAFF_VIEW = 'staff.view';

    public const STAFF_MANAGE = 'staff.manage';

    public const SHOP_VIEW = 'shop.view';

    public const SHOP_MANAGE = 'shop.manage';

    /** @return array<string, string> */
    public static function permissionOptions(): array
    {
        return [
            self::DASHBOARD_VIEW => 'View Dashboard',
            self::PRODUCTS_VIEW => 'View Products',
            self::PRODUCTS_MANAGE => 'Create & Edit Products',
            self::ORDERS_VIEW => 'View Orders',
            self::ORDERS_MANAGE => 'Process Orders',
            self::FRAUD_CHECK => 'Use Fraud Checker',
            self::INVENTORY_VIEW => 'View Inventory',
            self::INVENTORY_MANAGE => 'Adjust Inventory',
            self::WAREHOUSES_VIEW => 'View Warehouses',
            self::WAREHOUSES_MANAGE => 'Manage Warehouses',
            self::STAFF_VIEW => 'View Staff',
            self::STAFF_MANAGE => 'Manage Staff & Permissions',
            self::SHOP_VIEW => 'View Shop Profile',
            self::SHOP_MANAGE => 'Edit Shop Profile',
        ];
    }

    public static function user(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function currentVendor(?User $user = null): ?Vendor
    {
        $user ??= self::user();

        if (! $user || ! Schema::hasTable('vendors')) {
            return null;
        }

        $cacheKey = 'seller.current_vendor.'.$user->getKey();
        $request = app()->bound('request') ? request() : null;

        if ($request?->attributes->has($cacheKey)) {
            $vendor = $request->attributes->get($cacheKey);

            return $vendor instanceof Vendor ? $vendor : null;
        }

        $vendor = $user->ownedVendors()->first();

        if (! $vendor && Schema::hasTable('vendor_user')) {
            $vendor = $user->vendors()
                ->wherePivot('status', 'active')
                ->orderByPivot('joined_at')
                ->first();
        }

        $request?->attributes->set($cacheKey, $vendor);

        return $vendor;
    }

    public static function can(string $permission, ?User $user = null, ?Vendor $vendor = null): bool
    {
        $user ??= self::user();
        $vendor ??= self::currentVendor($user);

        if (! $user || ! $vendor) {
            return false;
        }

        if ((int) $vendor->owner_id === (int) $user->getKey()) {
            return true;
        }

        $membership = $user->vendors()
            ->whereKey($vendor->getKey())
            ->wherePivot('status', 'active')
            ->first()?->pivot;

        if (! $membership) {
            return false;
        }

        $role = VendorMemberRole::tryFrom((string) $membership->role) ?? VendorMemberRole::Staff;
        $permissions = array_values(array_unique([
            ...self::defaultPermissions($role),
            ...self::decodePermissions($membership->permissions),
        ]));

        return in_array($permission, $permissions, true);
    }

    public static function hasSellerAccess(?User $user = null): bool
    {
        $user ??= self::user();

        return self::currentVendor($user) !== null;
    }

    /** @return list<string> */
    public static function defaultPermissions(VendorMemberRole $role): array
    {
        $dashboard = [self::DASHBOARD_VIEW];

        return match ($role) {
            VendorMemberRole::Owner => array_keys(self::permissionOptions()),
            VendorMemberRole::Manager => [
                ...$dashboard,
                self::PRODUCTS_VIEW, self::PRODUCTS_MANAGE,
                self::ORDERS_VIEW, self::ORDERS_MANAGE,
                self::FRAUD_CHECK,
                self::INVENTORY_VIEW, self::INVENTORY_MANAGE,
                self::WAREHOUSES_VIEW, self::WAREHOUSES_MANAGE,
                self::STAFF_VIEW,
                self::SHOP_VIEW,
            ],
            VendorMemberRole::CatalogManager => [
                ...$dashboard,
                self::PRODUCTS_VIEW, self::PRODUCTS_MANAGE,
                self::INVENTORY_VIEW, self::INVENTORY_MANAGE,
                self::WAREHOUSES_VIEW,
            ],
            VendorMemberRole::OrderManager => [
                ...$dashboard,
                self::ORDERS_VIEW, self::ORDERS_MANAGE,
                self::INVENTORY_VIEW,
                self::FRAUD_CHECK,
            ],
            VendorMemberRole::Accountant => [
                ...$dashboard,
                self::ORDERS_VIEW,
            ],
            VendorMemberRole::Support => [
                ...$dashboard,
                self::ORDERS_VIEW,
                self::FRAUD_CHECK,
            ],
            VendorMemberRole::Staff => [
                ...$dashboard,
                self::PRODUCTS_VIEW,
                self::ORDERS_VIEW,
                self::INVENTORY_VIEW,
                self::FRAUD_CHECK,
            ],
        };
    }

    /** @return list<string> */
    private static function decodePermissions(mixed $permissions): array
    {
        if (is_string($permissions)) {
            $permissions = json_decode($permissions, true);
        }

        return collect(Arr::wrap($permissions))
            ->filter(fn ($permission): bool => is_string($permission) && array_key_exists($permission, self::permissionOptions()))
            ->values()
            ->all();
    }
}
