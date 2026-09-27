<x-filament-panels::page>
    <div class="tisilo-dashboard tisilo-seller-dashboard">
        <section class="tisilo-welcome-card tisilo-seller-welcome">
            <div class="tisilo-welcome-copy">
                <span class="tisilo-eyebrow">TISILO VENDOR CENTER</span>
                <h2>{{ $vendor?->name ?? 'আপনার শপ' }}</h2>
                <p>স্বাগতম, {{ $seller?->name }}। আজকের অর্ডার, পণ্য, স্টক ও বিক্রির অবস্থা এক জায়গা থেকে দেখুন।</p>
            </div>
            <div class="tisilo-welcome-status">
                <span>Shop status</span>
                <strong>
                    <i class="tisilo-live-dot tisilo-live-dot-{{ $vendor?->status?->value ?? 'pending' }}"></i>
                    {{ $vendor?->status?->label() ?? 'Pending Review' }}
                </strong>
                <small>{{ now()->format('d M Y') }}</small>
            </div>
        </section>

        @if (! $vendor || $vendor->status?->value !== 'active')
            <section class="tisilo-seller-notice">
                <x-filament::icon icon="heroicon-o-information-circle" />
                <div>
                    <strong>{{ $vendor ? 'আপনার শপটি যাচাইাধীন আছে' : 'আপনার শপ প্রোফাইল সম্পূর্ণ করুন' }}</strong>
                    <p>শপ অনুমোদিত হলে পণ্য প্রকাশ, অর্ডার প্রসেস এবং পেআউট সুবিধা সক্রিয় হবে।</p>
                </div>
                <a href="{{ url('/seller/profile') }}">প্রোফাইল দেখুন</a>
            </section>
        @endif

        <section class="tisilo-stat-grid" aria-label="Vendor statistics">
            @foreach ($stats as $stat)
                <article class="tisilo-stat-card">
                    <div class="tisilo-stat-icon tisilo-stat-icon-{{ $stat['tone'] }}">
                        <x-filament::icon :icon="$stat['icon']" />
                    </div>
                    <div>
                        <span>{{ $stat['label'] }}</span>
                        <strong>{{ $stat['value'] }}</strong>
                        <small class="tisilo-stat-meta">{{ $stat['meta'] }}</small>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="tisilo-dashboard-grid tisilo-seller-analytics">
            <article class="tisilo-panel tisilo-activity-panel">
                <div class="tisilo-panel-heading">
                    <div>
                        <span>LAST SIX MONTHS</span>
                        <h3>Delivered Sales Trend</h3>
                    </div>
                    <span class="tisilo-live-pill"><i></i> Live data</span>
                </div>

                <div class="tisilo-chart-wrap">
                    <div class="tisilo-chart-grid"></div>
                    <svg class="tisilo-chart-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-label="Last six months delivered sales chart">
                        <defs>
                            <linearGradient id="sellerSalesArea" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#7c3aed" stop-opacity=".28" />
                                <stop offset="100%" stop-color="#7c3aed" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <polygon points="{{ $areaPoints }}" fill="url(#sellerSalesArea)" />
                        <polyline points="{{ $chartPoints }}" fill="none" stroke="#6d28d9" stroke-width="1.5" vector-effect="non-scaling-stroke" />
                    </svg>
                </div>

                <div class="tisilo-chart-labels">
                    @foreach ($salesActivity as $item)
                        <div><strong>{{ $item['display'] }}</strong><span>{{ $item['label'] }}</span></div>
                    @endforeach
                </div>
            </article>

            <article class="tisilo-panel tisilo-order-overview">
                <div class="tisilo-panel-heading">
                    <div>
                        <span>ORDER HEALTH</span>
                        <h3>Order Overview</h3>
                    </div>
                </div>

                <div class="tisilo-order-donut-wrap">
                    <div class="tisilo-order-donut" style="--order-gradient: conic-gradient({{ $statusGradient }});">
                        <div><strong>{{ number_format($totalOrders) }}</strong><span>Total</span></div>
                    </div>
                    <div class="tisilo-order-legend">
                        @forelse ($orderStatusLegend as $item)
                            <div><i style="--legend-color: {{ $item['color'] }}"></i><span>{{ $item['label'] }}</span><strong>{{ $item['value'] }}</strong></div>
                        @empty
                            <p>এখনো কোনো অর্ডার নেই।</p>
                        @endforelse
                    </div>
                </div>

                <div class="tisilo-earning-card">
                    <span>Estimated Earnings</span>
                    <strong>৳{{ number_format($estimatedEarnings, 2) }}</strong>
                    <small>Gross ৳{{ number_format($grossSales, 2) }} থেকে {{ number_format($commissionRate, 2) }}% কমিশন বাদে</small>
                </div>
            </article>
        </section>

        <section class="tisilo-dashboard-grid tisilo-seller-lower">
            <article class="tisilo-panel">
                <div class="tisilo-panel-heading">
                    <div>
                        <span>SALES</span>
                        <h3>Recent Orders</h3>
                    </div>
                    @if ($recentOrders->isNotEmpty())
                        <span class="tisilo-alert-pill">{{ $recentOrders->count() }} latest</span>
                    @endif
                </div>

                <div class="tisilo-table-wrap">
                    <table class="tisilo-table">
                        <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($recentOrders as $order)
                                <tr>
                                    <td><strong>{{ $order->order_number }}</strong><small>{{ $order->placed_at?->format('d M, h:i A') }}</small></td>
                                    <td>{{ $order->customer_name }}</td>
                                    <td>{{ number_format($order->items->sum('quantity')) }}</td>
                                    <td>৳{{ number_format((float) $order->items->sum('total_amount'), 2) }}</td>
                                    <td><span class="tisilo-status tisilo-status-{{ $order->status->value }}">{{ $order->status->label() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="tisilo-empty-cell">এখনো কোনো অর্ডার পাওয়া যায়নি।</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="tisilo-panel">
                <div class="tisilo-panel-heading">
                    <div>
                        <span>PERFORMANCE</span>
                        <h3>Top Products</h3>
                    </div>
                </div>

                <div class="tisilo-product-rankings">
                    @forelse ($topProducts as $index => $product)
                        <div class="tisilo-product-rank">
                            <span class="tisilo-rank">{{ $index + 1 }}</span>
                            <div><strong>{{ $product->product_name }}</strong><small>{{ number_format((int) $product->sold_quantity) }} units sold</small></div>
                            <b>৳{{ number_format((float) $product->revenue, 2) }}</b>
                        </div>
                    @empty
                        <div class="tisilo-empty-cell">ডেলিভারি সম্পন্ন হলে সেরা পণ্যগুলো এখানে দেখা যাবে।</div>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="tisilo-panel">
            <div class="tisilo-panel-heading">
                <div><span>SHORTCUTS</span><h3>Quick Actions</h3></div>
            </div>
            <div class="tisilo-quick-actions">
                @foreach ($quickActions as $action)
                    <a href="{{ $action['url'] }}" @if ($action['external'] ?? false) target="_blank" rel="noopener" @endif>
                        <x-filament::icon :icon="$action['icon']" />
                        <span>{{ $action['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
