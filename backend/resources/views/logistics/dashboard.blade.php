@extends('layouts.dashboard')

@section('title', 'Logistics Fleet Operations Center')
@section('role_title', 'Logistics Fleet Operations Center')
@section('page_title', 'Fleet Logistics Operations Center')
@section('role_badge', 'LOGISTICS HQ')
@section('role_badge_class', 'role-badge-logistics')

@section('sidebar_menu')
    @include('logistics.partials._sidebar')
@endsection

@section('content')
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🚚</span>
                <span>Fleet Logistics Operations Center</span>
            </h1>
            <p class="page-header-subtitle">
                Company: <strong style="color: var(--adm-text);">{{ $company->company_name ?? 'Victorious Express Logistics Uyo' }}</strong> — Supervise directional LGA delivery corridors, couriers, and 5% logistics fee earnings.
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <span class="dash-badge dash-badge-success" style="padding: 6px 12px; font-size: 12px;">
                ● Fleet Operational
            </span>
        </div>
    </div>

    <!-- 4 KPI Cards -->
    <div class="stat-grid-4">
        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Couriers</span>
                <span class="stat-card-value">{{ $fleetMetrics['active_riders'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">{{ $fleetMetrics['on_road'] }} On Road Duty</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">🏍️</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Dispatches</span>
                <span class="stat-card-value">{{ $fleetMetrics['active_dispatches'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">Corridor Transit</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">📦</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">5% Logistics Fee Wallet</span>
                <span class="stat-card-value">₦{{ number_format($fleetMetrics['accumulated_fees'], 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Verified OTP Earnings</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">💼</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Completed Drops</span>
                <span class="stat-card-value">{{ $fleetMetrics['completed_dispatches'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Total Handshakes</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">🏁</div>
        </div>
    </div>

    <!-- Operational Launchpad -->
    <div style="margin-bottom: 28px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
            <h2 style="font-size: 15px; font-weight: 800; color: var(--adm-text); text-transform: uppercase; letter-spacing: 0.5px;">
                ⚡ Fleet Operations Desks
            </h2>
            <span style="font-size: 12px; color: var(--adm-text-muted);">Dedicated Standalone Modules</span>
        </div>

        <div class="module-launchpad-grid">
            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(94, 23, 235, 0.1); color: var(--adm-primary);">🏍️</div>
                        <span class="dash-badge dash-badge-purple">{{ count($activeRiders) }} Couriers</span>
                    </div>
                    <div class="module-card-title">Rider Roster &amp; Shifts</div>
                    <div class="module-card-desc">Assign couriers to LGA delivery corridors, inspect vehicle compliance, and monitor active duty shifts.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: #047857; font-weight: 700;">● Active Roster</span>
                    <a href="{{ route('logistics.riders.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Rider Roster &rarr;
                    </a>
                </div>
            </div>

            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--adm-warning);">📜</div>
                        <span class="dash-badge dash-badge-gold">{{ count($activeDeliveries) }} Dispatches</span>
                    </div>
                    <div class="module-card-title">Directional LGA Waybills Manifest</div>
                    <div class="module-card-desc">Monitor parcels in transit from branch counters across Akwa Ibom corridors to customer doorsteps.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: var(--adm-text-muted);">Corridor Tracking</span>
                    <a href="{{ route('logistics.waybills.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Waybill Manifest &rarr;
                    </a>
                </div>
            </div>

            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--adm-success);">💼</div>
                        <span class="dash-badge dash-badge-success">5% Cut</span>
                    </div>
                    <div class="module-card-title">5% Logistics Fee Wallet</div>
                    <div class="module-card-desc">Review accumulated delivery revenue cuts, completed delivery logs, and corporate payout balances.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: #059669; font-weight: 700;">● Instant Escrow</span>
                    <a href="{{ route('logistics.wallet.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Fee Wallet &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection