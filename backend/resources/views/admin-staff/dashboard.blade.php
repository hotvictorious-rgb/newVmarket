@extends('layouts.dashboard')

@section('title', 'Admin Staff Operations Desk')
@section('role_title', 'Admin Staff')
@section('page_title', 'Operations & Moderation Support Desk')
@section('role_badge', 'ADMIN STAFF')
@section('role_badge_class', 'role-badge-admin-staff')

@section('sidebar_menu')
    @include('admin-staff.partials._sidebar')
@endsection

@section('content')
    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">📋</span>
                <span>Operations, Moderation &amp; Support Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Supervise active order dispatches, moderate vendor catalog listings for 7-day freshness, and assist customer inquiries.
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <span class="dash-badge dash-badge-info" style="padding: 6px 12px; font-size: 12px;">
                Operational Support Clearance (Non-Treasury)
            </span>
        </div>
    </div>

    <!-- 4 KPI Metrics -->
    <div class="stat-grid-4">
        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Catalog Items Monitored</span>
                <span class="stat-card-value">{{ $metrics['pending_reviews'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">7-Day Freshness Monitored</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">🔍</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">Orders in Fulfillment</span>
                <span class="stat-card-value">{{ $metrics['orders_triage'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">Processing &amp; Ready</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">📦</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">In-Shop Counter Check-ins</span>
                <span class="stat-card-value">{{ $metrics['counter_inspections'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Verified by Plaza Staff</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">🏬</div>
        </div>

        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Fleet Couriers</span>
                <span class="stat-card-value">{{ $metrics['active_riders'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">Online in Uyo LGA</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">🏍️</div>
        </div>
    </div>

    <!-- Operational Launchpad -->
    <div style="margin-bottom: 28px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
            <h2 style="font-size: 15px; font-weight: 800; color: var(--adm-text); text-transform: uppercase; letter-spacing: 0.5px;">
                ⚡ Staff Operations Desks
            </h2>
            <span style="font-size: 12px; color: var(--adm-text-muted);">Dedicated Standalone Modules</span>
        </div>

        <div class="module-launchpad-grid">
            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(2, 132, 199, 0.1); color: var(--adm-info);">🔍</div>
                        <span class="dash-badge dash-badge-info">{{ count($products) }} Items</span>
                    </div>
                    <div class="module-card-title">Product Catalog Moderation &amp; 7-Day Freshness Queue</div>
                    <div class="module-card-desc">Moderate new merchant listings, inspect description quality, and enforce the 7-day freshness rotation.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: #047857; font-weight: 700;">● Active Queue</span>
                    <a href="{{ route('admin.staff.moderation.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Moderation Desk &rarr;
                    </a>
                </div>
            </div>

            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--adm-warning);">📦</div>
                        <span class="dash-badge dash-badge-gold">{{ count($recentOrders) }} Orders</span>
                    </div>
                    <div class="module-card-title">Order Dispatch Triage</div>
                    <div class="module-card-desc">Supervise active customer orders, ensure merchant preparation promptness, and monitor handover queues.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: var(--adm-text-muted);">Live Triage Flow</span>
                    <a href="{{ route('admin.staff.orders.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Triage Desk &rarr;
                    </a>
                </div>
            </div>

            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--adm-success);">🏬</div>
                        <span class="dash-badge dash-badge-success">{{ count($counterInspections) }} Passes</span>
                    </div>
                    <div class="module-card-title">Counter Inspection Monitor</div>
                    <div class="module-card-desc">Track physical inspection reservation passes, branch staff sign-offs, and in-shop pickup custody handovers.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: #10B981; font-weight: 700;">● Counter Terminals</span>
                    <a href="{{ route('admin.staff.inspections.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        Open Counter Monitor &rarr;
                    </a>
                </div>
            </div>

            <div class="module-card">
                <div>
                    <div class="module-card-header">
                        <div class="module-card-icon" style="background: rgba(168, 85, 247, 0.1); color: #8B5CF6;">📜</div>
                        <span class="dash-badge dash-badge-purple">{{ count($auditLogs) }} Logs</span>
                    </div>
                    <div class="module-card-title">Platform Operational Logs</div>
                    <div class="module-card-desc">Review the immutable audit log of administrative actions, user logins, and order status transitions.</div>
                </div>
                <div class="module-card-footer">
                    <span style="font-size: 11.5px; color: var(--adm-text-muted);">Immutable Trail</span>
                    <a href="{{ route('admin.staff.logs.index') }}" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                        View Audit Logs &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection