@extends('layouts.dashboard')

@section('title', 'Order Dispatch Triage Desk')
@section('role_title', 'Admin Staff')
@section('page_title', 'Order Dispatch Triage')
@section('role_badge', 'ADMIN STAFF')
@section('role_badge_class', 'role-badge-admin-staff')

@section('sidebar_menu')
    @include('admin-staff.partials._sidebar')
@endsection

@section('content')
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">📦</span>
                <span>Order Fulfillment Oversight &amp; Triage Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Active customer transactions monitored for prompt preparation and directional handover.
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="{{ route('admin.staff.dashboard') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Staff Desk
            </a>
            <span class="dash-badge dash-badge-gold" style="font-size: 12px; padding: 6px 12px;">
                Real-Time Fulfillment Flow
            </span>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>📦</span>
                <div>
                    <div>Live Order Dispatch Queue</div>
                    <span class="dash-card-subtitle">Orders in preparation across merchants and branch counters.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-purple">{{ count($recentOrders) }} Active Orders</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer Contact</th>
                        <th>Branch Plaza</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Supervision Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOrders as $order)
                        <tr>
                            <td>
                                <strong style="font-family: monospace; font-size: 13.5px; color: var(--adm-primary);">{{ $order->order_number }}</strong>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">{{ $order->created_at ? $order->created_at->format('M d, H:i') : 'Recent' }}</div>
                            </td>
                            <td>
                                <div><strong>{{ $order->customer->name ?? 'Shopper' }}</strong></div>
                                <div style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $order->customer->phone ?? 'N/A' }}</div>
                            </td>
                            <td>{{ $order->shop->name ?? 'Plaza Branch' }}</td>
                            <td><strong>&#8358;{{ number_format($order->order_amount, 2) }}</strong></td>
                            <td>
                                <span class="dash-badge dash-badge-info">● {{ ucfirst($order->order_status) }}</span>
                            </td>
                            <td>
                                <a href="{{ route('order.status', $order->order_number) }}" target="_blank" class="btn-dash btn-dash-outline" style="padding: 4px 10px; font-size: 11px;">
                                    Inspect Journey ↗
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: var(--adm-text-muted);">
                                No orders currently require triage intervention.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection