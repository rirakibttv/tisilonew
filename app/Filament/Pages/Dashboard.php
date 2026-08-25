<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    public function getTitle(): string|Htmlable
    {
        return 'Hi! Welcome To Dashboard';
    }

    public function getSubheading(): ?string
    {
        return 'Home → Marketplace Dashboard';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $hasProducts = Schema::hasTable('products');
        $hasVendors = Schema::hasTable('vendors');
        $hasUsers = Schema::hasTable('users');
        $hasInventory = Schema::hasTable('inventory_stocks');
        $hasCategories = Schema::hasTable('categories');

        $productCount = $hasProducts ? Product::query()->count() : 0;
        $activeVendors = $hasVendors
            ? Vendor::query()->where('status', VendorStatus::Active->value)->count()
            : 0;
        $customerCount = $hasUsers
            ? User::query()->where('role', UserRole::Customer->value)->count()
            : 0;
        $stockUnits = $hasInventory ? (int) InventoryStock::query()->sum('quantity') : 0;

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
                ['label' => 'Total Products', 'value' => number_format($productCount), 'icon' => 'heroicon-o-shopping-bag', 'tone' => 'indigo'],
                ['label' => 'Active Vendors', 'value' => number_format($activeVendors), 'icon' => 'heroicon-o-building-storefront', 'tone' => 'violet'],
                ['label' => 'Total Customers', 'value' => number_format($customerCount), 'icon' => 'heroicon-o-user-group', 'tone' => 'sky'],
                ['label' => 'Stock Units', 'value' => number_format($stockUnits), 'icon' => 'heroicon-o-cube', 'tone' => 'amber'],
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
