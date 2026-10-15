@extends('layouts.dashboard')

@section('title', 'Rider Dispatch Cockpit')
@section('role_title', 'Delivery Rider')
@section('page_title', 'Delivery Rider Dispatch Cockpit')
@section('role_badge', 'DISPATCH RIDER')
@section('role_badge_class', 'role-badge-rider')

@section('sidebar_menu')
    <li class="nav-section-title">COURIER COCKPIT</li>
    <li class="sidebar-nav-item">
        <a href="{{ route('rider.dashboard') }}" class="sidebar-nav-link active">
            <span class="nav-link-icon">🏍️</span>
            <span>Rider Cockpit</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#active-runs" class="sidebar-nav-link">
            <span class="nav-link-icon">📦</span>
            <span>My Active Drops</span>
            <span class="nav-link-badge">{{ count($activeDeliveries) }}</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#pickup-stop" class="sidebar-nav-link">
            <span class="nav-link-icon">🏬</span>
            <span>Plaza Pickup Stop</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#customer-dropoff" class="sidebar-nav-link">
            <span class="nav-link-icon">🏁</span>
            <span>Customer Drop-off &amp; OTP</span>
        </a>
    </li>

    <li class="nav-section-title">EARNINGS</li>
    <li class="sidebar-nav-item">
        <a href="#earnings-log" class="sidebar-nav-link">
            <span class="nav-link-icon">💰</span>
            <span>Today's Earning Ledger</span>
        </a>
    </li>
@endsection

@section('content')
    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏍️</span>
                <span>{{ $rider->user->name ?? 'Edidiong Udoh (Rider Alpha)' }}</span>
            </h1>
            <p class="page-header-subtitle">
                Fleet: <strong>{{ $rider->logisticsCompany->company_name ?? 'Akwa Express Logistics' }}</strong> &bull; Vehicle: <strong>Motorcycle ({{ $rider->license_plate ?? 'UYO-123-AK' }})</strong> &bull; Corridor: <strong>{{ $riderMetrics['assigned_corridor'] }}</strong>
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <form method="POST" action="{{ route('rider.toggle.shift') }}" style="margin: 0;">
                @csrf
                <button type="submit" class="btn-dash {{ $rider->is_online ? 'btn-dash-success' : 'btn-dash-outline' }}" style="padding: 6px 14px; font-size: 12px;">
                    {{ $rider->is_online ? '🟢 ON DUTY (Tap to go Off-Duty)' : '⚪ OFF DUTY (Tap to go On-Duty)' }}
                </button>
            </form>
        </div>
    </div>

    <!-- 4 KPI Metrics -->
    <div class="stat-grid-4">
        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Assigned Delivery Drops</span>
                <span class="stat-card-value">{{ $riderMetrics['assigned_runs'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">In Today's Run</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">📦</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Completed Drops Today</span>
                <span class="stat-card-value">{{ $riderMetrics['completed_today'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Verified via Customer OTP</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">🏁</div>
        </div>

        <div class="stat-card stat-card-border-gold">
            <div class="stat-card-info">
                <span class="stat-card-label">Today's Estimated Earnings</span>
                <span class="stat-card-value">&#8358;{{ $riderMetrics['estimated_earnings'] }}</span>
                <span class="stat-card-subtext" style="color: #B45309;">Drop fees + base subsidy</span>
            </div>
            <div class="stat-card-icon-box icon-box-gold">💰</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Duty Shift Status</span>
                <span class="stat-card-value" style="font-size: 20px;">{{ $riderMetrics['shift_status'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">GPS Ping Active</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">⚡</div>
        </div>
    </div>

    <!-- Active Delivery Step-by-Step Flow -->
    <div class="dash-card" id="active-runs" style="border: 1.5px solid rgba(94, 23, 235, 0.3);">
        <div class="dash-card-header" style="background: linear-gradient(135deg, rgba(94, 23, 235, 0.05) 0%, rgba(255, 215, 0, 0.05) 100%);">
            <div class="dash-card-title">
                <span>📍</span>
                <div>
                    <div>Current Active Delivery Run (Order Step-by-Step)</div>
                    <span class="dash-card-subtitle">Complete the pickup handover OTP at the plaza, then verify customer delivery OTP at doorstep.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-purple">Dual Handshake Invariant</span>
        </div>

        <div class="dash-card-body">
            @php $currentOrder = $activeDeliveries->first(); @endphp
            @if($currentOrder)
                <!-- Step Cards Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; margin-bottom: 24px;">
                    
                    <!-- Step 1: Pickup Stop -->
                    <div style="background: #F8FAFC; border: 1.5px solid var(--adm-border); border-radius: 10px; padding: 18px;" id="pickup-stop">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 11px; font-weight: 800; color: var(--adm-primary); text-transform: uppercase;">STEP 1: PLAZA COUNTER PICKUP</span>
                            <span class="dash-badge dash-badge-purple">Branch Stop</span>
                        </div>
                        <h4 style="font-size: 15px; font-weight: 700; margin-bottom: 4px;">{{ $currentOrder->shop->name ?? 'Victor Tech Uyo Flagship' }}</h4>
                        <p style="font-size: 12px; color: var(--adm-text-muted); margin-bottom: 14px;">
                            📍 42 Ikot Ekpene Road, Uyo
                        </p>
                        
                        <div style="background: #FFF; border: 1px dashed var(--adm-primary); border-radius: 8px; padding: 12px; text-align: center; margin-bottom: 10px;">
                            <span style="font-size: 11px; color: var(--adm-text-muted); display: block;">Show this OTP to Counter Officer:</span>
                            <span style="font-family: monospace; font-size: 22px; font-weight: 800; color: var(--adm-primary); letter-spacing: 4px;">
                                {{ $currentOrder->pickup_verification_code ?? '654920' }}
                            </span>
                        </div>
                        <span style="font-size: 11px; color: var(--adm-text-muted);">Counter will enter this code to release package into your custody.</span>
                    </div>

                    <!-- Step 2: Customer Doorstep & Dual OTP -->
                    <div style="background: #F8FAFC; border: 1.5px solid var(--adm-border); border-radius: 10px; padding: 18px;" id="customer-dropoff">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 11px; font-weight: 800; color: var(--adm-success); text-transform: uppercase;">STEP 2: CUSTOMER DOORSTEP HANDSHAKE</span>
                            <span class="dash-badge dash-badge-success">Drop-off Stop</span>
                        </div>
                        <h4 style="font-size: 15px; font-weight: 700; margin-bottom: 4px;">{{ $currentOrder->customer->name ?? 'Emeka Okonkwo' }}</h4>
                        <p style="font-size: 12px; color: var(--adm-text-muted); margin-bottom: 14px;">
                            📍 12 Hospital Road, Uyo &bull; Tel: <strong>{{ $currentOrder->customer->phone ?? '+2348055550001' }}</strong>
                        </p>

                        <!-- Doorstep OTP Form -->
                        <form method="POST" action="{{ route('rider.verify.delivery.otp') }}" style="background: #FFF; border: 1px solid var(--adm-border); border-radius: 8px; padding: 14px;">
                            @csrf
                            <input type="hidden" name="order_id" value="{{ $currentOrder->id }}">
                            <label style="display: block; font-size: 11.5px; font-weight: 700; margin-bottom: 6px;">
                                Ask Customer for their 6-Digit Delivery OTP:
                            </label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" name="delivery_otp" placeholder="e.g. 829104" maxlength="6" required style="flex: 1; padding: 8px 12px; border-radius: 6px; border: 1.5px solid var(--adm-success); font-family: monospace; font-size: 15px; font-weight: 700; text-align: center; letter-spacing: 2px;">
                                <button type="submit" class="btn-dash btn-dash-success" style="padding: 8px 16px;">
                                    ✓ Complete Drop
                                </button>
                            </div>
                            <span style="font-size: 10.5px; color: var(--adm-text-muted); display: block; margin-top: 6px;">
                                (Customer Demo OTP is: <code style="color: var(--adm-primary); font-weight: 700;">{{ $currentOrder->verification_code ?? '829104' }}</code>)
                            </span>
                        </form>
                    </div>
                </div>
            @else
                <div style="margin-bottom: 16px;">
                    <div style="background: #F8FAFC; border: 1.5px dashed var(--adm-primary); border-radius: 10px; padding: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                            <div>
                                <span style="font-size: 11px; font-weight: 800; color: var(--adm-primary); text-transform: uppercase;">COURIER DISPATCH PROTOCOL GUIDE</span>
                                <h4 style="font-size: 15px; font-weight: 700; margin-top: 2px;">Standby Mode — Ready for Next LGA Corridor Run</h4>
                            </div>
                            <span class="dash-badge dash-badge-info">Protocol Active</span>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px;">
                            <div style="background: #FFF; border: 1px solid var(--adm-border); border-radius: 8px; padding: 14px;">
                                <span style="font-size: 11px; font-weight: 800; color: var(--adm-primary); text-transform: uppercase;">STEP 1: PLAZA COUNTER PICKUP</span>
                                <p style="font-size: 12px; color: var(--adm-text-muted); margin-top: 6px;">
                                    When assigned, drive to the designated plaza counter and present your 6-digit pickup OTP to release package custody.
                                </p>
                            </div>
                            <div style="background: #FFF; border: 1px solid var(--adm-border); border-radius: 8px; padding: 14px;">
                                <span style="font-size: 11px; font-weight: 800; color: var(--adm-success); text-transform: uppercase;">STEP 2: CUSTOMER DOORSTEP HANDSHAKE</span>
                                <p style="font-size: 12px; color: var(--adm-text-muted); margin-top: 6px;">
                                    Arrive at customer doorstep and enter customer's 6-digit delivery OTP to securely complete custody and unlock your drop earnings.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Today's Completed Runs & Payout Shares -->
    <div class="dash-card" id="earnings-log">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>💰</span>
                <div>
                    <div>Today's Completed Drops &amp; Rider Earning Log</div>
                    <span class="dash-card-subtitle">Verified drop-offs cleared with customer delivery OTP handshake.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-gold">Cleared Payouts</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Pickup Plaza</th>
                        <th>Customer Delivered</th>
                        <th>Corridor Route</th>
                        <th>Your Drop Fee</th>
                        <th>Handshake Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($completedDrops as $drop)
                        <tr>
                            <td style="font-family: monospace; font-weight: 700;">{{ $drop->order_number }}</td>
                            <td>{{ $drop->shop->name ?? 'Victor Tech Plaza' }}</td>
                            <td>{{ $drop->customer->name ?? 'Shopper' }}</td>
                            <td>Uyo Intra-City Lane</td>
                            <td><strong style="color: var(--adm-success); font-size: 13.5px;">+&#8358;750.00</strong></td>
                            <td>
                                <span class="dash-badge dash-badge-success">Dual OTP Verified ✓</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px; color: var(--adm-text-muted);">
                                No completed drops yet today. Complete your active run above!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
