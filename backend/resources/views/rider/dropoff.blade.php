@extends('layouts.dashboard')

@section('title', 'STEP 2: CUSTOMER DOORSTEP HANDSHAKE — Delivery Rider')
@section('role_title', 'Delivery Rider Dispatch Cockpit')
@section('page_title', 'STEP 2: CUSTOMER DOORSTEP HANDSHAKE')
@section('role_badge', 'DISPATCH RIDER')
@section('role_badge_class', 'role-badge-rider')

@section('sidebar_menu')
    @include('rider.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('rider.dashboard') }}">Rider Cockpit</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">STEP 2: CUSTOMER DOORSTEP HANDSHAKE</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏁</span>
                <span>STEP 2: CUSTOMER DOORSTEP HANDSHAKE</span>
            </h1>
            <p class="page-header-subtitle">
                Deliver parcel to customer at their doorstep. Customer inputs the verification PIN on their own device to unlock payment and complete the delivery.
            </p>
        </div>
        <div>
            <span class="dash-badge dash-badge-success" style="padding: 6px 14px; font-size: 12.5px; font-weight: 700;">
                Tamper-Proof Customer Handshake
            </span>
        </div>
    </div>

    @if(session('success'))
        <div style="background: #ECFDF5; border-left: 4px solid #10B981; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; color: #065F46; font-size: 14px; font-weight: 700;">
            ✅ {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div style="background: #FEF2F2; border-left: 4px solid #EF4444; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; color: #991B1B; font-size: 14px; font-weight: 700;">
            ❌ {{ session('error') }}
        </div>
    @endif

    @php
        $currentOrder = $activeDeliveries->first();
    @endphp

    @if($currentOrder)
        <div class="dash-card" style="border-top: 3.5px solid var(--adm-primary);">
            <div class="dash-card-header">
                <div>
                    <div class="dash-card-title">Recipient Doorstep Details &amp; Custody Handshake</div>
                    <span class="dash-card-subtitle">Order #{{ $currentOrder->order_number }} &bull; Fulfillment: Doorstep Delivery Handshake</span>
                </div>
                <div>
                    <span class="dash-badge {{ $currentOrder->order_status === 'delivered' ? 'dash-badge-success' : ($currentOrder->hasRiderArrived() ? 'dash-badge-gold' : 'dash-badge-info') }}">
                        {{ $currentOrder->order_status === 'delivered' ? 'DELIVERY COMPLETE' : ($currentOrder->hasRiderArrived() ? 'AWAITING CUSTOMER PIN' : 'IN TRANSIT') }}
                    </span>
                </div>
            </div>

            <div class="dash-card-body">
                <div style="background: #F8FAFC; border: 1.5px solid var(--adm-border); border-radius: 14px; padding: 22px; max-width: 680px; margin-bottom: 24px;">
                    
                    <!-- Customer Information Block -->
                    <div style="margin-bottom: 20px;">
                        <span style="font-size: 11px; font-weight: 800; color: var(--adm-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Recipient Customer</span>
                        <h3 style="font-size: 19px; font-weight: 800; color: var(--adm-text); margin: 4px 0 2px;">{{ $currentOrder->customer->name ?? 'Emeka Okonkwo' }}</h3>
                        <p style="font-size: 14px; font-weight: 700; color: var(--adm-primary); margin: 0 0 6px;">📞 {{ substr($currentOrder->customer->phone ?? '08012345678', 0, 4) . '****' . substr($currentOrder->customer->phone ?? '08012345678', -4) }}</p>
                        <p style="font-size: 13.5px; color: #475569; margin: 0;">📍 {{ $currentOrder->delivery_address ?? '14 Oron Road, Uyo, Akwa Ibom State' }}</p>
                    </div>

                    <!-- Contact Buttons -->
                    <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                        <a href="tel:{{ $currentOrder->customer->phone ?? '08012345678' }}" class="btn-dash btn-dash-outline" style="font-size: 12.5px; padding: 7px 14px; display: inline-flex; align-items: center; gap: 6px;">
                            <span>📞 Call Customer</span>
                        </a>
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $currentOrder->customer->phone ?? '08012345678') }}?text=Hello%20{{ urlencode($currentOrder->customer->name ?? 'Customer') }},%20I%20have%20arrived%20with%20your%20order%20{{ $currentOrder->order_number }}" target="_blank" class="btn-dash" style="background: #25D366; color: #FFFFFF; font-size: 12.5px; padding: 7px 14px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                            <span>💬 WhatsApp Customer</span>
                        </a>
                    </div>

                    <!-- Dynamic Handshake State Machine -->
                    <div id="handshakeStateContainer" style="border-top: 1.5px solid var(--adm-border); padding-top: 20px;">
                        @if($currentOrder->order_status === 'delivered')
                            <!-- STATE 3: DELIVERED SUCCESS -->
                            <div style="background: #ECFDF5; border: 2px solid #10B981; border-radius: 12px; padding: 20px; text-align: center;">
                                <div style="font-size: 38px; margin-bottom: 8px;">🎉</div>
                                <h4 style="font-size: 17px; font-weight: 800; color: #065F46; margin: 0 0 6px;">
                                    Delivery Handshake Confirmed!
                                </h4>
                                <p style="font-size: 13px; color: #047857; margin: 0 0 14px;">
                                    Customer verified parcel receipt on their own phone.
                                </p>
                                <div style="display: inline-block; background: #FFFFFF; border: 1.5px solid #10B981; border-radius: 10px; padding: 10px 18px; margin-bottom: 12px;">
                                    <span style="font-size: 12px; font-weight: 700; color: #065F46; display: block;">✓ DELIVERY HANDSHAKE VERIFIED &amp; COMPLETED</span>
                                </div>
                                @if($currentOrder->proof_of_delivery_image)
                                    <div style="margin-top: 8px;">
                                        <span style="font-size: 11.5px; color: #065F46; display: block; font-weight: 600;">Customer snapped condition proof photo:</span>
                                        <img src="{{ $currentOrder->proof_of_delivery_image }}" alt="Proof" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; margin-top: 4px; border: 1px solid #10B981;">
                                    </div>
                                @endif
                            </div>
                        @elseif($currentOrder->hasRiderArrived())
                            <!-- STATE 2: ARRIVED & WAITING FOR CUSTOMER PIN ENTRY -->
                            <div id="waitingCustomerBox" style="background: linear-gradient(135deg, #1E1B4B 0%, #312E81 100%); border: 2px solid #6366F1; border-radius: 12px; padding: 22px; color: #FFFFFF; text-align: center;">
                                <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(99, 102, 241, 0.3); padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: 800; color: #C7D2FE; margin-bottom: 12px;">
                                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #38BDF8; animation: pulse 1.5s infinite;"></span>
                                    ARRIVAL SIGNAL SENT AT {{ $currentOrder->rider_arrived_at->format('h:i A') }}
                                </div>
                                <h4 style="font-size: 17px; font-weight: 800; margin: 0 0 6px; color: #FFFFFF;">
                                    Waiting for Customer to Enter PIN on Their Phone...
                                </h4>
                                <p style="font-size: 13px; color: #C7D2FE; margin: 0 0 16px; line-height: 1.5;">
                                    Hand over parcel for physical inspection. Ask the customer to check their Victorious Market screen or SMS, and type their 6-digit PIN on their own device.
                                </p>
                                <div style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 10px; padding: 12px; font-size: 12px; color: #E0E7FF;">
                                    ⚡ <strong>Live Sync Active:</strong> This screen will automatically turn green the moment the customer submits their code.
                                </div>
                            </div>
                        @else
                            <!-- STATE 1: RIDER HAS NOT YET SIGNALED ARRIVAL -->
                            <div style="text-align: center;">
                                <p style="font-size: 13.5px; color: #475569; margin: 0 0 16px; line-height: 1.5;">
                                    When you arrive at the customer's gate or doorstep, tap the button below. This will trigger the verification box on the customer's phone.
                                </p>

                                <form method="POST" action="{{ route('rider.order.arrived') }}">
                                    @csrf
                                    <input type="hidden" name="order_id" value="{{ $currentOrder->id }}">
                                    <button type="submit" class="btn-dash btn-dash-primary" style="background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%); padding: 14px 28px; font-size: 15px; font-weight: 800; border-radius: 12px; display: inline-flex; align-items: center; gap: 10px; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35); cursor: pointer;">
                                        <span style="font-size: 20px;">📍</span>
                                        <span>I Have Arrived at Delivery Point / Request Customer Confirmation</span>
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="dash-card">
            <div class="dash-card-body" style="text-align: center; padding: 50px;">
                <span style="font-size: 40px;">🏁</span>
                <h3 style="font-size: 17px; font-weight: 700; margin-top: 12px;">No Deliveries Pending Doorstep Drop-off</h3>
                <p style="font-size: 13px; color: var(--adm-text-muted); margin-top: 4px;">All active runs have been completed.</p>
            </div>
        </div>
    @endif

    <!-- Live Auto-Polling Script when awaiting customer confirmation -->
    @if($currentOrder && $currentOrder->order_status === 'out_for_delivery' && $currentOrder->hasRiderArrived())
        <script>
            const pollRiderStatusInterval = setInterval(function() {
                fetch("{{ route('rider.order.handshake_status', $currentOrder->id) }}")
                    .then(response => response.json())
                    .then(data => {
                        if (data.is_delivered) {
                            clearInterval(pollRiderStatusInterval);
                            // Refresh screen to show completed green state and wallet credit!
                            window.location.reload();
                        }
                    })
                    .catch(err => console.log('Rider poll error:', err));
            }, 3000);
        </script>
    @endif
@endsection