@extends('layouts.dashboard')

@section('title', 'In-Shop Inspection & Handover Terminal')
@section('role_title', 'Counter Staff')
@section('page_title', 'In-Shop Inspection & Handover Terminal')
@section('role_badge', 'COUNTER STAFF')
@section('role_badge_class', 'role-badge-counter')

@section('sidebar_menu')
    @include('counter.partials._sidebar')
@endsection

@section('content')
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏬</span>
                <span>In-Shop Inspection &amp; Handover Terminal</span>
            </h1>
            <p class="page-header-subtitle">
                Branch Plaza: <strong style="color: var(--adm-text);">{{ $shop->name ?? 'Victor Tech Uyo Flagship Plaza' }}</strong> — Physical inspection authority &amp; courier custody transfer.
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <span class="dash-badge dash-badge-success" style="padding: 6px 12px; font-size: 12px;">
                ● Counter Online
            </span>
        </div>
    </div>

    <!-- 4 KPI Cards -->
    <div class="stat-grid-4">
        <div class="stat-card stat-card-border-gold">
            <div class="stat-card-info">
                <span class="stat-card-label">Pending Inspections</span>
                <span class="stat-card-value">{{ $counterMetrics['pending_inspections'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">Shopper Reservation Passes</span>
            </div>
            <div class="stat-card-icon-box icon-box-gold">🔎</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Staged for Pickup</span>
                <span class="stat-card-value">{{ $counterMetrics['staged_for_pickup'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Ready on Counter Rack</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">📦</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Inspected Today</span>
                <span class="stat-card-value">{{ $counterMetrics['inspected_today'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Customer Verified</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">✅</div>
        </div>

        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Inspection Pass Rate</span>
                <span class="stat-card-value">{{ $counterMetrics['pass_rate'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">Zero Customer Disputes</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">🏆</div>
        </div>
    </div>

    <!-- Operational Launchpad -->
    <div style="margin-bottom: 28px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
            <h2 style="font-size: 15px; font-weight: 800; color: var(--adm-text); text-transform: uppercase; letter-spacing: 0.5px;">
                ⚡ Counter Staff Terminals
            </h2>
            <span style="font-size: 12px; color: var(--adm-text-muted);">Dedicated Standalone Modules</span>
        </div>

        <div class="module-launchpad-grid">
            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--adm-warning);">🔎</div>
                        <span class="dash-badge dash-badge-gold">{{ count($pendingInspections) }} Pending</span>
                    </div>
                    <div class="module-card-title">Physical Inspection Sign-Off Desk</div>
                    <div class="module-card-desc">Inspect product in person with the customer, answer questions, and sign off approval before escrow release.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: #047857; font-weight: 700;">● Walk-in Desk</span>
                    <a href="{{ route('counter.inspections.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Inspection Desk &rarr;
                    </a>
                </div>
            </div>

            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--adm-success);">🔑</div>
                        <span class="dash-badge dash-badge-success">{{ count($stagedOrders) }} Ready</span>
                    </div>
                    <div class="module-card-title">Rider Handover Custody Verification</div>
                    <div class="module-card-desc">Verify courier's 6-digit cryptographic OTP to legally transfer custody from the branch counter to logistics.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: var(--adm-text-muted);">Cryptographic Handshake</span>
                    <a href="{{ route('counter.handovers.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Handover Desk &rarr;
                    </a>
                </div>
            </div>

            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(2, 132, 199, 0.1); color: var(--adm-info);">📋</div>
                        <span class="dash-badge dash-badge-info">{{ count($inspectedToday) }} Logged</span>
                    </div>
                    <div class="module-card-title">Today's Inspection Records</div>
                    <div class="module-card-desc">Review the daily log of completed customer physical examinations, passed items, and counter activity.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: #10B981; font-weight: 700;">● 100% Pass Rate</span>
                    <a href="{{ route('counter.records.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Daily Records &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection