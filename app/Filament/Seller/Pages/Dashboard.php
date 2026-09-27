<?php

namespace App\Filament\Seller\Pages;

use App\Enums\OrderStatus;
use App\Enums\VendorListingStatus;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.seller.pages.dashboard';

    public function getTitle(): string|Htmlable
    {
        return 'Vendor Dashboard';
    }

    public function getSubheading(): ?string
    {
        return 'আপনার শপ, অর্ডার, পণ্য, স্টক ও আয়ের পূর্ণাঙ্গ চিত্র';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $seller = Filament::auth()->user();
        $vendor = $seller?->ownedVendors()->first() ?? $seller?->vendors()->first();

        if (! $vendor || ! Schema::hasTable('order_items')) {
            return $this->emptyDashboardData($seller, $vendor);
        }

        $orderQuery = Order::query()
            ->whereHas('items', fn ($query) => $query->where('vendor_id', $vendor->getKey()));

        $totalOrders = (clone $orderQuery)->count();
        $ordersToProcess = (clone $orderQuery)
            ->whereIn('status', [
                OrderStatus::Pending->value,
                OrderStatus::Confirmed->value,
                OrderStatus::Processing->value,
            ])
            ->count();

        $deliveredItems = OrderItem::query()
            ->where('vendor_id', $vendor->getKey())
            ->whereHas('order', fn ($query) => $query->where('status', OrderStatus::Delivered->value));

        $grossSales = (float) (clone $deliveredItems)->sum('total_amount');
        $commissionRate = (float) ($vendor->commission_rate ?? 0);
        $estimatedEarnings = max(0, $grossSales - ($grossSales * $commissionRate / 100));

        $totalListings = Schema::hasTable('vendor_listings')
            ? $vendor->listings()->count()
            : 0;
        $activeListings = Schema::hasTable('vendor_listings')
            ? $vendor->listings()->where('status', VendorListingStatus::Approved->value)->count()
            : 0;
        $activeSkuCount = Schema::hasTable('vendor_listing_items')
            ? $vendor->listings()->withCount('items')->get()->sum('items_count')
            : 0;

        $availableStock = 0;
        $lowStockCount = 0;

        if (Schema::hasTable('inventory_stocks') && Schema::hasTable('vendor_listing_items')) {
            $stockQuery = InventoryStock::query()
                ->whereHas('item', fn ($query) => $query->where('vendor_id', $vendor->getKey()));

            $availableStock = (int) (clone $stockQuery)
                ->selectRaw('COALESCE(SUM(GREATEST(quantity - reserved_quantity, 0)), 0) as available_stock')
                ->value('available_stock');
            $lowStockCount = (clone $stockQuery)
                ->whereRaw('(quantity - reserved_quantity) <= reorder_point')
                ->count();
        }

        $recentOrders = (clone $orderQuery)
            ->with([
                'items' => fn ($query) => $query->where('vendor_id', $vendor->getKey()),
            ])
            ->latest('placed_at')
            ->limit(6)
            ->get();

        $topProducts = (clone $deliveredItems)
            ->selectRaw('product_name, SUM(quantity) as sold_quantity, SUM(total_amount) as revenue')
            ->groupBy('product_name')
            ->orderByDesc('sold_quantity')
            ->limit(5)
            ->get();

        [$salesActivity, $chartPoints, $areaPoints] = $this->salesChart($vendor);
        [$statusGradient, $orderStatusLegend] = $this->orderStatusChart($orderQuery);

        return [
            'seller' => $seller,
            'vendor' => $vendor,
            'stats' => [
                [
                    'label' => 'Total Orders',
                    'value' => number_format($totalOrders),
                    'meta' => number_format($ordersToProcess).' টি প্রসেস করতে হবে',
                    'icon' => 'heroicon-o-shopping-bag',
                    'tone' => 'purple',
                ],
                [
                    'label' => 'Delivered Sales',
                    'value' => '৳'.number_format($grossSales, 2),
                    'meta' => 'সফল ডেলিভারি থেকে',
                    'icon' => 'heroicon-o-banknotes',
                    'tone' => 'green',
                ],
                [
                    'label' => 'Active Products',
                    'value' => number_format($activeListings),
                    'meta' => number_format($totalListings).' listing · '.number_format($activeSkuCount).' SKU',
                    'icon' => 'heroicon-o-cube',
                    'tone' => 'blue',
                ],
                [
                    'label' => 'Available Stock',
                    'value' => number_format($availableStock),
                    'meta' => number_format($lowStockCount).' টি low stock',
                    'icon' => 'heroicon-o-archive-box',
                    'tone' => 'orange',
                ],
            ],
            'salesActivity' => $salesActivity,
            'chartPoints' => $chartPoints,
            'areaPoints' => $areaPoints,
            'statusGradient' => $statusGradient,
            'orderStatusLegend' => $orderStatusLegend,
            'recentOrders' => $recentOrders,
            'topProducts' => $topProducts,
            'totalOrders' => $totalOrders,
            'grossSales' => $grossSales,
            'commissionRate' => $commissionRate,
            'estimatedEarnings' => $estimatedEarnings,
        ];
    }

    /** @return array{0: Collection<int, array{label: string, value: float, display: string}>, 1: string, 2: string} */
    protected function salesChart(Vendor $vendor): array
    {
        $months = collect(range(5, 0))->map(fn (int $offset) => now()->startOfMonth()->subMonths($offset));
        $from = $months->first()->copy()->startOfMonth();

        $items = OrderItem::query()
            ->where('vendor_id', $vendor->getKey())
            ->whereHas('order', fn ($query) => $query
                ->where('status', OrderStatus::Delivered->value)
                ->where('placed_at', '>=', $from))
            ->with('order:id,placed_at')
            ->get()
            ->groupBy(fn (OrderItem $item) => $item->order?->placed_at?->format('Y-m'));

        $activity = $months->map(function ($month) use ($items): array {
            $value = (float) $items->get($month->format('Y-m'), collect())->sum('total_amount');

            return [
                'label' => $month->format('M'),
                'value' => $value,
                'display' => '৳'.number_format($value, 0),
            ];
        });

        $maximum = max(1, (float) $activity->max('value'));
        $points = $activity->values()->map(function (array $item, int $index) use ($activity, $maximum): string {
            $x = $activity->count() === 1 ? 50 : ($index / ($activity->count() - 1)) * 100;
            $y = 88 - (($item['value'] / $maximum) * 68);

            return round($x, 2).','.round($y, 2);
        })->implode(' ');

        return [$activity, $points, '0,100 '.$points.' 100,100'];
    }

    /** @return array{0: string, 1: Collection<int, array{label: string, value: int, color: string}>} */
    protected function orderStatusChart($orderQuery): array
    {
        $counts = (clone $orderQuery)
            ->get(['status'])
            ->countBy(fn (Order $order) => $order->status->value);

        $palette = [
            OrderStatus::Pending->value => '#f59e0b',
            OrderStatus::Confirmed->value => '#8b5cf6',
            OrderStatus::Processing->value => '#3b82f6',
            OrderStatus::Shipped->value => '#06b6d4',
            OrderStatus::Delivered->value => '#10b981',
            OrderStatus::Cancelled->value => '#ef4444',
            OrderStatus::Refunded->value => '#64748b',
        ];

        $total = max(1, (int) $counts->sum());
        $start = 0;
        $segments = [];
        $legend = collect();

        foreach (OrderStatus::cases() as $status) {
            $value = (int) ($counts[$status->value] ?? 0);

            if ($value < 1) {
                continue;
            }

            $end = $start + (($value / $total) * 100);
            $color = $palette[$status->value] ?? '#94a3b8';
            $segments[] = $color.' '.round($start, 2).'% '.round($end, 2).'%';
            $legend->push([
                'label' => $status->label(),
                'value' => $value,
                'color' => $color,
            ]);
            $start = $end;
        }

        return [
            $segments === [] ? '#e2e8f0 0% 100%' : implode(', ', $segments),
            $legend,
        ];
    }

    /** @return array<string, mixed> */
    protected function emptyDashboardData($seller, ?Vendor $vendor): array
    {
        return [
            'seller' => $seller,
            'vendor' => $vendor,
            'stats' => collect([
                ['label' => 'Total Orders', 'value' => '0', 'meta' => '0 টি প্রসেস করতে হবে', 'icon' => 'heroicon-o-shopping-bag', 'tone' => 'purple'],
                ['label' => 'Delivered Sales', 'value' => '৳0.00', 'meta' => 'সফল ডেলিভারি থেকে', 'icon' => 'heroicon-o-banknotes', 'tone' => 'green'],
                ['label' => 'Active Products', 'value' => '0', 'meta' => '0 listing · 0 SKU', 'icon' => 'heroicon-o-cube', 'tone' => 'blue'],
                ['label' => 'Available Stock', 'value' => '0', 'meta' => '0 টি low stock', 'icon' => 'heroicon-o-archive-box', 'tone' => 'orange'],
            ]),
            'salesActivity' => collect(['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'])->map(fn (string $month) => ['label' => $month, 'value' => 0, 'display' => '৳0']),
            'chartPoints' => '0,88 20,88 40,88 60,88 80,88 100,88',
            'areaPoints' => '0,100 0,88 20,88 40,88 60,88 80,88 100,88 100,100',
            'statusGradient' => '#e2e8f0 0% 100%',
            'orderStatusLegend' => collect(),
            'recentOrders' => collect(),
            'topProducts' => collect(),
            'totalOrders' => 0,
            'grossSales' => 0,
            'commissionRate' => (float) ($vendor?->commission_rate ?? 0),
            'estimatedEarnings' => 0,
        ];
    }
}
