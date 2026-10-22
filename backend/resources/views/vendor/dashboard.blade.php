@extends('layouts.dashboard')

@section('title', 'Merchant Commerce Suite — Vendor')
@section('role_title', 'Merchant Commerce Suite')
@section('page_title', 'Merchant Command Overview')
@section('role_badge', 'VENDOR')
@section('role_badge_class', 'role-badge-vendor')

@section('sidebar_menu')
    @include('vendor.partials._sidebar')
@endsection

@section('content')
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏪</span>
                <span>Merchant Commerce Suite</span>
            </h1>
            <p class="page-header-subtitle">
                Supervise your catalog freshness, customer orders, physical plaza branches, and escrow settlements.
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <span class="dash-badge dash-badge-success" style="padding: 6px 12px; font-size: 12px;">
                ● Active Verified Merchant
            </span>
        </div>
    </div>

    @include('vendor.partials._shop_switcher')

    <!-- 4 KPI Cards -->
    <div class="stat-grid-4">
        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Gross Merchant Sales</span>
                <span class="stat-card-value">₦{{ number_format($vendorMetrics['gross_sales'], 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">Total Escrow Sales</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">📊</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Merchant Net Earnings</span>
                <span class="stat-card-value">₦{{ number_format($vendorMetrics['net_earnings'], 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Direct Merchant Share</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">💰</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Catalog Products</span>
                <span class="stat-card-value">{{ $vendorMetrics['total_products'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">7-Day Certified Freshness</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">🏷️</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Orders</span>
                <span class="stat-card-value">{{ $vendorMetrics['active_orders'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">In Fulfillment</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">🛍️</div>
        </div>
    </div>

    <!-- Operational Launchpad -->
    <div style="margin-bottom: 28px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
            <h2 style="font-size: 15px; font-weight: 800; color: var(--adm-text); text-transform: uppercase; letter-spacing: 0.5px;">
                ⚡ Merchant Management Desks
            </h2>
            <span style="font-size: 12px; color: var(--adm-text-muted);">Dedicated Standalone Modules</span>
        </div>

        <div class="module-launchpad-grid">
            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(94, 23, 235, 0.1); color: var(--adm-primary);">🏷️</div>
                        <span class="dash-badge dash-badge-purple">{{ count($products) }} Products</span>
                    </div>
                    <div class="module-card-title">Products &amp; Freshness Catalog</div>
                    <div class="module-card-desc">Restock, add new items, and re-verify certified freshness every 7 days to preserve storefront visibility.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: #047857; font-weight: 700;">● Active Catalog</span>
                    <a href="{{ route('vendor.products.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Catalog &rarr;
                    </a>
                </div>
            </div>

            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--adm-warning);">🛍️</div>
                        <span class="dash-badge dash-badge-gold">{{ count($orders) }} Orders</span>
                    </div>
                    <div class="module-card-title">Customer Orders</div>
                    <div class="module-card-desc">Review incoming customer orders, manage processing packaging, and monitor counter rider handovers.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: var(--adm-text-muted);">Escrow Protected</span>
                    <a href="{{ route('vendor.orders.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Orders &rarr;
                    </a>
                </div>
            </div>

            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--adm-success);">📝</div>
                        <span class="dash-badge dash-badge-success">{{ count($reservations) }} Passes</span>
                    </div>
                    <div class="module-card-title">Pickup Reservations</div>
                    <div class="module-card-desc">Customer reservation passes awaiting walk-in inspection at your Plaza counter with zero upfront risk.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: #10B981; font-weight: 700;">● In-Shop Pass</span>
                    <a href="{{ route('vendor.reservations.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Reservations &rarr;
                    </a>
                </div>
            </div>

            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(2, 132, 199, 0.1); color: var(--adm-info);">🏬</div>
                        <span class="dash-badge dash-badge-info">{{ count($shops) }} Outlets</span>
                    </div>
                    <div class="module-card-title">Physical Plaza Outlets</div>
                    <div class="module-card-desc">Manage your commercial plaza branches, operating hours, and counter staff verification desks.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: var(--adm-text-muted);">Commercial Corridors</span>
                    <a href="{{ route('vendor.shops.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Outlets &rarr;
                    </a>
                </div>
            </div>

            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--adm-success);">💳</div>
                        <span class="dash-badge dash-badge-success">Bank Payout</span>
                    </div>
                    <div class="module-card-title">Merchant Payout Bank Account</div>
                    <div class="module-card-desc">View disbursed funds, escrow holds, and verified Nigerian bank details for automatic payouts.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: #059669; font-weight: 700;">● T+0 Settlement</span>
                    <a href="{{ route('vendor.payout.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Bank Account &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection