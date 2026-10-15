@extends('layouts.dashboard')

@section('title', "Today's Earning Ledger — Delivery Rider")
@section('role_title', 'Delivery Rider Dispatch Cockpit')
@section('page_title', "Today's Earning Ledger")
@section('role_badge', 'DISPATCH RIDER')
@section('role_badge_class', 'role-badge-rider')

@section('sidebar_menu')
    @include('rider.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('rider.dashboard') }}">Rider Cockpit</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Today's Earning Ledger</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">💰</span>
                <span>Today's Earning Ledger</span>
            </h1>
            <p class="page-header-subtitle">
                Verified delivery drops cleared with customer OTP handshake. Daily revenue ledger and payout summary.
            </p>
        </div>
        <div>
            <span class="dash-badge dash-badge-success" style="padding: 6px 14px; font-size: 13px; font-weight: 800;">
                ₦{{ $riderMetrics['estimated_earnings'] ?? '3,000.00' }} Total Earned
            </span>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stat-grid-4">
        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Today's Total Earnings</span>
                <span class="stat-card-value">₦{{ $riderMetrics['estimated_earnings'] ?? '3,000.00' }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Delivered Runs + Base</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">💰</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Completed Drops</span>
                <span class="stat-card-value">{{ count($completedDrops) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">OTP Cleared</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">🏁</div>
        </div>

        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Duty Shift Status</span>
                <span class="stat-card-value">{{ $riderMetrics['shift_status'] ?? 'ON DUTY' }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">{{ $riderMetrics['assigned_corridor'] ?? 'Uyo' }}</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">🏍️</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">Payout Channel</span>
                <span class="stat-card-value">DAILY</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">Direct Bank Transfer</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">🏦</div>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Completed Delivery Drops ({{ count($completedDrops) }})</div>
                <span class="dash-card-subtitle">Runs verified with customer delivery OTP</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order # &amp; Time</th>
                        <th>Plaza Branch</th>
                        <th>Customer Recipient</th>
                        <th>Rider Earning Cut</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($completedDrops as $drop)
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-primary);">{{ $drop->order_number }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $drop->updated_at->format('d M, Y H:i') }}</span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $drop->shop->name ?? 'Victor Tech Plaza' }}</div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $drop->customer->name ?? 'Emeka Okonkwo' }}</div>
                            </td>
                            <td style="font-weight: 800; color: #059669;">
                                ₦750.00
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-success">✅ Handshake Cleared</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No completed drops recorded today yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection