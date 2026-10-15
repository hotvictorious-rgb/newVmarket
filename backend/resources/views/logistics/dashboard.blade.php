@extends('layouts.dashboard')

@section('title', 'Logistics Fleet Operations')
@section('role_title', 'Logistics Fleet Desk')
@section('page_title', 'Fleet Logistics Operations Center')
@section('role_badge', 'LOGISTICS FLEET')
@section('role_badge_class', 'role-badge-logistics')

@section('sidebar_menu')
    <li class="nav-section-title">FLEET OPERATIONS</li>
    <li class="sidebar-nav-item">
        <a href="{{ route('logistics.dashboard') }}" class="sidebar-nav-link active">
            <span class="nav-link-icon">🚚</span>
            <span>Fleet Command</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#rider-roster" class="sidebar-nav-link">
            <span class="nav-link-icon">🏍️</span>
            <span>Rider Roster &amp; Shifts</span>
            <span class="nav-link-badge">{{ count($activeRiders) }}</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#waybills" class="sidebar-nav-link">
            <span class="nav-link-icon">📜</span>
            <span>Directional LGA Waybills</span>
            <span class="nav-link-badge">{{ count($activeDeliveries) }}</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#completed-runs" class="sidebar-nav-link">
            <span class="nav-link-icon">🏁</span>
            <span>Completed Dispatches</span>
        </a>
    </li>

    <li class="nav-section-title">FINANCES &amp; REVENUE</li>
    <li class="sidebar-nav-item">
        <a href="#logistics-wallet" class="sidebar-nav-link">
            <span class="nav-link-icon">💼</span>
            <span>5% Logistics Fee Wallet</span>
        </a>
    </li>
@endsection

@section('content')
    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🚚</span>
                <span>{{ $company->company_name ?? 'Akwa Express Logistics Ltd' }}</span>
            </h1>
            <p class="page-header-subtitle">
                Licensed Directional Courier Network &bull; HQ: {{ $company->hq_address ?? '15 Oron Road, Uyo' }} &bull; Fleet Capacity: {{ $company->fleet_size ?? 12 }} Vehicles
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <span class="dash-badge dash-badge-success" style="padding: 6px 12px; font-size: 12px;">
                ● Fleet Dispatch Active
            </span>
            <span class="dash-badge dash-badge-purple" style="padding: 6px 12px; font-size: 12px;">
                Dual OTP Handshake Guaranteed
            </span>
        </div>
    </div>

    <!-- 4 KPI Metrics -->
    <div class="stat-grid-4">
        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Couriers on Road</span>
                <span class="stat-card-value">{{ $fleetMetrics['on_road'] }} / {{ $fleetMetrics['active_riders'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">Assigned in Akwa Ibom</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">🏍️</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">In-Transit Waybills</span>
                <span class="stat-card-value">{{ $fleetMetrics['active_dispatches'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">Active on Directional Lanes</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">📦</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Delivered Runs Today</span>
                <span class="stat-card-value">{{ $fleetMetrics['completed_dispatches'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Verified via Customer OTP</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">🏁</div>
        </div>

        <div class="stat-card stat-card-border-gold">
            <div class="stat-card-info">
                <span class="stat-card-label">5% Delivery Revenue</span>
                <span class="stat-card-value">&#8358;{{ number_format($fleetMetrics['accumulated_fees'], 2) }}</span>
                <span class="stat-card-subtext" style="color: #B45309;">Logistics Split Revenue</span>
            </div>
            <div class="stat-card-icon-box icon-box-gold">💼</div>
        </div>
    </div>

    <!-- Panel 1: Fleet Rider Roster & Vehicle Assignment -->
    <div class="dash-card" id="rider-roster">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>🏍️</span>
                <div>
                    <div>Fleet Rider Roster &amp; Vehicle Deployment</div>
                    <span class="dash-card-subtitle">Active dispatchers, assigned vehicles, coverage corridors, and real-time duty status.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-info">Fleet Roster</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Rider Name</th>
                        <th>Phone</th>
                        <th>Vehicle &amp; Plate</th>
                        <th>Assigned LGA Corridor</th>
                        <th>Duty Status</th>
                        <th>Current Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeRiders as $rider)
                        <tr>
                            <td>
                                <strong>{{ $rider->user->name ?? 'Edidiong Udoh' }}</strong>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">NIN: {{ $rider->nin_number ?? 'Verified' }}</div>
                            </td>
                            <td>{{ $rider->user->phone ?? '+2348044440001' }}</td>
                            <td>
                                <span class="dash-badge dash-badge-purple" style="font-family: monospace;">
                                    {{ strtoupper($rider->vehicle_type) }} &bull; {{ $rider->license_plate ?? 'UYO-123-AK' }}
                                </span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ $rider->assignedLga->name ?? 'Uyo' }} Delivery Corridor</span>
                            </td>
                            <td>
                                @if($rider->is_online)
                                    <span class="dash-badge dash-badge-success">● On-Duty (Active)</span>
                                @else
                                    <span class="dash-badge dash-badge-warning">⚪ Off-Duty</span>
                                @endif
                            </td>
                            <td>
                                <strong>&#8358;{{ number_format($rider->current_balance, 2) }}</strong>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px; color: var(--adm-text-muted);">No active riders registered under this fleet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Panel 2: Directional LGA Waybills Manifest -->
    <div class="dash-card" id="waybills">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>📜</span>
                <div>
                    <div>Directional LGA Waybills Manifest</div>
                    <span class="dash-card-subtitle">Parcels collected from Plaza counters for door-to-door delivery across Akwa Ibom.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-gold">Directional Batches</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Waybill / Order #</th>
                        <th>Origin Shop Plaza</th>
                        <th>Customer Destination</th>
                        <th>Assigned Courier</th>
                        <th>Fulfillment Stage</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeDeliveries as $ord)
                        <tr>
                            <td style="font-family: monospace; font-weight: 700;">{{ $ord->order_number }}</td>
                            <td>{{ $ord->shop->name ?? 'Victor Tech Uyo Flagship' }}</td>
                            <td>
                                <div>{{ $ord->customer->name ?? 'Shopper' }}</div>
                                <div style="font-size: 11.5px; color: var(--adm-text-muted);">Uyo LGA Corridor</div>
                            </td>
                            <td>{{ $ord->rider->user->name ?? 'Edidiong Udoh (Rider Alpha)' }}</td>
                            <td>
                                @if($ord->order_status === 'out_for_delivery')
                                    <span class="dash-badge dash-badge-info">🚚 In LGA Transit</span>
                                @else
                                    <span class="dash-badge dash-badge-warning">⏳ Awaiting Counter Handover</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px; color: var(--adm-text-muted);">No active dispatches currently on the road.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Panel 3: 5% Logistics Fee Revenue Ledger -->
    <div class="dash-card" id="logistics-wallet">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>💼</span>
                <div>
                    <div>5% Logistics Fee Wallet &amp; Reconciliation</div>
                    <span class="dash-card-subtitle">Automated mathematical 5% revenue cut credited upon verified customer delivery OTP handshake.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-purple">Settlement Split</span>
        </div>

        <div class="dash-card-body">
            <div style="background: #F8FAFC; border: 1px solid var(--adm-border); border-radius: 8px; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <div>
                    <span style="font-size: 12px; color: var(--adm-text-muted); text-transform: uppercase; font-weight: 700;">Current Accumulated Logistics Split</span>
                    <div style="font-size: 28px; font-weight: 800; color: var(--adm-primary); margin-top: 4px;">
                        &#8358;{{ number_format($fleetMetrics['accumulated_fees'], 2) }}
                    </div>
                </div>
                <div style="font-size: 12.5px; color: var(--adm-text-muted); text-align: right;">
                    <div>Bank Account: <strong>Stanbic IBTC</strong></div>
                    <div>Account No: <strong>0034928172</strong></div>
                </div>
            </div>
            <p style="font-size: 12px; color: var(--adm-text-muted);">
                ⚖️ <strong>Sovereign Split Guarantee:</strong> Every delivered order automatically splits 5% of the total amount directly to the operating logistics partner, disbursed weekly.
            </p>
        </div>
    </div>
@endsection
