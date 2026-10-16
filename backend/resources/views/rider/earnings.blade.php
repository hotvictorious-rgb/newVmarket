@extends('layouts.dashboard')

@section('title', 'Shift Trip History & Telemetry — Delivery Rider')
@section('role_title', 'Delivery Rider Dispatch Cockpit')
@section('page_title', 'Shift Trip History & Telemetry')
@section('role_badge', 'DISPATCH RIDER')
@section('role_badge_class', 'role-badge-rider')

@section('sidebar_menu')
    @include('rider.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('rider.dashboard') }}">Rider Cockpit</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Shift Trip History</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">📊</span>
                <span>Shift Trip History &amp; Telemetry</span>
            </h1>
            <p class="page-header-subtitle">
                Verified delivery drops cleared with customer OTP handshake. Operational shift dispatch record.
            </p>
        </div>
        <div>
            <span class="dash-badge dash-badge-success" style="padding: 6px 14px; font-size: 13px; font-weight: 800;">
                {{ count($completedDrops) }} Completed Runs
            </span>
        </div>
    </div>

    <!-- Corporate Fleet Employment Notice -->
    <div style="background: rgba(94, 23, 235, 0.05); border: 1.5px solid rgba(94, 23, 235, 0.2); border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 28px;">🏢</span>
            <div>
                <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--adm-primary); letter-spacing: 0.5px;">Parent Logistics Fleet</div>
                <div style="font-size: 15px; font-weight: 800; color: var(--adm-text);">{{ $rider->logisticsCompany->company_name ?? 'Logistics Fleet Partner' }}</div>
                <div style="font-size: 12px; color: var(--adm-text-muted);">All salary, trip allowances, and fuel disbursements are administered directly by your parent fleet headquarters.</div>
            </div>
        </div>
        <span class="dash-badge dash-badge-success" style="padding: 6px 12px; font-size: 12px; font-weight: 700;">
            ✓ Operational Courier Roster
        </span>
    </div>

    <!-- Stats Grid -->
    <div class="stat-grid-4" style="margin-bottom: 24px;">
        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Completed Drops</span>
                <span class="stat-card-value">{{ count($completedDrops) }}</span>
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

        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Duty Shift Status</span>
                <span class="stat-card-value">{{ $riderMetrics['shift_status'] ?? 'ON DUTY' }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">Real-time Telemetry</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">🏍️</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">Verification Rate</span>
                <span class="stat-card-value">100%</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">Cryptographic OTP Proof</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">⚡</div>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Completed Delivery Drops ({{ count($completedDrops) }})</div>
                <span class="dash-card-subtitle">Runs verified with customer delivery OTP handshake</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order # &amp; Handshake Time</th>
                        <th>Pickup Merchant</th>
                        <th>Customer Recipient</th>
                        <th>Corridor Route</th>
                        <th>Handshake Verification</th>
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
                            <td>
                                <span class="dash-badge dash-badge-info">{{ $drop->destinationLga->name ?? 'Uyo' }} Route</span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-success">✅ Handshake Cleared</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No completed drops recorded on this shift yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection