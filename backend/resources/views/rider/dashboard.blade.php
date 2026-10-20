@extends('layouts.dashboard')

@section('title', 'Delivery Rider Dispatch Cockpit')
@section('role_title', 'Delivery Rider Dispatch Cockpit')
@section('page_title', 'Delivery Rider Dispatch Cockpit')
@section('role_badge', 'DISPATCH RIDER')
@section('role_badge_class', 'role-badge-rider')

@section('sidebar_menu')
    @include('rider.partials._sidebar')
@endsection

@section('content')
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏍️</span>
                <span>Delivery Rider Dispatch Cockpit</span>
            </h1>
            <p class="page-header-subtitle">
                Courier: <strong style="color: var(--adm-text);">{{ $rider->user->name ?? 'Ekanem Bassey' }}</strong> — Motorbike: <strong style="color: var(--adm-primary);">{{ $rider->vehicle_plate_number ?? 'UYO-123-AK' }}</strong> — Corridor: <strong style="color: var(--adm-text);">{{ $riderMetrics['assigned_corridor'] }}</strong>
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <form method="POST" action="{{ route('rider.toggle.shift') }}" style="margin: 0;">
                @csrf
                <button type="submit" class="btn-dash {{ ($rider && $rider->is_online) ? 'btn-dash-primary' : 'btn-dash-outline' }}">
                    {{ ($rider && $rider->is_online) ? '● ON DUTY' : '○ GO ON DUTY' }}
                </button>
            </form>
        </div>
    </div>

    <!-- 4 KPI Cards -->
    <div class="stat-grid-4">
        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Dispatches</span>
                <span class="stat-card-value">{{ $riderMetrics['assigned_runs'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">Assigned Courier Runs</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">📦</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Completed Drops</span>
                <span class="stat-card-value">{{ $riderMetrics['completed_today'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Verified OTP Handshakes</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">🏁</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Assigned Corridor</span>
                <span class="stat-card-value">{{ $riderMetrics['assigned_corridor'] ?? 'Uyo' }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Designated Transit Zone</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">📍</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">Duty Shift</span>
                <span class="stat-card-value">{{ $riderMetrics['shift_status'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">{{ $rider->vendor->business_name ?? ($rider->logisticsCompany->company_name ?? 'Merchant Fleet') }}</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">⚡</div>
        </div>
    </div>

    <!-- Operational Launchpad -->
    <div style="margin-bottom: 28px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
            <h2 style="font-size: 15px; font-weight: 800; color: var(--adm-text); text-transform: uppercase; letter-spacing: 0.5px;">
                ⚡ Rider Cockpit Desks
            </h2>
            <span style="font-size: 12px; color: var(--adm-text-muted);">Dedicated Standalone Modules</span>
        </div>

        <div class="module-launchpad-grid">
            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(94, 23, 235, 0.1); color: var(--adm-primary);">📦</div>
                        <span class="dash-badge dash-badge-purple">{{ count($activeDeliveries) }} Active</span>
                    </div>
                    <div class="module-card-title">My Active Drops</div>
                    <div class="module-card-desc">Review your active dispatch assignments, pickup locations, customer recipients, and corridor routes.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: #047857; font-weight: 700;">● Active Dispatches</span>
                    <a href="{{ route('rider.deliveries.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Active Drops &rarr;
                    </a>
                </div>
            </div>

            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--adm-warning);">🏬</div>
                        <span class="dash-badge dash-badge-gold">Step 1</span>
                    </div>
                    <div class="module-card-title">STEP 1: PLAZA COUNTER PICKUP</div>
                    <div class="module-card-desc">Collect parcels from physical plaza counters, verify pickup code, and initiate GPS-guided delivery transit.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: var(--adm-text-muted);">Plaza Counter Stop</span>
                    <a href="{{ route('rider.pickup.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Pickup Desk &rarr;
                    </a>
                </div>
            </div>

            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--adm-success);">🏁</div>
                        <span class="dash-badge dash-badge-success">Step 2</span>
                    </div>
                    <div class="module-card-title">STEP 2: CUSTOMER DOORSTEP HANDSHAKE</div>
                    <div class="module-card-desc">Deliver parcel to customer doorstep, verify the 6-digit cryptographic OTP, and complete order handover.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: #10B981; font-weight: 700;">● OTP Verification</span>
                    <a href="{{ route('rider.dropoff.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Drop-off Desk &rarr;
                    </a>
                </div>
            </div>

            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(2, 132, 199, 0.1); color: var(--adm-info);">💰</div>
                        <span class="dash-badge dash-badge-info">Daily Ledger</span>
                    </div>
                    <div class="module-card-title">Today's Earning Ledger</div>
                    <div class="module-card-desc">Review your delivery earnings, verified drop bonuses, base fees, and automated daily payout history.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: #059669; font-weight: 700;">● Daily Settlement</span>
                    <a href="{{ route('rider.earnings.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Earning Ledger &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection