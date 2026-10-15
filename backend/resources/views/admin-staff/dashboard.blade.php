@extends('layouts.dashboard')

@section('title', 'Admin Staff Operations Desk')
@section('role_title', 'Admin Staff')
@section('page_title', 'Operations & Moderation Support Desk')
@section('role_badge', 'ADMIN STAFF')
@section('role_badge_class', 'role-badge-admin-staff')

@section('sidebar_menu')
    <li class="nav-section-title">STAFF OPERATIONS</li>
    <li class="sidebar-nav-item">
        <a href="{{ route('admin.staff.dashboard') }}" class="sidebar-nav-link active">
            <span class="nav-link-icon">📋</span>
            <span>Staff Desk Overview</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#catalog-moderation" class="sidebar-nav-link">
            <span class="nav-link-icon">🔍</span>
            <span>Product Moderation &amp; Freshness</span>
            <span class="nav-link-badge">{{ count($products) }}</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#order-triage" class="sidebar-nav-link">
            <span class="nav-link-icon">📦</span>
            <span>Order Dispatch Triage</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#counter-monitor" class="sidebar-nav-link">
            <span class="nav-link-icon">🏬</span>
            <span>Counter Inspection Monitor</span>
        </a>
    </li>

    <li class="nav-section-title">SUPPORT &amp; LOGS</li>
    <li class="sidebar-nav-item">
        <a href="#dispute-queue" class="sidebar-nav-link">
            <span class="nav-link-icon">💬</span>
            <span>Customer Dispute Triage</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#activity-log" class="sidebar-nav-link">
            <span class="nav-link-icon">📜</span>
            <span>Platform Operational Logs</span>
        </a>
    </li>
@endsection

@section('content')
    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">📋</span>
                <span>Operations, Moderation &amp; Support Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Supervise active order dispatches, moderate vendor catalog listings for 7-day freshness, and assist customer inquiries.
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <span class="dash-badge dash-badge-info" style="padding: 6px 12px; font-size: 12px;">
                Operational Support Clearance (Non-Treasury)
            </span>
        </div>
    </div>

    <!-- 4 KPI Metrics -->
    <div class="stat-grid-4">
        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Catalog Items Monitored</span>
                <span class="stat-card-value">{{ $metrics['pending_reviews'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">7-Day Freshness Monitored</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">🔍</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">Orders in Fulfillment</span>
                <span class="stat-card-value">{{ $metrics['orders_triage'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">Processing &amp; Ready</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">📦</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">In-Shop Counter Check-ins</span>
                <span class="stat-card-value">{{ $metrics['counter_inspections'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Verified by Plaza Staff</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">🏬</div>
        </div>

        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Fleet Couriers</span>
                <span class="stat-card-value">{{ $metrics['active_riders'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">Online in Uyo LGA</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">🏍️</div>
        </div>
    </div>

    <!-- Panel 1: Product Catalog Moderation & 7-Day Freshness Verification -->
    <div class="dash-card" id="catalog-moderation">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>🔍</span>
                <div>
                    <div>Product Catalog Moderation &amp; 7-Day Freshness Queue</div>
                    <span class="dash-card-subtitle">Verify merchant catalog submissions and inspect inventory approaching the 7-day auto-stale delisting boundary.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-purple">Catalog Integrity</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Product Details</th>
                        <th>Category</th>
                        <th>Merchant Plaza Branch</th>
                        <th>Unit Price</th>
                        <th>Freshness Status</th>
                        <th>Staff Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td>
                                <div style="font-weight: 700;">{{ $product->name }}</div>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">SKU: VM-PRD-{{ $product->id }}</div>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ $product->category->name ?? 'Electronics' }}</span>
                            </td>
                            <td>{{ $product->shop->name ?? 'Victor Tech Uyo Flagship' }}</td>
                            <td><strong>&#8358;{{ number_format($product->unit_price, 2) }}</strong></td>
                            <td>
                                <span class="dash-badge dash-badge-success">
                                    ✓ Fresh Listing (Active)
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn-dash btn-dash-outline" onclick="alert('Product #{{ $product->id }} verified and approved for storefront!')">
                                    <span>✓ Verify &amp; Pass</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px; color: var(--adm-text-muted);">
                                No products in moderation queue.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Panel 2: Order Fulfillment Oversight & Triage -->
    <div class="dash-card" id="order-triage">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>📦</span>
                <div>
                    <div>Order Dispatch Triage Desk</div>
                    <span class="dash-card-subtitle">Active customer transactions monitored for prompt preparation and directional handover.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-gold">Operational Flow</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer Contact</th>
                        <th>Branch Plaza</th>
                        <th>Total Amount</th>
                        <th>Fulfillment Stage</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOrders as $order)
                        <tr>
                            <td style="font-family: monospace; font-weight: 700;">{{ $order->order_number }}</td>
                            <td>
                                <div style="font-weight: 600;">{{ $order->customer->name ?? 'Customer' }}</div>
                                <div style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $order->customer->phone ?? 'N/A' }}</div>
                            </td>
                            <td>{{ $order->shop->name ?? 'Victor Tech Plaza' }}</td>
                            <td>&#8358;{{ number_format($order->total_amount, 2) }}</td>
                            <td>
                                <span class="dash-badge dash-badge-purple">
                                    {{ ucfirst(str_replace('_', ' ', $order->fulfillment_type)) }}
                                </span>
                            </td>
                            <td>
                                @if($order->order_status === 'delivered')
                                    <span class="dash-badge dash-badge-success">Delivered</span>
                                @elseif($order->order_status === 'out_for_delivery')
                                    <span class="dash-badge dash-badge-info">In Transit</span>
                                @else
                                    <span class="dash-badge dash-badge-warning">{{ ucfirst($order->order_status) }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px; color: var(--adm-text-muted);">No orders in triage queue.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Panel 3: Counter Inspection Activity Feed -->
    <div class="dash-card" id="counter-monitor">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>🏬</span>
                <div>
                    <div>Branch Counter Inspection Monitor</div>
                    <span class="dash-card-subtitle">Real-time record of customer physical examinations at Uyo Plaza counters.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-emerald">Live Branch Feed</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Reservation Code</th>
                        <th>Product Inspected</th>
                        <th>Customer</th>
                        <th>Plaza Branch</th>
                        <th>Inspection Result</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($counterInspections as $res)
                        <tr>
                            <td><strong style="font-family: monospace;">{{ $res->reservation_code }}</strong></td>
                            <td>{{ $res->product->name ?? 'Verified Item' }}</td>
                            <td>{{ $res->customer->name ?? 'Customer' }}</td>
                            <td>{{ $res->shop->name ?? 'Uyo Flagship Plaza' }}</td>
                            <td>
                                @if($res->status === 'inspected')
                                    <span class="dash-badge dash-badge-success">Passed Inspection ✓</span>
                                @else
                                    <span class="dash-badge dash-badge-warning">Awaiting Inspection ⏳</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px; color: var(--adm-text-muted);">No counter inspection logs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
