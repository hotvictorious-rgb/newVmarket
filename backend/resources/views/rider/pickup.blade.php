@extends('layouts.dashboard')

@section('title', 'STEP 1: Plaza Counter Pickup — Delivery Rider')
@section('role_title', 'Delivery Rider Dispatch Cockpit')
@section('page_title', 'STEP 1: PLAZA COUNTER PICKUP')
@section('role_badge', 'DISPATCH RIDER')
@section('role_badge_class', 'role-badge-rider')

@section('sidebar_menu')
    @include('rider.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('rider.dashboard') }}">Rider Cockpit</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">STEP 1: PLAZA COUNTER PICKUP</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏬</span>
                <span>STEP 1: PLAZA COUNTER PICKUP</span>
            </h1>
            <p class="page-header-subtitle">
                Collect parcel from merchant's physical plaza branch counter. Verify package intactness and complete counter custody sign-off.
            </p>
        </div>
        <div>
            <span class="dash-badge dash-badge-gold" style="padding: 6px 14px; font-size: 12.5px; font-weight: 700;">
                Counter Handover Required
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
        <div class="dash-card" style="border-top: 3.5px solid var(--adm-gold);">
            <div class="dash-card-header">
                <div>
                    <div class="dash-card-title">Plaza Counter Location &amp; Handover Code</div>
                    <span class="dash-card-subtitle">Present this pickup code to the counter staff at the plaza</span>
                </div>
            </div>
            <div class="dash-card-body">
                <div style="background: #F8FAFC; border: 1.5px solid var(--adm-border); border-radius: 12px; padding: 22px; max-width: 650px;">
                    <div style="margin-bottom: 16px;">
                        <span style="font-size: 12px; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase;">Origin Merchant Outlet</span>
                        <h3 style="font-size: 18px; font-weight: 800; color: var(--adm-text); margin-top: 4px;">{{ $currentOrder->shop->name ?? 'Victor Tech Uyo Flagship' }}</h3>
                        <p style="font-size: 13px; color: var(--adm-text-muted); margin-top: 2px;">{{ $currentOrder->shop->address ?? 'Shop 12, Ibom Plaza Commercial Center, Uyo' }}</p>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding-top: 14px; border-top: 1px solid var(--adm-border); margin-bottom: 18px;">
                        <div>
                            <span style="font-size: 11.5px; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase;">Order Number</span>
                            <div style="font-size: 15px; font-weight: 700; color: var(--adm-primary);">{{ $currentOrder->order_number }}</div>
                        </div>
                        <div>
                            <span style="font-size: 11.5px; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase;">Counter Pickup OTP</span>
                            <div style="font-size: 20px; font-weight: 800; color: #B45309; font-family: monospace; letter-spacing: 2px;">
                                {{ $currentOrder->pickup_otp ?? '492817' }}
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('rider.verify.pickup.otp') }}">
                        @csrf
                        <input type="hidden" name="order_number" value="{{ $currentOrder->order_number }}">
                        <button type="submit" class="btn-dash btn-dash-primary" style="padding: 10px 20px; font-size: 13.5px; font-weight: 700;">
                            🏬 Confirm Counter Pickup &amp; Start Transit &rarr;
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @else
        <div class="dash-card">
            <div class="dash-card-body" style="text-align: center; padding: 50px;">
                <span style="font-size: 40px;">🏍️</span>
                <h3 style="font-size: 17px; font-weight: 700; margin-top: 12px;">No Deliveries Pending Counter Pickup</h3>
                <p style="font-size: 13px; color: var(--adm-text-muted); margin-top: 4px;">You currently have zero packages awaiting plaza collection.</p>
            </div>
        </div>
    @endif
@endsection