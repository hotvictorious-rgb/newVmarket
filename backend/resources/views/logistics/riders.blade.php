@extends('layouts.dashboard')

@section('title', 'Rider Roster & Shifts — Logistics Fleet')
@section('role_title', 'Logistics Fleet Operations Center')
@section('page_title', 'Rider Roster & Shifts')
@section('role_badge', 'LOGISTICS HQ')
@section('role_badge_class', 'role-badge-logistics')

@section('sidebar_menu')
    @include('logistics.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('logistics.dashboard') }}">Logistics Fleet</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Rider Roster &amp; Shifts</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏍️</span>
                <span>Rider Roster &amp; Corporate Payroll Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Active dispatch couriers, assigned motorbikes, assigned LGA delivery corridors, and closed-loop fleet payroll disbursements.
            </p>
        </div>
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <div style="background: rgba(16, 185, 129, 0.1); border: 1.5px solid #10B981; padding: 6px 14px; border-radius: 8px;">
                <span style="font-size: 11px; font-weight: 700; color: #047857; text-transform: uppercase;">Corporate Wallet:</span>
                <strong style="font-size: 14px; color: #047857; margin-left: 4px;">₦{{ number_format((float)($company->wallet_balance ?? 0), 2) }}</strong>
            </div>
            <span class="dash-badge dash-badge-success" style="padding: 6px 14px; font-size: 12.5px; font-weight: 700;">
                {{ count($activeRiders) }} Couriers on Roster
            </span>
        </div>
    </div>

    @if(session('status'))
        <div style="margin-bottom: 20px; padding: 12px 18px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); border: 1px solid #10B981; color: #047857; font-weight: 600; font-size: 13px;">
            ✅ {{ session('status') }}
        </div>
    @endif
    @if(session('error'))
        <div style="margin-bottom: 20px; padding: 12px 18px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border: 1px solid #EF4444; color: #B91C1C; font-weight: 600; font-size: 13px;">
            ⚠️ {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div style="margin-bottom: 20px; padding: 12px 18px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border: 1px solid #EF4444; color: #B91C1C; font-weight: 600; font-size: 13px;">
            <ul style="margin: 0; padding-left: 18px;">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Registered Fleet Couriers ({{ count($activeRiders) }})</div>
                <span class="dash-card-subtitle">Couriers authorized for directional LGA transit, OTP doorstep deliveries, and direct fleet disbursements</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Courier Name &amp; Contact</th>
                        <th>Vehicle &amp; Plate</th>
                        <th>Assigned LGA Corridor</th>
                        <th>Duty Status</th>
                        <th>Courier Wallet</th>
                        <th style="text-align: right;">Fleet Payroll Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeRiders as $rider)
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-text); font-size: 14px;">{{ $rider->user->name ?? 'Fleet Rider' }}</div>
                                <span style="font-size: 12px; color: var(--adm-text-muted);">{{ $rider->user->phone ?? '08000000000' }}</span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $rider->vehicle_type ?? 'Motorcycle' }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-primary); font-family: monospace; font-weight: 700;">{{ $rider->vehicle_plate_number ?? 'UYO-123-AK' }}</span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ $rider->assignedLga->name ?? 'Uyo' }} LGA Corridor</span>
                            </td>
                            <td>
                                @if($rider->is_online)
                                    <span class="dash-badge dash-badge-success">● ON DUTY</span>
                                @else
                                    <span class="dash-badge dash-badge-gold">○ STANDBY / OFF</span>
                                @endif
                            </td>
                            <td>
                                <strong style="font-size: 13.5px; color: var(--adm-text); font-family: monospace;">₦{{ number_format((float)($rider->current_balance ?? 0), 2) }}</strong>
                                <div style="font-size: 10.5px; color: var(--adm-text-muted);">Courier Balance</div>
                            </td>
                            <td style="text-align: right;">
                                <button onclick="openPayModal('{{ $rider->id }}', '{{ addslashes($rider->user->name ?? 'Courier') }}', '{{ number_format((float)($rider->current_balance ?? 0), 2) }}')" 
                                        class="btn-dash btn-dash-success" 
                                        style="padding: 5px 12px; font-size: 12px; font-weight: 700;">
                                    💸 Disburse Pay
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No couriers registered on this fleet roster yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal for Fleet Payroll Disbursement -->
    <div id="disbursePayModal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0, 0, 0, 0.6); align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div style="background: var(--adm-card-bg, #ffffff); border-radius: 14px; max-width: 480px; width: 92%; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); border: 1.5px solid var(--adm-card-border, #E2E8F0);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;">💼</span>
                    <div>
                        <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--adm-text);">Disburse Fleet Payroll</h3>
                        <span style="font-size: 11.5px; color: var(--adm-text-muted);">Closed-loop zero-sum corporate wallet transfer</span>
                    </div>
                </div>
                <button type="button" onclick="closePayModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--adm-text-muted);">&times;</button>
            </div>

            <form action="{{ route('logistics.riders.disburse') }}" method="POST">
                @csrf
                <input type="hidden" name="rider_id" id="modalRiderId">

                <div style="background: rgba(94, 23, 235, 0.05); border: 1px solid rgba(94, 23, 235, 0.2); border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                    <div style="font-size: 12px; color: var(--adm-text-muted);">Recipient Courier:</div>
                    <strong id="modalRiderName" style="font-size: 14px; color: var(--adm-primary);"></strong>
                    <div style="font-size: 11.5px; color: var(--adm-text-muted); margin-top: 4px;">Current Balance: ₦<span id="modalRiderBalance">0.00</span></div>
                    <div style="font-size: 11.5px; color: #047857; margin-top: 2px;">Your Available Corporate Wallet: <strong>₦{{ number_format((float)($company->wallet_balance ?? 0), 2) }}</strong></div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px;">Payout Amount (₦) *</label>
                    <input type="number" name="amount" step="0.01" min="100" max="{{ (float)($company->wallet_balance ?? 0) }}" placeholder="e.g. 5000.00" required 
                           style="width: 100%; padding: 10px 12px; border: 1.5px solid var(--adm-card-border, #CBD5E1); border-radius: 8px; font-size: 14px; font-weight: 700; color: var(--adm-text);">
                    <span style="font-size: 11px; color: var(--adm-text-muted); margin-top: 4px; display: block;">Debited directly from company corporate wallet; maximum ₦{{ number_format((float)($company->wallet_balance ?? 0), 2) }}.</span>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px;">Disbursement Purpose / Memo</label>
                    <input type="text" name="notes" placeholder="e.g. Weekly shift delivery payout" 
                           style="width: 100%; padding: 9px 12px; border: 1.5px solid var(--adm-card-border, #CBD5E1); border-radius: 8px; font-size: 13px; color: var(--adm-text);">
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closePayModal()" class="btn-dash btn-dash-outline" style="padding: 8px 16px; font-size: 12.5px;">Cancel</button>
                    <button type="submit" class="btn-dash btn-dash-success" style="padding: 8px 18px; font-size: 12.5px; font-weight: 800;">Confirm Disbursement</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPayModal(riderId, riderName, riderBalance) {
            document.getElementById('modalRiderId').value = riderId;
            document.getElementById('modalRiderName').innerText = riderName;
            document.getElementById('modalRiderBalance').innerText = riderBalance;
            document.getElementById('disbursePayModal').style.display = 'flex';
        }

        function closePayModal() {
            document.getElementById('disbursePayModal').style.display = 'none';
        }
    </script>
@endsection