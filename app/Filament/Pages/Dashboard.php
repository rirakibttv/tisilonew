<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VendorListingStatus;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorListing;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    public function getTitle(): string|Htmlable
    {
        return 'Hi! Welcome To Tisilo enterprise marketplace Dashboard';
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $hasOrders = Schema::hasTable('orders');
        $hasProducts = Schema::hasTable('products');
        $hasVendors = Schema::hasTable('vendors');
        $hasVendorListings = Schema::hasTable('vendor_listings');
        $hasUsers = Schema::hasTable('users');
        $hasCategories = Schema::hasTable('categories');

        $todayStartsAt = now()->startOfDay();
        $tomorrowStartsAt = $todayStartsAt->copy()->addDay();
        $newProductWindowStartsAt = $todayStartsAt->copy()->subDays(6);

        $orderMetrics = $hasOrders
            ? Order::query()
                ->selectRaw('COUNT(*) AS total_orders')
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN placed_at >= ? AND placed_at < ? THEN 1 ELSE 0 END), 0) AS todays_total_orders',
                    [$todayStartsAt, $tomorrowStartsAt],
                )
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS pending_orders',
                    [OrderStatus::Pending->value],
                )
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS confirmed_orders',
                    [OrderStatus::Confirmed->value],
                )
                ->first()
            : null;

        $productMetrics = $hasProducts
            ? Product::query()
                ->selectRaw('COUNT(*) AS all_products')
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) AS todays_new_products',
                    [$todayStartsAt, $tomorrowStartsAt],
                )
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) AS new_products',
                    [$newProductWindowStartsAt, $tomorrowStartsAt],
                )
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS pending_products',
                    ['pending'],
                )
                ->first()
            : null;

        $vendorMetrics = $hasVendors
            ? Vendor::query()
                ->selectRaw('COUNT(*) AS total_vendors')
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) AS todays_new_vendor_requests',
                    [$todayStartsAt, $tomorrowStartsAt],
                )
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS pending_vendors',
                    [VendorStatus::Pending->value],
                )
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS approved_vendors',
                    [VendorStatus::Active->value],
                )
                ->first()
            : null;

        $vendorProductMetrics = $hasVendorListings
            ? VendorListing::query()
                ->selectRaw('COUNT(*) AS all_products')
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) AS todays_new_products',
                    [$todayStartsAt, $tomorrowStartsAt],
                )
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) AS new_products',
                    [$newProductWindowStartsAt, $tomorrowStartsAt],
                )
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS pending_products',
                    [VendorListingStatus::Pending->value],
                )
                ->first()
            : null;

        $customerMetrics = $hasUsers
            ? User::query()
                ->where('role', UserRole::Customer->value)
                ->selectRaw('COUNT(*) AS total_customers')
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) AS todays_new_customers',
                    [$todayStartsAt, $tomorrowStartsAt],
                )
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS active_customers',
                    [UserStatus::Active->value],
                )
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS inactive_customers',
                    [UserStatus::Inactive->value],
                )
                ->first()
            : null;

        $recentProducts = $hasProducts
            ? Product::query()->with('category:id,name')->latest()->limit(5)->get()
            : collect();

        $recentCustomers = $hasUsers
            ? User::query()
                ->where('role', UserRole::Customer->value)
                ->latest()
                ->limit(5)
                ->get(['id', 'name', 'email', 'phone', 'created_at'])
            : collect();

        $categoryBreakdown = ($hasProducts && $hasCategories)
            ? Category::query()
                ->withCount('products')
                ->orderByDesc('products_count')
                ->limit(5)
                ->get(['id', 'name'])
                ->filter(fn (Category $category): bool => $category->products_count > 0)
                ->values()
            : collect();

        [$categoryGradient, $categoryLegend] = $this->categoryChart($categoryBreakdown);
        [$activity, $chartPoints, $areaPoints] = $this->activityChart($hasProducts, $hasUsers);

        return [
            'stats' => [
                ['label' => "Today's Total Order", 'value' => number_format((int) ($orderMetrics?->todays_total_orders ?? 0)), 'icon' => 'heroicon-o-shopping-cart', 'tone' => 'indigo'],
                ['label' => 'Pending Order', 'value' => number_format((int) ($orderMetrics?->pending_orders ?? 0)), 'icon' => 'heroicon-o-archive-box', 'tone' => 'violet'],
                ['label' => 'Confirmed Order', 'value' => number_format((int) ($orderMetrics?->confirmed_orders ?? 0)), 'icon' => 'heroicon-o-check-circle', 'tone' => 'sky'],
                ['label' => 'Total Order', 'value' => number_format((int) ($orderMetrics?->total_orders ?? 0)), 'icon' => 'heroicon-o-clipboard-document-list', 'tone' => 'amber'],

                ['label' => "Today's New Product", 'value' => number_format((int) ($productMetrics?->todays_new_products ?? 0)), 'icon' => 'heroicon-o-plus-circle', 'tone' => 'indigo'],
                ['label' => 'New Products', 'value' => number_format((int) ($productMetrics?->new_products ?? 0)), 'icon' => 'heroicon-o-sparkles', 'tone' => 'violet'],
                ['label' => 'Pending Products', 'value' => number_format((int) ($productMetrics?->pending_products ?? 0)), 'icon' => 'heroicon-o-exclamation-triangle', 'tone' => 'sky'],
                ['label' => 'All Products', 'value' => number_format((int) ($productMetrics?->all_products ?? 0)), 'icon' => 'heroicon-o-shopping-bag', 'tone' => 'amber'],

                ['label' => "Today's New Vendor Request", 'value' => number_format((int) ($vendorMetrics?->todays_new_vendor_requests ?? 0)), 'icon' => 'heroicon-o-document-check', 'tone' => 'indigo'],
                ['label' => 'Pending Vendor', 'value' => number_format((int) ($vendorMetrics?->pending_vendors ?? 0)), 'icon' => 'heroicon-o-inbox', 'tone' => 'violet'],
                ['label' => 'Approved Vendor', 'value' => number_format((int) ($vendorMetrics?->approved_vendors ?? 0)), 'icon' => 'heroicon-o-shield-check', 'tone' => 'sky'],
                ['label' => 'Total Vendor', 'value' => number_format((int) ($vendorMetrics?->total_vendors ?? 0)), 'icon' => 'heroicon-o-building-storefront', 'tone' => 'amber'],

                ['label' => "Today's Vendor New Product", 'value' => number_format((int) ($vendorProductMetrics?->todays_new_products ?? 0)), 'icon' => 'heroicon-o-plus-circle', 'tone' => 'indigo'],
                ['label' => 'Vendor New Products', 'value' => number_format((int) ($vendorProductMetrics?->new_products ?? 0)), 'icon' => 'heroicon-o-sparkles', 'tone' => 'violet'],
                ['label' => 'Vendor Pending Products', 'value' => number_format((int) ($vendorProductMetrics?->pending_products ?? 0)), 'icon' => 'heroicon-o-exclamation-triangle', 'tone' => 'sky'],
                ['label' => 'Vendor All Products', 'value' => number_format((int) ($vendorProductMetrics?->all_products ?? 0)), 'icon' => 'heroicon-o-cube', 'tone' => 'amber'],

                ['label' => "Today's New Customer", 'value' => number_format((int) ($customerMetrics?->todays_new_customers ?? 0)), 'icon' => 'heroicon-o-user-plus', 'tone' => 'indigo'],
                ['label' => 'Active Customers', 'value' => number_format((int) ($customerMetrics?->active_customers ?? 0)), 'icon' => 'heroicon-o-check-badge', 'tone' => 'violet'],
                ['label' => 'Inactive Customers', 'value' => number_format((int) ($customerMetrics?->inactive_customers ?? 0)), 'icon' => 'heroicon-o-x-circle', 'tone' => 'sky'],
                ['label' => 'Total Customers', 'value' => number_format((int) ($customerMetrics?->total_customers ?? 0)), 'icon' => 'heroicon-o-user-group', 'tone' => 'amber'],
            ],
            'recentProducts' => $recentProducts,
            'recentCustomers' => $recentCustomers,
            'categoryGradient' => $categoryGradient,
            'categoryLegend' => $categoryLegend,
            'categoryTotal' => (int) $categoryBreakdown->sum('products_count'),
            'activity' => $activity,
            'chartPoints' => $chartPoints,
            'areaPoints' => $areaPoints,
        ];
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array{0: string, 1: array<int, array<string, mixed>>}
     */
    private function categoryChart(Collection $categories): array
    {
        $colors = ['#4f46e5', '#7c3aed', '#0ea5e9', '#f59e0b', '#10b981'];
        $total = (int) $categories->sum('products_count');

        if ($total === 0) {
            return ['#e2e8f0 0% 100%', []];
        }

        $offset = 0.0;
        $segments = [];
        $legend = [];

        foreach ($categories as $index => $category) {
            $percentage = ($category->products_count / $total) * 100;
            $end = $offset + $percentage;
            $color = $colors[$index % count($colors)];

            $segments[] = sprintf('%s %.2f%% %.2f%%', $color, $offset, $end);
            $legend[] = [
                'name' => $category->name,
                'count' => $category->products_count,
                'percentage' => round($percentage),
                'color' => $color,
            ];

            $offset = $end;
        }

        return [implode(', ', $segments), $legend];
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: string, 2: string} */
    private function activityChart(bool $hasProducts, bool $hasUsers): array
    {
        $months = collect(range(5, 0))->map(fn (int $monthsAgo) => now()->startOfMonth()->subMonths($monthsAgo));
        $firstMonth = $months->first();

        $products = $hasProducts
            ? Product::query()->where('created_at', '>=', $firstMonth)->get(['created_at'])->countBy(fn (Product $product): string => $product->created_at->format('Y-m'))
            : collect();
        $customers = $hasUsers
            ? User::query()
                ->where('role', UserRole::Customer->value)
                ->where('created_at', '>=', $firstMonth)
                ->get(['created_at'])
                ->countBy(fn (User $user): string => $user->created_at->format('Y-m'))
            : collect();

        $activity = $months->values()->map(function ($month) use ($products, $customers): array {
            $key = $month->format('Y-m');

            return [
                'label' => $month->format('M'),
                'value' => (int) ($products->get($key, 0) + $customers->get($key, 0)),
            ];
        })->all();

        $maximum = max(1, ...array_column($activity, 'value'));
        $lastIndex = max(1, count($activity) - 1);
        $points = [];

        foreach ($activity as $index => $item) {
            $x = ($index / $lastIndex) * 100;
            $y = 88 - (($item['value'] / $maximum) * 68);
            $points[] = sprintf('%.2f,%.2f', $x, $y);
        }

        $chartPoints = implode(' ', $points);

        return [$activity, $chartPoints, '0,94 '.$chartPoints.' 100,94'];
    }
}
