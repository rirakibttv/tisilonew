<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case OperationsManager = 'operations_manager';
    case VendorOwner = 'vendor_owner';
    case VendorStaff = 'vendor_staff';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::OperationsManager => 'Operations Manager',
            self::VendorOwner => 'Vendor Owner',
            self::VendorStaff => 'Vendor Staff',
            self::Customer => 'Customer',
        };
    }

    public function canAccessAdminPanel(): bool
    {
        return in_array($this, [
            self::SuperAdmin,
            self::Admin,
            self::OperationsManager,
        ], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(
            array_map(
                fn (self $role): array => [$role->value, $role->label()],
                self::cases(),
            ),
            1,
            0,
        );
    }
}
