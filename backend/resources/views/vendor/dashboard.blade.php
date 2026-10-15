@extends('layouts.dashboard')

@section('title', 'Merchant Business Suite')
@section('role_title', 'Vendor Merchant')
@section('page_title', 'Merchant Commerce Suite')
@section('role_badge', 'VENDOR MERCHANT')
@section('role_badge_class', 'role-badge-vendor')

@section('sidebar_menu')
    <li class="nav-section-title">MERCHANT COMMERCE</li>
    <li class="sidebar-nav-item">
        <a href="{{ route('vendor.dashboard') }}" class="sidebar-nav-link active">
            <span class="nav-link-icon">📊</span>
            <span>Merchant Overview</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#products-catalog" class="sidebar-nav-link">
            <span class="nav-link-icon">🏷️</span>
            <span>Products &amp; Freshness</span>
            <span class="nav-link-badge">{{ count($products) }}</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#shop-orders" class="sidebar-nav-link">
            <span class="nav-link-icon">🛍️</span>
            <span>Customer Orders</span>
            <span class="nav-link-badge">{{ count($orders) }}</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#reservations" class="sidebar-nav-link">
            <span class="nav-link-icon">📝</span>
            <span>Pickup Reservations</span>
        </a>
    </li>

    <li class="nav-section-title">OUTLETS &amp; SETTLEMENT</li>
    <li class="sidebar-nav-item">
        <a href="#shop-branches" class="sidebar-nav-link">
            <span class="nav-link-icon">🏬</span>
            <span>Physical Plaza Outlets</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#settlement-account" class="sidebar-nav-link">
            <span class="nav-link-icon">💳</span>
            <span>90% Payout Bank Account</span>
        </a>
    </li>
@endsection

@section('content')
    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏪</span>
                <span>{{ $vendor->business_name ?? 'Victor Tech Innovations' }}</span>
            </h1>
            <p class="page-header-subtitle">
                Accredited Merchant Portal &bull; Flagship: {{ $primaryShop->name ?? 'Victor Tech Uyo Plaza' }} &bull; CAC: {{ $vendor->cac_number ?? 'RC-987654' }}
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <span class="dash-badge dash-badge-success" style="padding: 6px 12px; font-size: 12px;">
                ✓ KYC VERIFIED (NIN: {{ $vendor->nin_number ?? '12345678901' }})
            </span>
            <span class="dash-badge dash-badge-purple" style="padding: 6px 12px; font-size: 12px;">
                🛡️ 48H BANK COOLDOWN ACTIVE
            </span>
        </div>
    </div>

    <!-- 4 KPI Metrics -->
    <div class="stat-grid-4">
        <div class="stat-card stat-card-border-gold">
            <div class="stat-card-info">
                <span class="stat-card-label">Gross Merchant Sales</span>
                <span class="stat-card-value">&#8358;{{ number_format($vendorMetrics['gross_sales'], 2) }}</span>
                <span class="stat-card-subtext" style="color: #B45309;">100% Customer Paid</span>
            </div>
            <div class="stat-card-icon-box icon-box-gold">💰</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">90% Net Disbursed</span>
                <span class="stat-card-value">&#8358;{{ number_format($vendorMetrics['settled_amount'], 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Direct to Zenith Bank</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">🏦</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">Pending Escrow Settlement</span>
                <span class="stat-card-value">&#8358;{{ number_format($vendorMetrics['pending_settlement'], 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">In 24h Return Buffer</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">⏳</div>
        </div>

        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Verified Active Listings</span>
                <span class="stat-card-value">{{ $vendorMetrics['total_products'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">7-Day Freshness Monitored</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">🏷️</div>
        </div>
    </div>

    <!-- Panel 1: Products Catalog & 7-Day Freshness Countdown -->
    <div class="dash-card" id="products-catalog">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>🏷️</span>
                <div>
                    <div>My Products &amp; 7-Day Freshness Status</div>
                    <span class="dash-card-subtitle">Items must be re-verified or restocked every 7 days to preserve certified freshness on Victorious MARKET.</span>
                </div>
            </div>
            <button type="button" class="btn-dash btn-dash-primary" onclick="alert('Product addition modal: Fill in item name, price, and shop counter allocation.')">
                <span>+ Add New Product</span>
            </button>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Retail Price</th>
                        <th>Your 90% Cut</th>
                        <th>Stock Qty</th>
                        <th>Freshness Lifecycle</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td>
                                <strong style="color: var(--adm-text);">{{ $product->name }}</strong>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">SKU: VM-{{ $product->id }}</div>
                            </td>
                            <td><span class="dash-badge dash-badge-info">{{ $product->category->name ?? 'Electronics' }}</span></td>
                            <td>&#8358;{{ number_format($product->unit_price, 2) }}</td>
                            <td>
                                <strong style="color: var(--adm-success); font-size: 13.5px;">
                                    &#8358;{{ number_format($product->unit_price * 0.9, 2) }}
                                </strong>
                            </td>
                            <td>
                                <span class="dash-badge {{ $product->stock_quantity > 5 ? 'dash-badge-success' : 'dash-badge-danger' }}">
                                    {{ $product->stock_quantity }} units
                                </span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-purple">
                                    ● Certified Fresh (5 days left)
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn-dash btn-dash-outline" onclick="alert('Freshness timestamp extended by 7 days for {{ addslashes($product->name) }}!')">
                                    <span>↻ Refresh (7 Days)</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px; color: var(--adm-text-muted);">
                                No products found. Add your first item above!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Panel 2: Customer Orders for This Merchant -->
    <div class="dash-card" id="shop-orders">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>🛍️</span>
                <div>
                    <div>Orders Placed for My Items</div>
                    <span class="dash-card-subtitle">Escrow-backed orders awaiting counter pickup or directional logistics dispatch.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-gold">Live Storefront Orders</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Gross Total</th>
                        <th>Your 90% Share</th>
                        <th>Fulfillment</th>
                        <th>Settlement Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td style="font-family: monospace; font-weight: 700;">{{ $order->order_number }}</td>
                            <td>
                                <div>{{ $order->customer->name ?? 'Shopper' }}</div>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">{{ $order->customer->phone ?? 'N/A' }}</div>
                            </td>
                            <td>&#8358;{{ number_format($order->total_amount, 2) }}</td>
                            <td>
                                <strong style="color: var(--adm-success); font-size: 14px;">
                                    &#8358;{{ number_format($order->vendor_earning_90, 2) }}
                                </strong>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-purple">
                                    {{ ucfirst(str_replace('_', ' ', $order->fulfillment_type)) }}
                                </span>
                            </td>
                            <td>
                                @if($order->vendor_settlement_status === 'settled')
                                    <span class="dash-badge dash-badge-success">Settled to Bank ✓</span>
                                @elseif($order->vendor_settlement_status === 'held')
                                    <span class="dash-badge dash-badge-warning">In 24h Buffer ⏳</span>
                                @else
                                    <span class="dash-badge dash-badge-info">{{ ucfirst($order->vendor_settlement_status) }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px; color: var(--adm-text-muted);">No orders found for this merchant.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Panel 3 & 4: Outlet Details & Settlement Account -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px;">
        <!-- Outlet Details -->
        <div class="dash-card" id="shop-branches">
            <div class="dash-card-header">
                <div class="dash-card-title">
                    <span>🏬</span>
                    <span>Primary Shop Outlet</span>
                </div>
                <span class="dash-badge dash-badge-success">Inspection Counter Active</span>
            </div>
            <div class="dash-card-body">
                <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 6px;">{{ $primaryShop->name ?? 'Victor Tech Uyo Flagship Plaza' }}</h3>
                <p style="color: var(--adm-text-muted); font-size: 13px; margin-bottom: 14px;">
                    📍 {{ $primaryShop->address ?? '42 Ikot Ekpene Road, Uyo, Akwa Ibom' }}
                </p>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 12.5px;">
                    <div style="background: #F8FAFC; padding: 10px; border-radius: 6px;">
                        <span style="color: var(--adm-text-muted); display: block;">Counter Staff:</span>
                        <strong>Aniefiok Akpan</strong>
                    </div>
                    <div style="background: #F8FAFC; padding: 10px; border-radius: 6px;">
                        <span style="color: var(--adm-text-muted); display: block;">Operating Hours:</span>
                        <strong>08:30 AM – 06:00 PM</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settlement Bank Account -->
        <div class="dash-card" id="settlement-account">
            <div class="dash-card-header">
                <div class="dash-card-title">
                    <span>💳</span>
                    <span>90% Payout Bank Account</span>
                </div>
                <span class="dash-badge dash-badge-purple">48h Invariant Locked</span>
            </div>
            <div class="dash-card-body">
                <div style="background: linear-gradient(135deg, #0B0F19 0%, #1A1035 100%); color: #FFF; padding: 18px 20px; border-radius: 10px; border: 1.5px solid var(--adm-gold); margin-bottom: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <span style="font-size: 13px; font-weight: 700; color: var(--adm-gold); text-transform: uppercase;">{{ $vendor->bank_name ?? 'Zenith Bank' }}</span>
                        <span style="font-size: 10px; background: rgba(255, 215, 0, 0.2); color: var(--adm-gold); padding: 2px 8px; border-radius: 4px; font-weight: 700;">VERIFIED</span>
                    </div>
                    <div style="font-family: monospace; font-size: 20px; font-weight: 700; letter-spacing: 2px; margin-bottom: 8px;">
                        {{ $vendor->account_number ?? '1012345678' }}
                    </div>
                    <div style="font-size: 12px; color: #CBD5E1;">
                        {{ $vendor->account_name ?? 'Victor Tech Innovations' }}
                    </div>
                </div>
                <p style="font-size: 11.5px; color: var(--adm-text-muted); line-height: 1.4;">
                    🛡️ <strong>48-Hour Bank Cooldown Rule:</strong> Any bank account modification triggers a mandatory 48-hour freeze on disbursements to prevent hijacked payouts. Last updated: 5 days ago (Safe).
                </p>
            </div>
        </div>
    </div>
@endsection
