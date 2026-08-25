<?php

namespace Tests\Unit;

use App\Enums\AdminNavigationGroup;
use PHPUnit\Framework\TestCase;

class AdminNavigationGroupTest extends TestCase
{
    public function test_admin_navigation_groups_follow_the_required_order(): void
    {
        $this->assertSame([
            'POS System',
            'Fraud Checker API',
            'Products Info',
            'Order Panel',
            'Shipping',
            'Offer Panel',
            'Vendors',
            'Refunds',
            'Coupons',
            'Blog',
            'Accounts',
            'CRM / HR',
            'Reviews',
            'Landing Page',
            'Complaints',
            'Marketing',
            'User',
            'SEO Overview',
            'Live Ads Result',
            'API Integration',
            'Pages',
            'General Settings',
        ], array_map(
            fn (AdminNavigationGroup $group): string => $group->getLabel(),
            AdminNavigationGroup::cases(),
        ));
    }
}
