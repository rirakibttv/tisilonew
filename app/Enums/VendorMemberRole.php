<?php

namespace App\Enums;

enum VendorMemberRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case CatalogManager = 'catalog_manager';
    case OrderManager = 'order_manager';
    case Accountant = 'accountant';
    case Support = 'support';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::CatalogManager => 'Catalog Manager',
            self::OrderManager => 'Order Manager',
            self::Accountant => 'Accountant',
            self::Support => 'Support',
            self::Staff => 'Staff',
        };
    }
}
