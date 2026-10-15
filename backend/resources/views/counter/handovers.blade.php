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
                Verify the dispatch courier's 6-digit pickup OTP to safely transfer package custody from this branch counter to the logistics fleet.
            </p>
        </div>
        <div>
            <span class="dash-badge dash-badge-success" style="padding: 6px 14px; font-size: 12.5px; font-weight: 700;">
                {{ count($stagedOrders) }} Packages Staged &amp; Ready
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

    <!-- OTP Input Station -->
    <div class="dash-card" style="border-top: 3.5px solid var(--adm-success); margin-bottom: 24px;">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Express Courier Pickup Verification Terminal</div>
                <span class="dash-card-subtitle">Enter courier's provided 6-digit cryptographic OTP to clear package release</span>
            </div>
        </div>
        <div class="dash-card-body">
            <form method="POST" action="{{ route('counter.verify.handover.otp') }}" style="max-width: 500px;">
                @csrf
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px;">
                        Order Tracking Number
                    </label>
                    <input type="text" name="order_number" required class="form-control" style="width: 100%; padding: 10px 14px; border: 1.5px solid var(--adm-border); border-radius: 8px; font-weight: 700;" placeholder="e.g. VM-20261014-001">
                </div>
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px;">
                        Rider 6-Digit Pickup OTP
                    </label>
                    <input type="text" name="pickup_otp" maxlength="6" required class="form-control" style="width: 100%; padding: 10px 14px; border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: 18px; font-weight: 800; letter-spacing: 4px; font-family: monospace;" placeholder="••••••">
                </div>
                <button type="submit" class="btn-dash btn-dash-primary" style="padding: 10px 20px; font-size: 13.5px; font-weight: 700;">
                    🔑 Verify &amp; Transfer Custody to Rider
                </button>
            </form>
        </div>
    </div>

    <!-- Staged Packages Table -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Staged Packages Ready for Courier Pickup ({{ count($stagedOrders) }})</div>
                <span class="dash-card-subtitle">Packaged parcels awaiting courier physical arrival at counter</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order # &amp; Date</th>
                        <th>Customer &amp; Phone</th>
                        <th>Order Value</th>
                        <th>Fulfillment Type</th>
                        <th>Stage Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stagedOrders as $order)
                        <tr>
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
                            <td>
                                <span class="dash-badge dash-badge-gold">● Staged at Counter Rack</span>
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
@endsection