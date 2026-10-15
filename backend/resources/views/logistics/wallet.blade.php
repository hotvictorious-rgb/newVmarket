@extends('layouts.dashboard')

@section('title', '5% Logistics Fee Wallet — Logistics Fleet')
@section('role_title', 'Logistics Fleet Operations Center')
@section('page_title', '5% Logistics Fee Wallet')
@section('role_badge', 'LOGISTICS HQ')
@section('role_badge_class', 'role-badge-logistics')

@section('sidebar_menu')
    @include('logistics.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('logistics.dashboard') }}">Logistics Fleet</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">5% Logistics Fee Wallet</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">💼</span>
                <span>5% Logistics Fee Wallet &amp; Revenue Ledger</span>
            </h1>
            <p class="page-header-subtitle">
                Automated mathematical 5% revenue cut credited upon verified customer delivery OTP handshake. Direct corporate settlement.
            </p>
        </div>
        <div>
            <span class="dash-badge dash-badge-success" style="padding: 6px 14px; font-size: 13px; font-weight: 800;">
                ₦{{ number_format($fleetMetrics['accumulated_fees'] ?? 0, 2) }} Total Earned
            </span>
        </div>
    </div>

    <!-- Financial Stats Grid -->
    <div class="stat-grid-4">
        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Accumulated 5% Fees</span>
                <span class="stat-card-value">₦{{ number_format($fleetMetrics['accumulated_fees'] ?? 0, 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Mathematical 5% Revenue Share</span>
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
                <span class="stat-card-label">Settlement Status</span>
                <span class="stat-card-value">AUTO</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">Instant Escrow Release</span>
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
                        <th>5% Logistics Cut</th>
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
                            <td style="font-weight: 800; color: #059669;">
                                ₦{{ number_format($run->logistics_fee_5 ?? 150, 2) }}
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-success">✅ Delivered &amp; Settled</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No completed delivery dispatches recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection