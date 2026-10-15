@extends('layouts.dashboard')

@section('title', 'My Active Drops — Delivery Rider')
@section('role_title', 'Delivery Rider Dispatch Cockpit')
@section('page_title', 'My Active Drops & Waybill Runs')
@section('role_badge', 'DISPATCH RIDER')
@section('role_badge_class', 'role-badge-rider')

@section('sidebar_menu')
    @include('rider.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('rider.dashboard') }}">Rider Cockpit</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">My Active Drops</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">📦</span>
                <span>My Active Drops &amp; LGA Dispatches</span>
            </h1>
            <p class="page-header-subtitle">
                Current delivery runs assigned to your motorbike. Complete plaza counter pickup handover first, then navigate to customer doorstep.
            </p>
        </div>
        <div>
            <span class="dash-badge dash-badge-purple" style="padding: 6px 14px; font-size: 12.5px; font-weight: 700;">
                {{ count($activeDeliveries) }} Assigned Dispatches
            </span>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Assigned Active Deliveries ({{ count($activeDeliveries) }})</div>
                <span class="dash-card-subtitle">Follow the 2-step protocol for every package transfer</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order Tracking #</th>
                        <th>Origin Branch Plaza</th>
                        <th>Customer Recipient</th>
                        <th>Delivery Fee Cut</th>
                        <th>Current Stage</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeDeliveries as $order)
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-primary); font-size: 14px;">{{ $order->order_number }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $order->created_at->format('d M, Y H:i') }}</span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $order->shop->name ?? 'Victor Tech Plaza' }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">Counter Collection</span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $order->customer->name ?? 'Emeka Okonkwo' }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $order->customer->phone ?? '08012345678' }}</span>
                            </td>
                            <td style="font-weight: 800; color: #059669;">
                                ₦{{ number_format($order->delivery_fee ?? 1500, 2) }}
                            </td>
                            <td>
                                @if($order->order_status === 'processing' || $order->order_status === 'ready_for_pickup')
                                    <span class="dash-badge dash-badge-gold">Step 1: Plaza Pickup</span>
                                @elseif($order->order_status === 'out_for_delivery')
                                    <span class="dash-badge dash-badge-info">Step 2: Doorstep Transit</span>
                                @else
                                    <span class="dash-badge dash-badge-success">Completed</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <a href="{{ route('rider.pickup.index') }}" class="btn-dash btn-dash-outline" style="padding: 4px 10px; font-size: 11.5px;">
                                        Step 1 &rarr;
                                    </a>
                                    <a href="{{ route('rider.dropoff.index') }}" class="btn-dash btn-dash-primary" style="padding: 4px 10px; font-size: 11.5px;">
                                        Step 2 &rarr;
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No active delivery runs assigned right now. You are on standby mode.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection