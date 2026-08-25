<x-filament-panels::page>
    <div class="tisilo-dashboard">
        <section class="tisilo-welcome-card">
            <div>
                <p class="tisilo-eyebrow">Tisilo enterprise marketplace</p>
                <h2>Congratulations {{ auth()->user()->name ?? 'Tisilo Team' }} 🎉</h2>
                <p>Your marketplace control center is ready. Keep building a trusted shopping experience.</p>
            </div>
            <div class="tisilo-welcome-status">
                <span>System status</span>
                <strong><i></i> Live &amp; secure</strong>
                <small>{{ now()->format('d M Y') }}</small>
            </div>
        </section>

        <section class="tisilo-stat-grid" aria-label="Marketplace statistics">
            @foreach ($stats as $stat)
                <article class="tisilo-stat-card">
                    <div class="tisilo-stat-icon tisilo-tone-{{ $stat['tone'] }}">
                        <x-filament::icon :icon="$stat['icon']" />
                    </div>
                    <div>
                        <p>{{ $stat['label'] }}</p>
                        <strong>{{ $stat['value'] }}</strong>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="tisilo-analytics-grid">
            <article class="tisilo-panel tisilo-category-panel">
                <div class="tisilo-panel-heading">
                    <div>
                        <p class="tisilo-eyebrow">Catalog mix</p>
                        <h3>Products By Category</h3>
                    </div>
                    <a href="{{ url('/admin/categories') }}">Manage</a>
                </div>

                <div class="tisilo-donut-wrap">
                    <div class="tisilo-donut" style="--tisilo-donut: conic-gradient({{ $categoryGradient }});">
                        <div>
                            <span>Total products</span>
                            <strong>{{ number_format($categoryTotal) }}</strong>
                        </div>
                    </div>

                    <div class="tisilo-chart-legend">
                        @forelse ($categoryLegend as $item)
                            <div>
                                <span><i style="background: {{ $item['color'] }}"></i>{{ $item['name'] }}</span>
                                <strong>{{ $item['percentage'] }}%</strong>
                            </div>
                        @empty
                            <p>No category data yet. Add products to activate this chart.</p>
                        @endforelse
                    </div>
                </div>
            </article>

            <article class="tisilo-panel tisilo-activity-panel">
                <div class="tisilo-panel-heading">
                    <div>
                        <p class="tisilo-eyebrow">Last six months</p>
                        <h3>Marketplace Activity</h3>
                    </div>
                    <span class="tisilo-live-pill"><i></i> Live data</span>
                </div>

                <div class="tisilo-chart-canvas">
                    <div class="tisilo-chart-grid" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                    <svg viewBox="0 0 100 100" preserveAspectRatio="none" role="img" aria-label="Monthly products and customers activity">
                        <defs>
                            <linearGradient id="tisilo-chart-fill" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#6366f1" stop-opacity=".28" />
                                <stop offset="100%" stop-color="#6366f1" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <polygon points="{{ $areaPoints }}" fill="url(#tisilo-chart-fill)" />
                        <polyline points="{{ $chartPoints }}" fill="none" stroke="#4f46e5" stroke-width="2.2" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>

                <div class="tisilo-chart-labels">
                    @foreach ($activity as $item)
                        <span><strong>{{ $item['value'] }}</strong>{{ $item['label'] }}</span>
                    @endforeach
                </div>
            </article>
        </section>

        <section class="tisilo-lower-grid">
            <article class="tisilo-panel tisilo-table-panel">
                <div class="tisilo-panel-heading">
                    <div>
                        <p class="tisilo-eyebrow">Catalog</p>
                        <h3>Recent Products</h3>
                    </div>
                    <a href="{{ url('/admin/products') }}">View all</a>
                </div>

                <div class="tisilo-table-scroll">
                    <table>
                        <thead><tr><th>Product</th><th>SKU</th><th>Status</th><th>Price</th></tr></thead>
                        <tbody>
                            @forelse ($recentProducts as $product)
                                <tr>
                                    <td><strong>{{ $product->name }}</strong><small>{{ $product->category?->name ?? 'Uncategorized' }}</small></td>
                                    <td>{{ $product->sku ?: '—' }}</td>
                                    <td><span class="tisilo-status tisilo-status-{{ $product->status }}">{{ str($product->status)->headline() }}</span></td>
                                    <td>৳{{ number_format((float) ($product->sale_price ?: $product->regular_price), 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="tisilo-empty">No products yet. Your newest products will appear here.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="tisilo-panel tisilo-customers-panel">
                <div class="tisilo-panel-heading">
                    <div>
                        <p class="tisilo-eyebrow">Community</p>
                        <h3>Recent Customers</h3>
                    </div>
                    <a href="{{ url('/admin/users') }}">View all</a>
                </div>

                <div class="tisilo-customer-list">
                    @forelse ($recentCustomers as $customer)
                        <div>
                            <span class="tisilo-avatar">{{ str($customer->name)->substr(0, 1)->upper() }}</span>
                            <p><strong>{{ $customer->name }}</strong><small>{{ $customer->phone ?: $customer->email }}</small></p>
                            <time>{{ $customer->created_at?->diffForHumans(short: true) }}</time>
                        </div>
                    @empty
                        <p class="tisilo-empty">New customers will appear here.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="tisilo-quick-actions">
            <a href="{{ url('/admin/products/create') }}"><x-filament::icon icon="heroicon-o-plus-circle" /><span><strong>Add product</strong><small>Create a new catalog item</small></span></a>
            <a href="{{ url('/admin/vendors') }}"><x-filament::icon icon="heroicon-o-building-storefront" /><span><strong>Manage vendors</strong><small>Review marketplace sellers</small></span></a>
            <a href="{{ url('/admin/inventory-stocks') }}"><x-filament::icon icon="heroicon-o-cube" /><span><strong>Check inventory</strong><small>Monitor stock availability</small></span></a>
        </section>
    </div>
</x-filament-panels::page>
