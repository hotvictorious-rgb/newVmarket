@extends('layouts.dashboard')

@section('title', 'Platform Escrow Orders & Fulfillment Ledger')
@section('role_title', 'Super Admin')
@section('page_title', 'Platform Escrow Orders')
@section('role_badge', 'SUPER ADMIN')
@section('role_badge_class', 'role-badge-super-admin')

@section('sidebar_menu')
    @include('admin.partials._sidebar')
@endsection

@section('content')
    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">📦</span>
                <span>Platform Escrow Orders &amp; Fulfillment Ledger</span>
            </h1>
            <p class="page-header-subtitle">
                Comprehensive platform order auditing: Monitor prepaid escrows, in-shop pickup sign-offs, and rider delivery OTP handshakes.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.dashboard') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Command Tower
            </a>
            <span class="dash-badge dash-badge-purple" style="padding: 6px 12px; font-size: 12px;">
                🔒 Sovereign Escrow Locked
            </span>
        </div>
    </div>

    <!-- 4 Order Metric Cards -->
    <div class="stat-grid-4" style="margin-bottom: 24px;">
        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Orders</span>
                <span class="stat-card-value">{{ $recentOrders->whereIn('order_status', ['pending', 'confirmed', 'processing', 'out_for_delivery'])->count() }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">In Fulfillment Pipeline</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">📦</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">In-Shop Pickups</span>
                <span class="stat-card-value">{{ $recentOrders->where('order_type', 'pickup')->count() }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Branch Inspection Pass</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">🏬</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Transit Deliveries</span>
                <span class="stat-card-value">{{ $recentOrders->where('order_type', '!=', 'pickup')->count() }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Dispatched via Riders</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">🚚</div>
        </div>

        <div class="stat-card stat-card-border-gold">
            <div class="stat-card-info">
                <span class="stat-card-label">Gross Escrow Value</span>
                <span class="stat-card-value">&#8358;{{ number_format((float)$recentOrders->sum('order_amount'), 2) }}</span>
                <span class="stat-card-subtext" style="color: #B45309;">Prepaid via Paystack</span>
            </div>
            <div class="stat-card-icon-box icon-box-gold">💰</div>
        </div>
    </div>

    <!-- Orders Ledger Card -->
    <div class="dash-card" id="recent-orders">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>📋</span>
                <div>
                    <div>Master Platform Orders Ledger</div>
                    <span class="dash-card-subtitle">Real-time trace of order journeys across Shopper &rarr; Vendor &rarr; Counter &rarr; Rider.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-info">Live Sync</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order Ref</th>
                        <th>Shopper</th>
                        <th>Merchant &amp; Branch</th>
                        <th>Fulfillment Mode</th>
                        <th>Amount</th>
                        <th>Order Status</th>
                        <th>Settlement Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOrders as $order)
                        <tr>
                            <td>
                                <strong style="font-family: monospace; font-size: 13.5px; color: var(--adm-primary);">{{ $order->order_number }}</strong>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">{{ $order->created_at ? $order->created_at->format('M d, H:i') : 'Recent' }}</div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $order->customer->name ?? 'Guest Shopper' }}</div>
                                <div style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $order->customer->phone ?? '' }}</div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $order->vendor->business_name ?? 'Vendor' }}</div>
                                <div style="font-size: 11.5px; color: #64748B;">Branch: {{ $order->shop->name ?? 'Main Store' }}</div>
                            </td>
                            <td>
                                @if($order->order_type === 'pickup')
                                    <span class="dash-badge dash-badge-gold" style="font-size: 11px;">
                                        🏬 In-Shop Pickup
                                    </span>
                                @else
                                    <span class="dash-badge dash-badge-blue" style="font-size: 11px;">
                                        🚚 Doorstep Delivery
                                    </span>
                                @endif
                            </td>
                            <td>
                                <strong style="font-size: 13.5px; color: var(--adm-text);">&#8358;{{ number_format((float)$order->order_amount, 2) }}</strong>
                                <div style="font-size: 10.5px; color: var(--adm-text-muted);">Prepaid</div>
                            </td>
                            <td>
                                @php
                                    $statusMap = [
                                        'pending' => 'dash-badge-amber',
                                        'confirmed' => 'dash-badge-info',
                                        'processing' => 'dash-badge-purple',
                                        'out_for_delivery' => 'dash-badge-blue',
                                        'delivered' => 'dash-badge-success',
                                        'canceled' => 'dash-badge-danger',
                                    ];
                                    $badge = $statusMap[$order->order_status] ?? 'dash-badge-purple';
                                @endphp
                                <span class="dash-badge {{ $badge }}">
                                    ● {{ ucfirst(str_replace('_', ' ', $order->order_status)) }}
                                </span>
                            </td>
                            <td>
                                @if($order->vendor_settlement_status === 'settled')
                                    <span class="dash-badge dash-badge-success">Disbursed (90%)</span>
                                @elseif($order->vendor_settlement_status === 'eligible')
                                    <span class="dash-badge dash-badge-emerald">Ready for Payout</span>
                                @else
                                    <span class="dash-badge dash-badge-gold">Held in Escrow</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('order.status', $order->order_number) }}" target="_blank" class="btn-dash btn-dash-outline" style="padding: 4px 10px; font-size: 11px;">
                                    Track Live ↗
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: var(--adm-text-muted);">
                                No orders registered in the system yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection