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

    @include('vendor.partials._shop_switcher')

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
                        <th>Wholesale / Net Cut</th>
                        <th>Fulfillment</th>
                        <th>Status</th>
                        <th>Courier Handshake (Shop Counter)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr id="orderRow-{{ $order->order_number }}">
                            <td>
                                <div style="font-weight: 700; color: var(--adm-primary);">{{ $order->order_number }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $order->created_at->format('d M, Y H:i') }}</span>
                                @if($order->shop)
                                    <div style="font-size: 11px; color: #0284C7; font-weight: 600; margin-top: 2px;">
                                        🏬 {{ $order->shop->name }} (📍 {{ $order->shop->lga->name ?? 'LGA' }})
                                    </div>
                                @endif
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
                                        <div style="margin-top: 6px; display: flex; gap: 6px; flex-wrap: wrap;">
                                            @php
                                                $vendorAuditModalData = [
                                                    'order_number' => $order->order_number,
                                                    'status' => ucfirst(str_replace('_', ' ', $order->order_status)),
                                                    'customer' => $order->customer->name ?? 'Shopper',
                                                    'rider' => $order->rider->user->name ?? 'Assigned Courier',
                                                    'pickup_photo' => $order->pickup_proof_image ? asset($order->pickup_proof_image) : null,
                                                    'picked_up_at' => $order->picked_up_at ? $order->picked_up_at->format('M d, h:i A') : null,
                                                    'delivery_photo' => $order->proof_of_delivery_image ? asset($order->proof_of_delivery_image) : null,
                                                    'delivered_at' => $order->received_at ? $order->received_at->format('M d, h:i A') : null,
                                                    'attempt_photo' => $order->delivery_attempt_photo_path ? asset($order->delivery_attempt_photo_path) : null,
                                                    'attempt_at' => $order->last_delivery_attempt_at ? $order->last_delivery_attempt_at->format('M d, h:i A') : null,
                                                    'rejection_photos' => is_array($order->doorstep_rejection_photos) ? array_map(fn($p) => asset($p), $order->doorstep_rejection_photos) : [],
                                                    'item_defects' => $order->items ? $order->items->whereNotNull('defect_photo_path')->map(fn($it) => [
                                                        'name' => $it->product_name,
                                                        'status' => $it->item_delivery_status ?? $it->fulfillment_status ?? 'pending',
                                                        'photo' => asset($it->defect_photo_path),
                                                    ])->values()->all() : [],
                                                    'return_photo' => (isset($order->returnCases) && $order->returnCases->isNotEmpty() && $order->returnCases->first()->vendor_return_photo_path) ? asset($order->returnCases->first()->vendor_return_photo_path) : null,
                                                ];
                                            @endphp
                                            <button type="button" 
                                                onclick='openVendorAuditModal(@json($vendorAuditModalData))'
                                                style="background: #FFFFFF; border: 1.5px solid #6366F1; color: #4338CA; font-size: 11px; font-weight: 700; padding: 4px 9px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                                                <span>📸 Handshake Audit Proofs</span>
                                                @php
                                                    $vendorProofCount = ($order->pickup_proof_image ? 1 : 0) 
                                                        + ($order->proof_of_delivery_image ? 1 : 0) 
                                                        + ($order->delivery_attempt_photo_path ? 1 : 0)
                                                        + (isset($order->items) && $order->items->whereNotNull('defect_photo_path')->count() > 0 ? 1 : 0);
                                                @endphp
                                                @if($vendorProofCount > 0)
                                                    <span style="background: #EEF2FF; color: #4F46E5; border-radius: 999px; padding: 1px 5px; font-size: 9.5px; font-weight: 800;">{{ $vendorProofCount }}</span>
                                                @endif
                                            </button>
                                        </div>
                                    </div>
                                @else
                                    <!-- Doorstep delivery order: Assign own rider & Handover -->
                                    <div style="margin-bottom: 8px; background: #F8FAFC; border: 1px solid #E2E8F0; padding: 8px 10px; border-radius: 8px;">
                                        <div style="font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 5px; display: flex; align-items: center; justify-content: space-between;">
                                            <span>🏍️ Courier:</span>
                                            <span style="font-weight: 800; color: {{ $order->rider ? '#059669' : '#D97706' }};">
                                                {{ $order->rider->user->name ?? 'Unassigned' }}
                                            </span>
                                        </div>
                                        @if(isset($riders) && $riders->isNotEmpty())
                                            <form method="POST" action="{{ route('vendor.orders.assign_rider', $order->id) }}" style="display: flex; gap: 4px; margin-bottom: 4px;">
                                                @csrf
                                                <select name="rider_id" required style="font-size: 11px; padding: 4px 6px; border-radius: 6px; border: 1px solid #CBD5E1; background: #FFF; flex: 1;">
                                                    <option value="">-- Assign Rider --</option>
                                                    @foreach($riders as $r)
                                                        <option value="{{ $r->id }}" {{ (int)$order->rider_id === (int)$r->id ? 'selected' : '' }}>
                                                            {{ $r->user->name }} ({{ $r->vehicle_type ?? 'motorcycle' }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" style="background: #4F46E5; color: #FFF; border: none; border-radius: 6px; padding: 4px 8px; font-size: 11px; font-weight: 700; cursor: pointer;">
                                                    {{ $order->rider_id ? 'Reassign' : 'Assign' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>

                                    @if($order->isVendorHandoverReady())
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

    <!-- Vendor Visual Audit Dossier Modal -->
    <div id="vendorAuditModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75); z-index: 99999; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px);">
        <div style="background: #FFFFFF; border-radius: 20px; max-width: 800px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); border: 1px solid #E2E8F0;">
            
            <!-- Modal Header -->
            <div style="padding: 20px 24px; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center; background: #0F172A; color: #FFFFFF; border-radius: 20px 20px 0 0;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 18px;">📸</span>
                        <h3 style="font-size: 16px; font-weight: 800; margin: 0; color: #FFFFFF;" id="vendorModalTitle">Order Photographic Audit Trail</h3>
                    </div>
                    <span style="font-size: 11.5px; color: #94A3B8; margin-top: 3px; display: block;">
                        Immutable Multi-Party Evidence &bull; Locked upon Handshake Verification
                    </span>
                </div>
                <button type="button" onclick="closeVendorAuditModal()" style="background: rgba(255,255,255,0.1); border: none; color: #FFFFFF; width: 32px; height: 32px; border-radius: 50%; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center;">&times;</button>
            </div>

            <!-- Immutability Security Banner -->
            <div style="background: #ECFDF5; border-bottom: 1px solid #A7F3D0; padding: 10px 24px; display: flex; align-items: center; gap: 10px; font-size: 11.5px; color: #065F46; font-weight: 700;">
                <span>🛡️</span>
                <span>Immutable Proofs: Live camera photos taken at counter pickup and doorstep handover are permanently preserved and cannot be tampered with or deleted.</span>
            </div>

            <!-- Modal Body -->
            <div style="padding: 24px;" id="vendorModalBody">
                <!-- Injected via JavaScript -->
            </div>

            <!-- Modal Footer -->
            <div style="padding: 14px 24px; background: #F8FAFC; border-top: 1px solid #E2E8F0; display: flex; justify-content: flex-end; border-radius: 0 0 20px 20px;">
                <button type="button" onclick="closeVendorAuditModal()" style="background: #0F172A; color: #FFFFFF; font-weight: 700; font-size: 12px; padding: 8px 18px; border-radius: 8px; border: none; cursor: pointer;">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        function openVendorAuditModal(data) {
            document.getElementById('vendorModalTitle').innerText = 'Handshake Proofs: ' + data.order_number + ' (' + data.status + ')';
            
            let html = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px;">
                    <div style="background: #F8FAFC; padding: 12px; border-radius: 10px; border: 1px solid #E2E8F0;">
                        <span style="font-size: 10.5px; color: #64748B; font-weight: 700; display: block; text-transform: uppercase;">Customer</span>
                        <strong style="font-size: 13px; color: #0F172A;">${data.customer}</strong>
                    </div>
                    <div style="background: #F8FAFC; padding: 12px; border-radius: 10px; border: 1px solid #E2E8F0;">
                        <span style="font-size: 10.5px; color: #64748B; font-weight: 700; display: block; text-transform: uppercase;">Courier</span>
                        <strong style="font-size: 13px; color: #0F172A;">${data.rider}</strong>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 16px;">
            `;

            // Leg 0: Courier Merchant Pickup Counter Photo
            if (data.pickup_photo) {
                html += `
                    <div style="background: #FFFFFF; border: 1.5px solid #E2E8F0; border-radius: 14px; padding: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <strong style="font-size: 13.5px; color: #0F172A;">🏬 Shop Counter Pickup Snap (Leg 0)</strong>
                            <span style="font-size: 11.5px; color: #64748B;">${data.picked_up_at || 'Verified'}</span>
                        </div>
                        <div style="display: flex; gap: 14px; align-items: center;">
                            <img src="${data.pickup_photo}" alt="Pickup Proof" style="width: 140px; height: 100px; object-fit: cover; border-radius: 10px; border: 1px solid #CBD5E1;">
                            <div>
                                <span style="font-size: 12px; font-weight: 700; color: #059669; display: block;">✓ Package Released to Courier</span>
                                <p style="font-size: 11.5px; color: #64748B; margin: 4px 0 8px;">Courier authenticated counter PIN and snapped initial package condition.</p>
                                <a href="${data.pickup_photo}" target="_blank" style="font-size: 11px; font-weight: 700; color: #4F46E5; text-decoration: none;">View Full Photo ↗</a>
                            </div>
                        </div>
                    </div>
                `;
            }

            // Leg 1: Doorstep Delivery Handover Photo
            if (data.delivery_photo) {
                html += `
                    <div style="background: #FFFFFF; border: 1.5px solid #10B981; border-radius: 14px; padding: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <strong style="font-size: 13.5px; color: #0F172A;">🏡 Doorstep Delivery Snap (Leg 1)</strong>
                            <span style="font-size: 11.5px; color: #64748B;">${data.delivered_at || 'Delivered'}</span>
                        </div>
                        <div style="display: flex; gap: 14px; align-items: center;">
                            <img src="${data.delivery_photo}" alt="Delivery Proof" style="width: 140px; height: 100px; object-fit: cover; border-radius: 10px; border: 1px solid #10B981;">
                            <div>
                                <span style="font-size: 12px; font-weight: 700; color: #059669; display: block;">✓ Package Delivered to Customer</span>
                                <p style="font-size: 11.5px; color: #64748B; margin: 4px 0 8px;">Customer verified PIN at doorstep. Condition captured live by rider.</p>
                                <a href="${data.delivery_photo}" target="_blank" style="font-size: 11px; font-weight: 700; color: #10B981; text-decoration: none;">View Full Photo ↗</a>
                            </div>
                        </div>
                    </div>
                `;
            }

            // Leg 1-Attempt: Delivery Attempt Failure Photo
            if (data.attempt_photo) {
                html += `
                    <div style="background: #FFFBEB; border: 1.5px solid #F59E0B; border-radius: 14px; padding: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <strong style="font-size: 13.5px; color: #92400E;">⚠️ Courier Doorstep Attempt Failure Snap</strong>
                            <span style="font-size: 11.5px; color: #B45309;">${data.attempt_at || 'Logged'}</span>
                        </div>
                        <div style="display: flex; gap: 14px; align-items: center;">
                            <img src="${data.attempt_photo}" alt="Attempt Proof" style="width: 140px; height: 100px; object-fit: cover; border-radius: 10px; border: 1px solid #F59E0B;">
                            <div>
                                <span style="font-size: 12px; font-weight: 700; color: #B45309; display: block;">Locked Gate / Recipient Unreachable</span>
                                <p style="font-size: 11.5px; color: #78350F; margin: 4px 0 8px;">Courier visited destination and logged physical evidence of inability to hand over.</p>
                                <a href="${data.attempt_photo}" target="_blank" style="font-size: 11px; font-weight: 700; color: #B45309; text-decoration: none;">View Full Photo ↗</a>
                            </div>
                        </div>
                    </div>
                `;
            }

            // Line-Item Doorstep Defects
            if (data.item_defects && data.item_defects.length > 0) {
                html += `
                    <div style="background: #FEF2F2; border: 1.5px solid #EF4444; border-radius: 14px; padding: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <strong style="font-size: 13.5px; color: #991B1B;">❌ Customer Doorstep Defect Rejection Snaps (${data.item_defects.length})</strong>
                            <span style="font-size: 11.5px; color: #991B1B; font-weight: 700;">Itemized Manifest Evidence</span>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; margin-top: 10px;">
                `;
                data.item_defects.forEach(function(item) {
                    html += `
                        <div style="background: #FFFFFF; border: 1px solid #FECACA; border-radius: 10px; padding: 8px; text-align: center;">
                            <img src="${item.photo}" alt="${item.name}" style="width: 100%; height: 90px; object-fit: cover; border-radius: 6px; margin-bottom: 6px;">
                            <strong style="font-size: 11px; color: #0F172A; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${item.name}</strong>
                            <span style="font-size: 10px; color: #DC2626; font-weight: 800; text-transform: uppercase;">${item.status}</span>
                        </div>
                    `;
                });
                html += `</div></div>`;
            }

            // Leg 2: Vendor Return Snap
            if (data.return_photo) {
                html += `
                    <div style="background: #FAF5FF; border: 1.5px solid #A855F7; border-radius: 14px; padding: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <strong style="font-size: 13.5px; color: #6B21A8;">🏬 Inward Return to Shop Counter (Leg 2)</strong>
                            <span style="font-size: 11.5px; color: #7E22CE; font-weight: 700;">Returned</span>
                        </div>
                        <div style="display: flex; gap: 14px; align-items: center;">
                            <img src="${data.return_photo}" alt="Return Proof" style="width: 140px; height: 100px; object-fit: cover; border-radius: 10px; border: 1px solid #A855F7;">
                            <div>
                                <span style="font-size: 12px; font-weight: 700; color: #7E22CE; display: block;">✓ Returned Package Received at Shop</span>
                                <p style="font-size: 11.5px; color: #581C87; margin: 4px 0 8px;">Courier returned parcel to shop and verified vendor OTP with live condition snap.</p>
                                <a href="${data.return_photo}" target="_blank" style="font-size: 11px; font-weight: 700; color: #7E22CE; text-decoration: none;">View Full Photo ↗</a>
                            </div>
                        </div>
                    </div>
                `;
            }

            html += `</div>`;
            document.getElementById('vendorModalBody').innerHTML = html;
            document.getElementById('vendorAuditModal').style.display = 'flex';
        }

        function closeVendorAuditModal() {
            document.getElementById('vendorAuditModal').style.display = 'none';
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
                                    handshakeCol.innerHTML = `
                                        <div style="background: #F0FDF4; border: 1px solid #BBF7D0; padding: 8px 12px; border-radius: 8px;">
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="color: #16A34A; font-weight: 800; font-size: 12px;">✓ Package Released</span>
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