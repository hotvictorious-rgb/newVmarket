@extends('layouts.dashboard')

@section('title', 'Super Admin Command Tower')
@section('role_title', 'Super Admin')
@section('page_title', 'Platform Sovereign Command Tower')
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
                <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="VM" style="width: 32px; height: 32px; border-radius: 8px; object-fit: cover; border: 1.5px solid var(--adm-gold);">
                <span>Platform Sovereign Command Tower</span>
            </h1>
            <p class="page-header-subtitle">
                Central Governance, Directional Lane Matrix, Treasury Escrow, and 90/5/5 Mathematical Enforcement.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('storefront') }}" target="_blank" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 14px;">
                View Storefront 🌐
            </a>
            <span class="dash-badge dash-badge-purple" style="padding: 6px 12px; font-size: 12px;">
                🛡️ Zero-Trust Server Gate Active
            </span>
        </div>
    </div>

    <!-- 4 KPI Metrics -->
    <div class="stat-grid-4">
        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Escrow Orders</span>
                <span class="stat-card-value">{{ $metrics['active_orders'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">Prepaid via Paystack</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">📦</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Accredited Merchants</span>
                <span class="stat-card-value">{{ $metrics['total_vendors'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">{{ $metrics['total_shops'] }} Verified Uyo Counters</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">🏬</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Settlements in 24h Hold</span>
                <span class="stat-card-value">{{ $metrics['held_settlements'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Dispute Protection Buffer</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">⏳</div>
        </div>

        <div class="stat-card stat-card-border-gold">
            <div class="stat-card-info">
                <span class="stat-card-label">Customer Cashback Ledger</span>
                <span class="stat-card-value">&#8358;{{ number_format((float)$metrics['total_cashback_issued'], 2) }}</span>
                <span class="stat-card-subtext" style="color: #B45309;">5% Platform Funded</span>
            </div>
            <div class="stat-card-icon-box icon-box-gold">🪙</div>
        </div>
    </div>

    <!-- Sovereign Module Launchpad (Dedicated Pages Navigation) -->
    <div style="margin-bottom: 28px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
            <h2 style="font-size: 15px; font-weight: 800; color: var(--adm-text); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px;">
                <span>⚡</span> Dedicated Administration Desks
            </h2>
            <span style="font-size: 12px; color: var(--adm-text-muted);">Independent Full-Screen Modules</span>
        </div>

        <div class="module-launchpad-grid">
            <!-- 1. Directional Delivery Lanes Desk -->
            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(94, 23, 235, 0.1); color: var(--adm-primary);">
                            🛣️
                        </div>
                        <span class="dash-badge dash-badge-purple">{{ count($deliveryLanes) }} Corridors</span>
                    </div>
                    <div class="module-card-title">Standard Delivery Fee Matrix &amp; Zonal Baselines</div>
                    <div class="module-card-desc">
                        Manage Origin ⇄ Destination LGA Corridors, 3-tier distance baseline pricing (Municipal, Regional, Inter-State), bulky cargo addons, and bidirectional sync. Merchants configure individual shop delivery coverage.
                    </div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: #047857; font-weight: 700;">● Standard Rate Schedule</span>
                    <a href="{{ route('admin.delivery_lanes.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Lanes Setup &rarr;
                    </a>
                </div>
            </div>

            <!-- 2. 90/5/5 Settlement Desk -->
            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--adm-success);">
                            💰
                        </div>
                        <span class="dash-badge dash-badge-success">{{ count($pendingSettlements) }} Ready for Payout</span>
                    </div>
                    <div class="module-card-title">90/5/5 &amp; 90/10 Settlement Desk</div>
                    <div class="module-card-desc">
                        Review verified orders, disburse 90% merchant payouts (merchandise + delivery) via FIFO batches, track dispute inspection window, and balance the 5% cashback ledger (&Delta; = &#8358;0.00).
                    </div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: #B45309; font-weight: 700;">● Escrow Protected</span>
                    <a href="{{ route('admin.settlements.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Settlement Desk &rarr;
                    </a>
                </div>
            </div>

            <!-- 3. Announcement Bar & Customer Care -->
            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(2, 132, 199, 0.1); color: var(--adm-info);">
                            📢
                        </div>
                        <span class="dash-badge dash-badge-info">Storefront Live</span>
                    </div>
                    <div class="module-card-title">Storefront Announcement &amp; Support Control Center</div>
                    <div class="module-card-desc">
                        Update the top announcement ticker, customer care telephone numbers, WhatsApp call-to-order hotline, and seasonal promotional banners across all apps.
                    </div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: var(--adm-text-muted);">Hotline: {{ $announcementSettings['support_phone'] ?? '09118949035' }}</span>
                    <a href="{{ route('admin.announcements.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Configure Care &amp; Banner &rarr;
                    </a>
                </div>
            </div>

            <!-- 4. Platform Escrow Orders -->
            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--adm-warning);">
                            📦
                        </div>
                        <span class="dash-badge dash-badge-gold">{{ $metrics['active_orders'] }} Active Orders</span>
                    </div>
                    <div class="module-card-title">Platform Escrow Orders &amp; Fulfillment</div>
                    <div class="module-card-desc">
                        Audit full order journeys from Shopper &rarr; Vendor &rarr; Plaza Counter &rarr; Vendor Rider. Check physical inspection passes and delivery OTP handshakes.
                    </div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ count($recentOrders) }} Recent in Ledger</span>
                    <a href="{{ route('admin.orders.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        View All Orders &rarr;
                    </a>
                </div>
            </div>

            <!-- 5. Merchant Accreditation Desk -->
            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(168, 85, 247, 0.1); color: #8B5CF6;">
                            🏪
                        </div>
                        <span class="dash-badge dash-badge-purple">{{ $metrics['total_vendors'] }} Registered</span>
                    </div>
                    <div class="module-card-title">Merchant Accreditation &amp; KYC Desk</div>
                    <div class="module-card-desc">
                        Verify CAC registration numbers, Director NIN records, physical branch shops in Uyo plazas, and disburse marketplace listing approval.
                    </div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: #10B981; font-weight: 700;">● Sovereign Quality Gate</span>
                    <a href="{{ route('admin.merchants.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Review Merchants &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Activity Snapshots -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
        <!-- Pending Settlement Snapshot -->
        <div class="dash-card" style="margin-bottom: 0;">
            <div class="dash-card-header">
                <div class="dash-card-title">
                    <span>💰</span>
                    <div>
                        <div style="font-size: 14px;">Manual Settlement Desk â€” Quick Queue</div>
                        <span class="dash-card-subtitle">Orders verified and ready for 90% payout release.</span>
                    </div>
                </div>
                <a href="{{ route('admin.settlements.index') }}" class="dash-badge dash-badge-success" style="text-decoration: none;">
                    View All ({{ count($pendingSettlements) }}) &rarr;
                </a>
            </div>
            <div class="dash-table-responsive" style="max-height: 280px; overflow-y: auto;">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Merchant</th>
                            <th>90% Share</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingSettlements->take(4) as $order)
                            <tr>
                                <td>
                                    <strong style="font-family: monospace; font-size: 12.5px; color: var(--adm-primary);">{{ $order->order_number }}</strong>
                                </td>
                                <td>
                                    <div style="font-size: 12.5px; font-weight: 600;">{{ $order->vendor->business_name ?? 'Vendor' }}</div>
                                </td>
                                <td>
                                    <strong style="color: #047857;">&#8358;{{ number_format((float)$order->vendor_settlement_amount, 2) }}</strong>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.disburse.settlement') }}" style="margin: 0;">
                                        @csrf
                                        <input type="hidden" name="order_id" value="{{ $order->id }}">
                                        <button type="submit" class="btn-dash btn-dash-success" style="padding: 4px 8px; font-size: 11px;">
                                            Disburse
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 20px; color: var(--adm-text-muted);">
                                    No settlements currently pending disbursal.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Orders Snapshot -->
        <div class="dash-card" style="margin-bottom: 0;">
            <div class="dash-card-header">
                <div class="dash-card-title">
                    <span>📦</span>
                    <div>
                        <div style="font-size: 14px;">Recent Platform Escrow Orders</div>
                        <span class="dash-card-subtitle">Latest incoming shopper checkouts.</span>
                    </div>
                </div>
                <a href="{{ route('admin.orders.index') }}" class="dash-badge dash-badge-info" style="text-decoration: none;">
                    View All &rarr;
                </a>
            </div>
            <div class="dash-table-responsive" style="max-height: 280px; overflow-y: auto;">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentOrders->take(4) as $order)
                            <tr>
                                <td>
                                    <strong style="font-family: monospace; font-size: 12.5px; color: var(--adm-primary);">{{ $order->order_number }}</strong>
                                </td>
                                <td>
                                    <div style="font-size: 12px;">{{ $order->customer->name ?? 'Guest' }}</div>
                                </td>
                                <td>
                                    <strong>&#8358;{{ number_format((float)$order->order_amount, 2) }}</strong>
                                </td>
                                <td>
                                    <span class="dash-badge dash-badge-purple" style="font-size: 10px;">
                                        ● {{ ucfirst(str_replace('_', ' ', $order->order_status)) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 20px; color: var(--adm-text-muted);">
                                    No orders registered yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection