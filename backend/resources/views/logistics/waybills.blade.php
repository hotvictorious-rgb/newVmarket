@extends('layouts.dashboard')

@section('title', 'Directional LGA Waybills — Logistics Fleet')
@section('role_title', 'Logistics Fleet Operations Center')
@section('page_title', 'Directional LGA Waybills Manifest')
@section('role_badge', 'LOGISTICS HQ')
@section('role_badge_class', 'role-badge-logistics')

@section('sidebar_menu')
    @include('logistics.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('logistics.dashboard') }}">Logistics Fleet</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Directional LGA Waybills</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">📜</span>
                <span>Directional LGA Waybills Manifest</span>
            </h1>
            <p class="page-header-subtitle">
                Parcels collected from Plaza counters for door-to-door delivery across Akwa Ibom. Track corridor progress and delivery handshakes.
            </p>
        </div>
        <div>
            <span class="dash-badge dash-badge-gold" style="padding: 6px 14px; font-size: 12.5px; font-weight: 700;">
                {{ count($activeDeliveries) }} Active Waybill Dispatches
            </span>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Active LGA Dispatches ({{ count($activeDeliveries) }})</div>
                <span class="dash-card-subtitle">Shipments under transit from branch counter to customer doorstep</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Waybill / Order #</th>
                        <th>Origin Plaza</th>
                        <th>Recipient &amp; Phone</th>
                        <th>Assigned Courier</th>
                        <th>Delivery Fee</th>
                        <th>Transit Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeDeliveries as $order)
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-primary); font-size: 13.5px;">{{ $order->order_number }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $order->created_at->format('d M, Y H:i') }}</span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $order->shop->name ?? 'Victor Tech Plaza' }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">Counter Staged</span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $order->customer->name ?? 'Shopper' }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $order->customer->phone ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-purple">{{ $order->rider->user->name ?? 'Assigned Courier' }}</span>
                            </td>
                            <td style="font-weight: 800; color: #059669;">
                                ₦{{ number_format($order->delivery_fee ?? 1500, 2) }}
                            </td>
                            <td>
                                @if($order->order_status === 'out_for_delivery')
                                    <span class="dash-badge dash-badge-gold">⚡ Out for Delivery</span>
                                @elseif($order->order_status === 'delivered')
                                    <span class="dash-badge dash-badge-success">✅ Delivered</span>
                                @else
                                    <span class="dash-badge dash-badge-info">● {{ ucwords($order->order_status) }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No active waybill dispatches in progress right now.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection