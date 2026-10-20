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
                        <th>Destination LGA</th>
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
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ substr($order->customer->phone ?? '08012345678', 0, 4) . '****' . substr($order->customer->phone ?? '08012345678', -4) }}</span>
                            </td>
                            <td style="font-weight: 700; color: #4338CA;">
                                {{ $order->destinationLga->name ?? ($order->shop->lga->name ?? 'Transit Zone') }}
                            </td>
                            <td>
                                @if($order->order_status === 'processing' || $order->order_status === 'ready_for_pickup')
                                    <span class="dash-badge dash-badge-gold">Step 1: Plaza Pickup</span>
                                @elseif($order->order_status === 'out_for_delivery')
                                    @if($order->hasRiderArrived())
                                        <span class="dash-badge" style="background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; font-weight: 700;">⏳ Awaiting Customer PIN</span>
                                    @else
                                        <span class="dash-badge dash-badge-info">Step 2: In Transit</span>
                                    @endif
                                @else
                                    <span class="dash-badge dash-badge-success">Completed</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px; align-items: center;">
                                    @if($order->order_status === 'processing' || $order->order_status === 'ready_for_pickup')
                                        <a href="{{ route('rider.pickup.index', ['order_number' => $order->order_number]) }}" class="btn-dash btn-dash-primary" style="padding: 5px 12px; font-size: 11.5px; font-weight: 700; background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); border: none;">
                                            🔑 Enter Pickup Code &rarr;
                                        </a>
                                    @elseif($order->order_status === 'out_for_delivery' && !$order->hasRiderArrived())
                                        <form method="POST" action="{{ route('rider.order.arrived') }}" style="display: inline; margin: 0;">
                                            @csrf
                                            <input type="hidden" name="order_id" value="{{ $order->id }}">
                                            <button type="submit" class="btn-dash" style="background: #2563EB; color: #FFFFFF; padding: 4px 8px; font-size: 11px; font-weight: 700; cursor: pointer;">
                                                📍 Arrived
                                            </button>
                                        </form>
                                        <a href="{{ route('rider.dropoff.index') }}" class="btn-dash btn-dash-primary" style="padding: 4px 10px; font-size: 11.5px;">
                                            Step 2 &rarr;
                                        </a>
                                    @else
                                        <a href="{{ route('rider.dropoff.index') }}" class="btn-dash btn-dash-primary" style="padding: 4px 10px; font-size: 11.5px;">
                                            Step 2 &rarr;
                                        </a>
                                    @endif
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