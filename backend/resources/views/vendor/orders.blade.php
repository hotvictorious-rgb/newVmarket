@extends('layouts.dashboard')

@section('title', 'Customer Orders — Vendor')
@section('role_title', 'Merchant Commerce Suite')
@section('page_title', 'Customer Orders')
@section('role_badge', 'VENDOR')
@section('role_badge_class', 'role-badge-vendor')

@section('sidebar_menu')
    @include('vendor.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('vendor.dashboard') }}">Merchant</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Customer Orders</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🛍️</span>
                <span>Customer Orders</span>
            </h1>
            <p class="page-header-subtitle">
                Escrow-backed customer purchases awaiting counter packaging or directional logistics courier pickup.
            </p>
        </div>
        <div>
            <span class="dash-badge dash-badge-gold" style="padding: 7px 14px; font-size: 12.5px; font-weight: 700;">
                ⚡ Dual-Custody Handshake Active
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
    @if(session('error'))
        <div style="background: #FEF2F2; border-left: 4px solid #EF4444; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; color: #991B1B; font-size: 13.5px; font-weight: 600;">
            ❌ {{ session('error') }}
        </div>
    @endif

    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">All Customer Orders ({{ count($orders) }})</div>
                <span class="dash-card-subtitle">Transactions protected by Victorious Escrow Guarantee &amp; Dual OTP Handshakes</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order # &amp; Date</th>
                        <th>Customer</th>
                        <th>Gross Total</th>
                        <th>90% Merchant Cut</th>
                        <th>Fulfillment</th>
                        <th>Status</th>
                        <th>Courier Handshake (Plaza Counter)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr id="orderRow-{{ $order->order_number }}">
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
                            <td style="font-weight: 800; color: #059669;">
                                ₦{{ number_format($order->vendor_earning_90, 2) }}
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ ucwords(str_replace('_', ' ', $order->fulfillment_type)) }}</span>
                            </td>
                            <td id="orderStatusCol-{{ $order->order_number }}">
                                @if($order->order_status === 'delivered')
                                    <span class="dash-badge dash-badge-success">Delivered</span>
                                @elseif($order->order_status === 'out_for_delivery')
                                    <span class="dash-badge dash-badge-purple">Out for Delivery</span>
                                @elseif($order->order_status === 'ready_for_pickup')
                                    <span class="dash-badge dash-badge-gold">Ready for Pickup</span>
                                @elseif($order->order_status === 'processing')
                                    <span class="dash-badge dash-badge-gold">Processing</span>
                                @else
                                    <span class="dash-badge dash-badge-info">{{ ucwords($order->order_status) }}</span>
                                @endif
                            </td>
                            <td id="orderHandshakeCol-{{ $order->order_number }}" style="min-width: 250px;">
                                @if($order->fulfillment_type === 'in_shop_pickup')
                                    <span style="font-size: 12px; color: var(--adm-text-muted);">Customer in-shop collection</span>
                                @elseif(in_array($order->order_status, ['out_for_delivery', 'delivered']) || $order->pickup_confirmed_by_rider)
                                    <!-- Handover already completed -->
                                    <div style="background: #F0FDF4; border: 1px solid #BBF7D0; padding: 8px 12px; border-radius: 8px;">
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <span style="color: #16A34A; font-weight: 800; font-size: 12px;">✓ Package Released</span>
                                            <span style="font-size: 11px; color: #64748B;">({{ $order->picked_up_at ? $order->picked_up_at->format('h:i A') : 'Verified' }})</span>
                                        </div>
                                        <div style="font-size: 11.5px; color: #334155; margin-top: 3px;">
                                            Courier: <strong>{{ $order->rider->user->name ?? 'Assigned Rider' }}</strong>
                                        </div>
                                        @if($order->pickup_proof_image)
                                            <button type="button" onclick="openProofModal('{{ $order->pickup_proof_image }}', '{{ $order->order_number }}')" style="margin-top: 6px; background: #FFFFFF; border: 1px solid #CBD5E1; color: #0F172A; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                                <span>📸 View Counter Photo</span>
                                            </button>
                                        @endif
                                    </div>
                                @elseif($order->isVendorHandoverReady())
                                    <!-- Vendor clicked handover ready: reveal secret OTP to show to courier -->
                                    <div style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); border: 1.5px solid #F59E0B; border-radius: 10px; padding: 10px 14px; color: #FFFFFF;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                            <span style="font-size: 10.5px; font-weight: 800; color: #FBBF24; text-transform: uppercase;">
                                                🔑 SHOW TO COURIER
                                            </span>
                                            <span class="pulse-dot" style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #10B981;"></span>
                                        </div>
                                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                                            <span style="font-family: monospace; font-size: 22px; font-weight: 900; color: #FFD700; letter-spacing: 4px;">
                                                {{ $order->pickup_verification_code }}
                                            </span>
                                            <button type="button" onclick="navigator.clipboard.writeText('{{ $order->pickup_verification_code }}'); alert('PIN copied: {{ $order->pickup_verification_code }}');" style="background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.3); color: #FFFFFF; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer;">
                                                Copy
                                            </button>
                                        </div>
                                        <p style="font-size: 10.5px; color: #CBD5E1; margin: 4px 0 0; line-height: 1.3;">
                                            Courier enters this PIN on their phone to verify package condition and release custody.
                                        </p>
                                    </div>
                                @else
                                    <!-- Not yet marked ready: Click button when rider arrives at counter -->
                                    <form method="POST" action="{{ route('vendor.orders.handover_ready', $order->id) }}" onsubmit="return confirm('Authorize package handover for Order {{ $order->order_number }}? This will display the 6-digit pickup code for the courier.');">
                                        @csrf
                                        <button type="submit" class="btn-dash btn-dash-primary" style="padding: 7px 12px; font-size: 12px; font-weight: 700; width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px; background: linear-gradient(135deg, #4F46E5 0%, #3730A3 100%); border: none;">
                                            <span>🏍️</span>
                                            <span>Rider Arrived? Click Handover</span>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No customer orders recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Package Proof Photo Modal -->
    <div id="proofPhotoModal" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.7); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: #FFFFFF; border-radius: 16px; max-width: 480px; width: 100%; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
            <div style="padding: 16px 20px; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 15px; font-weight: 800; margin: 0; color: #0F172A;" id="proofModalTitle">📸 Package Condition at Pickup</h3>
                <button type="button" onclick="closeProofModal()" style="background: transparent; border: none; font-size: 20px; cursor: pointer; color: #64748B;">&times;</button>
            </div>
            <div style="padding: 20px; text-align: center;">
                <img id="proofModalImg" src="" alt="Pickup Proof" style="max-width: 100%; max-height: 380px; object-fit: contain; border-radius: 10px; border: 1px solid #CBD5E1;">
                <p style="font-size: 12px; color: #64748B; margin: 12px 0 0;">
                    Snapped by courier on mobile device at branch counter prior to departure.
                </p>
            </div>
            <div style="padding: 12px 20px; background: #F8FAFC; border-top: 1px solid #E2E8F0; text-align: right;">
                <button type="button" onclick="closeProofModal()" style="background: #0F172A; color: #FFFFFF; font-weight: 700; font-size: 12.5px; padding: 7px 16px; border-radius: 8px; border: none; cursor: pointer;">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        function openProofModal(imgSrc, orderNumber) {
            document.getElementById('proofModalImg').src = imgSrc;
            document.getElementById('proofModalTitle').innerText = '📸 Counter Proof: ' + orderNumber;
            document.getElementById('proofPhotoModal').style.display = 'flex';
        }

        function closeProofModal() {
            document.getElementById('proofPhotoModal').style.display = 'none';
        }

        // Live Auto-Polling for Active Handover Orders
        const activeOrders = [
            @foreach($orders as $o)
                @if(in_array($o->order_status, ['confirmed', 'processing', 'ready_for_pickup']))
                    '{{ $o->order_number }}',
                @endif
            @endforeach
        ];

        if (activeOrders.length > 0) {
            setInterval(function() {
                activeOrders.forEach(function(orderNum) {
                    fetch('/order/' + orderNum + '/pickup-handshake-status')
                        .then(res => res.json())
                        .then(data => {
                            if (data.is_picked_up) {
                                const statusCol = document.getElementById('orderStatusCol-' + orderNum);
                                const handshakeCol = document.getElementById('orderHandshakeCol-' + orderNum);
                                
                                if (statusCol) {
                                    statusCol.innerHTML = '<span class="dash-badge dash-badge-purple">Out for Delivery</span>';
                                }
                                if (handshakeCol) {
                                    let photoBtn = '';
                                    if (data.pickup_proof_image) {
                                        photoBtn = '<button type="button" onclick="openProofModal(\'' + data.pickup_proof_image + '\', \'' + orderNum + '\')" style="margin-top: 6px; background: #FFFFFF; border: 1px solid #CBD5E1; color: #0F172A; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;"><span>📸 View Counter Photo</span></button>';
                                    }
                                    handshakeCol.innerHTML = `
                                        <div style="background: #F0FDF4; border: 1px solid #BBF7D0; padding: 8px 12px; border-radius: 8px;">
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="color: #16A34A; font-weight: 800; font-size: 12px;">✓ Package Released</span>
                                                <span style="font-size: 11px; color: #64748B;">(${data.picked_up_at || 'Just now'})</span>
                                            </div>
                                            <div style="font-size: 11.5px; color: #334155; margin-top: 3px;">
                                                Courier: <strong>${data.rider_name || 'Assigned Courier'}</strong>
                                            </div>
                                            ${photoBtn}
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