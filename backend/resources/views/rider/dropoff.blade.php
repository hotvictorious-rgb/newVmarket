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
                Deliver parcel to customer at their doorstep. Request customer's 6-digit cryptographic delivery OTP to unlock payment and complete the order.
            </p>
        </div>
        <div>
            <span class="dash-badge dash-badge-success" style="padding: 6px 14px; font-size: 12.5px; font-weight: 700;">
                Customer Delivery Handshake
            </span>
        </div>
    </div>

    @if(session('success'))
        <div style="background: #ECFDF5; border-left: 4px solid #10B981; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; color: #065F46; font-size: 13.5px; font-weight: 600;">
            ✅ {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div style="background: #FEF2F2; border-left: 4px solid #EF4444; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; color: #991B1B; font-size: 13.5px; font-weight: 600;">
            ❌ {{ session('error') }}
        </div>
    @endif

    @php
        $currentOrder = $activeDeliveries->first();
    @endphp

    @if($currentOrder)
        <div class="dash-card" style="border-top: 3.5px solid var(--adm-success);">
            <div class="dash-card-header">
                <div>
                    <div class="dash-card-title">Recipient Doorstep Details &amp; OTP Handshake</div>
                    <span class="dash-card-subtitle">Verify OTP with customer upon physical parcel transfer</span>
                </div>
            </div>
            <div class="dash-card-body">
                <div style="background: #F8FAFC; border: 1.5px solid var(--adm-border); border-radius: 12px; padding: 22px; max-width: 650px;">
                    <div style="margin-bottom: 16px;">
                        <span style="font-size: 12px; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase;">Customer Recipient</span>
                        <h3 style="font-size: 18px; font-weight: 800; color: var(--adm-text); margin-top: 4px;">{{ $currentOrder->customer->name ?? 'Emeka Okonkwo' }}</h3>
                        <p style="font-size: 13.5px; font-weight: 600; color: var(--adm-primary); margin-top: 2px;">📞 {{ $currentOrder->customer->phone ?? '08012345678' }}</p>
                        <p style="font-size: 13px; color: var(--adm-text-muted); margin-top: 2px;">📍 {{ $currentOrder->delivery_address ?? '14 Oron Road, Uyo, Akwa Ibom State' }}</p>
                    </div>

                    <form method="POST" action="{{ route('rider.verify.delivery.otp') }}" style="padding-top: 14px; border-top: 1px solid var(--adm-border);">
                        @csrf
                        <input type="hidden" name="order_number" value="{{ $currentOrder->order_number }}">
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px;">
                                Enter Customer's 6-Digit Delivery OTP:
                            </label>
                            <input type="text" name="delivery_otp" maxlength="6" required class="form-control" style="width: 100%; max-width: 280px; padding: 10px 14px; border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: 20px; font-weight: 800; letter-spacing: 4px; font-family: monospace;" placeholder="••••••">
                        </div>
                        <button type="submit" class="btn-dash btn-dash-primary" style="padding: 10px 22px; font-size: 13.5px; font-weight: 700;">
                            🏁 Verify OTP &amp; Complete Delivery Handshake
                        </button>
                    </form>
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
@endsection