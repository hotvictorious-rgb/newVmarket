@extends('layouts.dashboard')

@section('title', 'Rider Handover OTP Desk — Counter Staff')
@section('role_title', 'Counter Staff')
@section('page_title', 'Rider Handover Custody Verification')
@section('role_badge', 'COUNTER STAFF')
@section('role_badge_class', 'role-badge-counter')

@section('sidebar_menu')
    @include('counter.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('counter.dashboard') }}">Counter Terminal</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Rider Handover OTP Desk</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🔑</span>
                <span>Rider Handover Custody Verification</span>
            </h1>
            <p class="page-header-subtitle">
                Authorize package release and display the 6-digit cryptographic OTP to the courier, or verify courier's pickup directly at the counter terminal.
            </p>
        </div>
        <div>
            <span class="dash-badge dash-badge-success" style="padding: 6px 14px; font-size: 12.5px; font-weight: 700;">
                {{ count($stagedOrders) }} Packages Staged &amp; Ready
            </span>
        </div>
    </div>

    @if(session('success'))
        <div style="background: #ECFDF5; border-left: 4px solid #10B981; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; color: #065F46; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; justify-content: space-between;">
            <div>
                ✅ {{ session('success') }}
                @if(session('pickup_otp'))
                    <span style="margin-left: 10px; background: #065F46; color: #FFD700; padding: 4px 10px; border-radius: 6px; font-family: monospace; font-size: 16px; letter-spacing: 2px;">
                        OTP: {{ session('pickup_otp') }}
                    </span>
                @endif
            </div>
            @if(session('pickup_otp'))
                <button type="button" onclick="navigator.clipboard.writeText('{{ session('pickup_otp') }}'); alert('Pickup OTP copied: {{ session('pickup_otp') }}');" style="background: rgba(6, 95, 70, 0.15); border: 1px solid #065F46; color: #065F46; font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 6px; cursor: pointer;">
                    📋 Copy OTP
                </button>
            @endif
        </div>
    @endif
    @if(session('status'))
        <div style="background: #ECFDF5; border-left: 4px solid #10B981; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; color: #065F46; font-size: 13.5px; font-weight: 600;">
            ✅ {{ session('status') }}
        </div>
    @endif
    @if(session('error'))
        <div style="background: #FEF2F2; border-left: 4px solid #EF4444; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; color: #991B1B; font-size: 13.5px; font-weight: 600;">
            ❌ {{ session('error') }}
        </div>
    @endif

    <!-- Staged Packages Table with Handover Actions -->
    <div class="dash-card" style="margin-bottom: 24px;">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Packages Staged for Courier Collection ({{ count($stagedOrders) }})</div>
                <span class="dash-card-subtitle">Click "Rider Arrived" when courier is at the counter to reveal their secret 6-digit verification code</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order # &amp; Date</th>
                        <th>Customer &amp; Phone</th>
                        <th>Order Value</th>
                        <th>Fulfillment</th>
                        <th>Handover Status &amp; 6-Digit Code</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stagedOrders as $order)
                        <tr id="counterRow-{{ $order->order_number }}">
                            <td>
                                <div style="font-weight: 700; color: var(--adm-primary);">{{ $order->order_number }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $order->created_at->format('d M, Y H:i') }}</span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $order->customer->name ?? 'Shopper' }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $order->customer->phone ?? 'N/A' }}</span>
                            </td>
                            <td style="font-weight: 800; color: var(--adm-text);">
                                ₦{{ number_format($order->total_amount, 2) }}
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ ucwords(str_replace('_', ' ', $order->fulfillment_type)) }}</span>
                            </td>
                            <td id="counterHandshakeCol-{{ $order->order_number }}" style="min-width: 260px;">
                                @if(in_array($order->order_status, ['out_for_delivery', 'delivered']) || $order->pickup_confirmed_by_rider)
                                    <div style="background: #F0FDF4; border: 1px solid #BBF7D0; padding: 8px 12px; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <span style="color: #16A34A; font-weight: 800; font-size: 12px;">✓ Custody Released</span>
                                            <span style="font-size: 11px; color: #64748B;">({{ $order->picked_up_at ? $order->picked_up_at->format('h:i A') : 'Verified' }})</span>
                                        </div>
                                        <div style="font-size: 11.5px; color: #334155; margin-top: 3px;">
                                            Courier: <strong>{{ $order->rider->user->name ?? 'Assigned Rider' }}</strong>
                                        </div>
                                    </div>
                                @elseif($order->isVendorHandoverReady())
                                    <div style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); border: 1.5px solid #F59E0B; border-radius: 10px; padding: 10px 14px; color: #FFFFFF;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                            <span style="font-size: 10.5px; font-weight: 800; color: #FBBF24; text-transform: uppercase;">
                                                🔑 SHOW TO COURIER
                                            </span>
                                            <span style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #10B981;"></span>
                                        </div>
                                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                                            <span style="font-family: monospace; font-size: 22px; font-weight: 900; color: #FFD700; letter-spacing: 4px;">
                                                {{ $order->pickup_verification_code }}
                                            </span>
                                            <button type="button" onclick="navigator.clipboard.writeText('{{ $order->pickup_verification_code }}'); alert('Code copied: {{ $order->pickup_verification_code }}');" style="background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.3); color: #FFFFFF; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer;">
                                                Copy
                                            </button>
                                        </div>
                                        <p style="font-size: 10.5px; color: #CBD5E1; margin: 4px 0 0; line-height: 1.3;">
                                            Courier inputs this code on their mobile phone to take custody.
                                        </p>
                                    </div>
                                @else
                                    <form method="POST" action="{{ route('counter.orders.handover_ready', $order->id) }}">
                                        @csrf
                                        <button type="submit" class="btn-dash btn-dash-primary" style="padding: 8px 14px; font-size: 12px; font-weight: 700; width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px; background: linear-gradient(135deg, #10B981 0%, #059669 100%); border: none;">
                                            <span>🏍️</span>
                                            <span>Courier Arrived? Reveal Code</span>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No packages staged for courier pickup.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Manual Counter Terminal Fallback -->
    <div class="dash-card" style="border-top: 3.5px solid var(--adm-primary); max-width: 600px;">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Terminal Direct Override Verification</div>
                <span class="dash-card-subtitle">Use if the courier's phone battery is drained or cannot access mobile network</span>
            </div>
        </div>
        <div class="dash-card-body">
            <form method="POST" action="{{ route('counter.verify.handover.otp') }}">
                @csrf
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px;">
                        Order Tracking Number
                    </label>
                    <input type="text" name="order_number" required class="form-control" style="width: 100%; padding: 10px 14px; border: 1.5px solid var(--adm-border); border-radius: 8px; font-weight: 700;" placeholder="e.g. VM-20261015-001">
                </div>
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px;">
                        Merchant Pickup Verification OTP
                    </label>
                    <input type="text" name="pickup_otp" maxlength="6" required class="form-control" style="width: 100%; padding: 10px 14px; border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: 18px; font-weight: 800; letter-spacing: 4px; font-family: monospace;" placeholder="••••••">
                </div>
                <button type="submit" class="btn-dash btn-dash-primary" style="padding: 10px 20px; font-size: 13.5px; font-weight: 700;">
                    🔑 Terminal Direct Verification &amp; Release
                </button>
            </form>
        </div>
    </div>

    <script>
        // Live Auto-Polling for Counter Handover
        const stagedOrderNumbers = [
            @foreach($stagedOrders as $o)
                @if(in_array($o->order_status, ['confirmed', 'processing', 'ready_for_pickup']))
                    '{{ $o->order_number }}',
                @endif
            @endforeach
        ];

        if (stagedOrderNumbers.length > 0) {
            setInterval(function() {
                stagedOrderNumbers.forEach(function(orderNum) {
                    fetch('/order/' + orderNum + '/pickup-handshake-status')
                        .then(res => res.json())
                        .then(data => {
                            if (data.is_picked_up) {
                                const col = document.getElementById('counterHandshakeCol-' + orderNum);
                                if (col) {
                                    col.innerHTML = `
                                        <div style="background: #F0FDF4; border: 1px solid #BBF7D0; padding: 8px 12px; border-radius: 8px;">
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="color: #16A34A; font-weight: 800; font-size: 12px;">✓ Custody Released</span>
                                                <span style="font-size: 11px; color: #64748B;">(${data.picked_up_at || 'Just now'})</span>
                                            </div>
                                            <div style="font-size: 11.5px; color: #334155; margin-top: 3px;">
                                                Courier: <strong>${data.rider_name || 'Assigned Courier'}</strong>
                                            </div>
                                        </div>
                                    `;
                                }
                            }
                        })
                        .catch(err => console.debug('Handshake poll error:', err));
                });
            }, 4000);
        }
    </script>
@endsection