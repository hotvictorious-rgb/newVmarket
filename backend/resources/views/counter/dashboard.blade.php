@extends('layouts.dashboard')

@section('title', 'Branch Counter Terminal')
@section('role_title', 'Branch Counter Staff')
@section('page_title', 'In-Shop Inspection & Handover Terminal')
@section('role_badge', 'BRANCH COUNTER')
@section('role_badge_class', 'role-badge-counter')

@section('sidebar_menu')
    <li class="nav-section-title">IN-SHOP COUNTER DESK</li>
    <li class="sidebar-nav-item">
        <a href="{{ route('counter.dashboard') }}" class="sidebar-nav-link active">
            <span class="nav-link-icon">🏬</span>
            <span>Counter Terminal</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#inspection-desk" class="sidebar-nav-link">
            <span class="nav-link-icon">🔎</span>
            <span>Physical Inspection Desk</span>
            <span class="nav-link-badge">{{ count($pendingInspections) }}</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#handover-desk" class="sidebar-nav-link">
            <span class="nav-link-icon">🔑</span>
            <span>Rider Handover OTP Desk</span>
            <span class="nav-link-badge">{{ count($stagedOrders) }}</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#staged-packages" class="sidebar-nav-link">
            <span class="nav-link-icon">📦</span>
            <span>Staged Packages Rack</span>
        </a>
    </li>

    <li class="nav-section-title">AUDIT &amp; RECORDS</li>
    <li class="sidebar-nav-item">
        <a href="#inspection-records" class="sidebar-nav-link">
            <span class="nav-link-icon">📋</span>
            <span>Today's Inspection Records</span>
        </a>
    </li>
@endsection

@section('content')
    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏬</span>
                <span>In-Shop Inspection &amp; Handover Terminal</span>
            </h1>
            <p class="page-header-subtitle">
                Assigned Outlet: <strong>{{ $shop->name ?? 'Victor Tech Uyo Flagship Plaza' }}</strong> &bull; 42 Ikot Ekpene Road, Uyo
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <span class="dash-badge dash-badge-success" style="padding: 6px 12px; font-size: 12px;">
                ● Counter Lane Active
            </span>
            <span class="dash-badge dash-badge-purple" style="padding: 6px 12px; font-size: 12px;">
                🔒 Branch Isolation Barrier Enforced
            </span>
        </div>
    </div>

    <!-- 4 KPI Metrics -->
    <div class="stat-grid-4">
        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">Pending In-Shop Examinations</span>
                <span class="stat-card-value">{{ $counterMetrics['pending_inspections'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">Shopper Expected at Counter</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">🔎</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Packages Staged for Courier</span>
                <span class="stat-card-value">{{ $counterMetrics['staged_for_pickup'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Awaiting Rider OTP Handshake</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">📦</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Inspected &amp; Cleared Today</span>
                <span class="stat-card-value">{{ $counterMetrics['inspected_today'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Payment Unlocked</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">✓</div>
        </div>

        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Counter Inspection Pass Rate</span>
                <span class="stat-card-value">{{ $counterMetrics['pass_rate'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">100% Verified Quality</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">🛡️</div>
        </div>
    </div>

    <!-- Dual Counter Operational Desks -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px; margin-bottom: 24px;">
        
        <!-- Desk 1: In-Shop Physical Inspection Sign-Off -->
        <div class="dash-card" id="inspection-desk" style="border-top: 3.5px solid var(--adm-gold); margin-bottom: 0;">
            <div class="dash-card-header">
                <div class="dash-card-title">
                    <span>1️⃣</span>
                    <div>
                        <div>Physical Inspection Sign-Off Desk</div>
                        <span class="dash-card-subtitle">Examine product with shopper before unlocking payment.</span>
                    </div>
                </div>
                <span class="dash-badge dash-badge-gold">Inspect First</span>
            </div>

            <div class="dash-card-body">
                <p style="font-size: 12.5px; color: var(--adm-text-muted); margin-bottom: 16px;">
                    When the customer arrives at this plaza counter with a reservation code, physically present the item for examination. If customer accepts, submit sign-off below to unlock their Paystack payment.
                </p>

                <form method="POST" action="{{ route('vendor.approve.inspection') }}" style="background: #F8FAFC; padding: 18px; border-radius: 8px; border: 1px solid var(--adm-border); margin-bottom: 20px;">
                    @csrf
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px;">Customer Reservation Code:</label>
                        <input type="text" name="reservation_code" placeholder="RES-XXXXXXXX" required style="width: 100%; padding: 10px 14px; border-radius: 6px; border: 1.5px solid var(--adm-primary); font-family: monospace; font-size: 15px; font-weight: 700; text-transform: uppercase;">
                    </div>
                    <button type="submit" class="btn-dash btn-dash-primary" style="width: 100%; padding: 10px; font-size: 13px;">
                        ✓ Approve Physical Inspection &amp; Unlock Payment
                    </button>
                </form>

                <h4 style="font-size: 13px; font-weight: 700; margin-bottom: 10px;">Pending In-Shop Customer Reservations:</h4>
                <div class="dash-table-responsive">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Item</th>
                                <th>Customer</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pendingInspections as $res)
                                <tr>
                                    <td><strong style="font-family: monospace; color: var(--adm-primary);">{{ $res->reservation_code }}</strong></td>
                                    <td>{{ $res->product->name ?? 'Item' }}</td>
                                    <td>{{ $res->customer->name ?? 'Shopper' }}</td>
                                    <td>
                                        <button type="button" class="btn-dash btn-dash-outline" style="padding: 4px 8px; font-size: 11px;" onclick="document.querySelector('input[name=reservation_code]').value='{{ $res->reservation_code }}'">
                                            Auto-Fill ▾
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--adm-text-muted); padding: 14px;">No reservations waiting right now.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Desk 2: Rider Handover OTP Custody Verification -->
        <div class="dash-card" id="handover-desk" style="border-top: 3.5px solid var(--adm-success); margin-bottom: 0;">
            <div class="dash-card-header">
                <div class="dash-card-title">
                    <span>2️⃣</span>
                    <div>
                        <div>Rider Handover Custody Verification</div>
                        <span class="dash-card-subtitle">Verify courier's 6-digit pickup OTP to transfer custody.</span>
                    </div>
                </div>
                <span class="dash-badge dash-badge-success">Release Goods</span>
            </div>

            <div class="dash-card-body">
                <p style="font-size: 12.5px; color: var(--adm-text-muted); margin-bottom: 16px;">
                    When an authorized courier arrives to pick up a paid parcel for directional LGA delivery, request their 6-digit pickup OTP. Entering this code securely transfers custody to the logistics fleet.
                </p>

                <form method="POST" action="{{ route('counter.verify.handover.otp') }}" style="background: #F8FAFC; padding: 18px; border-radius: 8px; border: 1px solid var(--adm-border); margin-bottom: 20px;">
                    @csrf
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px;">Select Staged Order:</label>
                        <select name="order_id" required style="width: 100%; padding: 9px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: #FFF;">
                            @foreach($stagedOrders as $ord)
                                <option value="{{ $ord->id }}">
                                    Order #{{ $ord->order_number }} — {{ $ord->customer->name ?? 'Shopper' }} (₦{{ number_format($ord->total_amount, 2) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px;">Rider Handover OTP (6 Digits):</label>
                        <input type="text" name="handover_otp" placeholder="e.g. 654920" maxlength="6" required style="width: 100%; padding: 10px 14px; border-radius: 6px; border: 1.5px solid var(--adm-success); font-family: monospace; font-size: 16px; font-weight: 700; text-align: center; letter-spacing: 3px;">
                    </div>

                    <button type="submit" class="btn-dash btn-dash-success" style="width: 100%; padding: 10px; font-size: 13px;">
                        🔑 Confirm Handover OTP &amp; Release Package
                    </button>
                </form>

                <h4 style="font-size: 13px; font-weight: 700; margin-bottom: 10px;">Staged Packages Ready for Pickup:</h4>
                <div class="dash-table-responsive">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Expected OTP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stagedOrders as $order)
                                <tr>
                                    <td><strong style="font-family: monospace;">{{ $order->order_number }}</strong></td>
                                    <td>{{ $order->customer->name ?? 'Customer' }}</td>
                                    <td>&#8358;{{ number_format($order->total_amount, 2) }}</td>
                                    <td>
                                        <span class="dash-badge dash-badge-purple" style="font-family: monospace;">
                                            {{ $order->pickup_verification_code ?? '654920' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--adm-text-muted); padding: 14px;">No packages currently staged for pickup.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Desk 3: Today's Completed Inspections Record -->
    <div class="dash-card" id="inspection-records">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>📋</span>
                <div>
                    <div>Today's In-Shop Inspection Log</div>
                    <span class="dash-card-subtitle">Verified physical examination logs recorded at this branch counter.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-success">Audit Complete</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Reservation Code</th>
                        <th>Product Name</th>
                        <th>Customer Contact</th>
                        <th>Inspection Verdict</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inspectedToday as $item)
                        <tr>
                            <td style="font-family: monospace; font-weight: 700;">{{ $item->reservation_code }}</td>
                            <td>{{ $item->product->name ?? 'Item' }}</td>
                            <td>{{ $item->customer->name ?? 'Shopper' }} ({{ $item->customer->phone ?? 'N/A' }})</td>
                            <td>
                                <span class="dash-badge dash-badge-success">
                                    ✓ Physical Inspection Passed
                                </span>
                            </td>
                            <td style="color: var(--adm-text-muted); font-size: 12px;">{{ $item->updated_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--adm-text-muted); padding: 20px;">No inspections completed yet today.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
