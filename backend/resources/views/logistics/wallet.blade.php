@extends('layouts.dashboard')

@section('title', '90% Logistics Fee Wallet — Logistics Fleet')
@section('role_title', 'Logistics Fleet Operations Center')
@section('page_title', '90% Logistics Delivery Fee Wallet')
@section('role_badge', 'LOGISTICS HQ')
@section('role_badge_class', 'role-badge-logistics')

@section('sidebar_menu')
    @include('logistics.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('logistics.dashboard') }}">Logistics Fleet</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">90% Logistics Delivery Fee Wallet</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">💼</span>
                <span>90% Logistics Delivery Fee Wallet &amp; Revenue Ledger</span>
            </h1>
            <p class="page-header-subtitle">
                Automated mathematical 90% delivery revenue credited upon verified customer delivery OTP handshake. Victorious Market earns 10% platform fee. Direct B2B corporate settlement.
            </p>
        </div>
        <div>
            <span class="dash-badge dash-badge-success" style="padding: 6px 14px; font-size: 13px; font-weight: 800;">
                ₦{{ number_format((float)($company->wallet_balance ?? 0), 2) }} Corporate Balance
            </span>
        </div>
    </div>

    <!-- Financial Stats Grid -->
    <div class="stat-grid-4">
        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Corporate Wallet Balance</span>
                <span class="stat-card-value">₦{{ number_format((float)($company->wallet_balance ?? 0), 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Direct 90% Delivery Escrow</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">💼</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Total Delivered Runs</span>
                <span class="stat-card-value">{{ count($completedRuns) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">OTP Cleared Handshakes</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">🏁</div>
        </div>

        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Couriers Online</span>
                <span class="stat-card-value">{{ $fleetMetrics['on_road'] ?? 0 }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">Across Akwa Ibom</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">🏍️</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">Settlement Formula</span>
                <span class="stat-card-value">90% / 10%</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">90% Fleet / 10% VMarket</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">⚡</div>
        </div>
    </div>

    <!-- Completed Dispatches Table -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Completed Dispatches &amp; Fee Ledger ({{ count($completedRuns) }})</div>
                <span class="dash-card-subtitle">Verified dispatches finalized with customer 6-digit cryptographic OTP handshake</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order # &amp; Date</th>
                        <th>Shopper &amp; Phone</th>
                        <th>Plaza Branch</th>
                        <th>Courier</th>
                        <th>Total Delivery Fee</th>
                        <th>90% Logistics Cut</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($completedRuns as $run)
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-primary);">{{ $run->order_number }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $run->updated_at->format('d M, Y H:i') }}</span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $run->customer->name ?? 'Shopper' }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $run->customer->phone ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ $run->shop->name ?? 'Plaza Branch' }}</span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-purple">{{ $run->rider->user->name ?? 'Courier' }}</span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">₦{{ number_format((float)($run->delivery_fee ?? 0), 2) }}</div>
                                <span style="font-size: 11px; color: var(--adm-text-muted);">VMarket 10%: ₦{{ number_format((float)($run->platform_delivery_fee ?? bcmul((string)($run->delivery_fee ?? 0), '0.10', 2)), 2) }}</span>
                            </td>
                            <td style="font-weight: 800; color: #059669;">
                                ₦{{ number_format((float)($run->logistics_fee_retained ?? bcmul((string)($run->delivery_fee ?? 0), '0.90', 2)), 2) }}
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-success">✅ Delivered &amp; Settled</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No completed delivery dispatches recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection