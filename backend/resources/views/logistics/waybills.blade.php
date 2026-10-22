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

    <div class="dash-card" style="margin-bottom: 16px;">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Uyo Road Corridor Tabs</div>
                <span class="dash-card-subtitle">Only orders assigned to your logistics company are shown. A rider may not mix corridors on a bike trip.</span>
            </div>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:8px; padding:0 18px 18px;">
            <button type="button" class="dash-badge dash-badge-info" data-corridor-filter="all" aria-pressed="true">All corridors ({{ count($activeDeliveries) }})</button>
            @foreach($corridors as $corridor)
                <button type="button" class="dash-badge dash-badge-info" data-corridor-filter="{{ $corridor->id }}" aria-pressed="false">
                    {{ $corridor->name }} ({{ $activeDeliveries->where('delivery_corridor_id', $corridor->id)->count() }})
                </button>
            @endforeach
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
                        <th>Source</th>
                        <th>Destination Corridor</th>
                        <th>Vehicle Class</th>
                        <th>Recipient &amp; Phone</th>
                        <th>Assigned Courier</th>
                        <th>Delivery Fee</th>
                        <th>Transit Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeDeliveries as $order)
                        <tr data-corridor-id="{{ $order->delivery_corridor_id }}">
                            <td>
                                <div style="font-weight: 700; color: var(--adm-primary); font-size: 13.5px;">{{ $order->order_number }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $order->created_at->format('d M, Y H:i') }}</span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $order->shop->name ?? 'Victor Tech Plaza' }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">Counter Staged</span>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-text);">{{ $order->deliveryCorridor->name ?? 'Unmapped — hold dispatch' }}</div>
                            </td>
                            <td>
                                @php($isBulkyOrder = $order->items->contains(fn ($item) => (bool) $item->is_bulky_snapshot))
                                @if($isBulkyOrder)
                                    <span class="dash-badge dash-badge-gold">Keke — exclusive order</span>
                                @else
                                    <span class="dash-badge dash-badge-info">Motorcycle — corridor batch only</span>
                                @endif
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $order->customer->name ?? 'Shopper' }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $order->customer->phone ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-purple">{{ $order->rider->user->name ?? 'Unassigned' }}</span>
                                @php($isBulkyForDispatch = $order->items->contains(fn ($item) => (bool) $item->is_bulky_snapshot))
                                @php($eligibleRiders = $companyRiders->filter(fn ($candidate) => $isBulkyForDispatch ? in_array(strtolower((string) $candidate->vehicle_type), ['keke', 'tricycle'], true) : in_array(strtolower((string) $candidate->vehicle_type), ['motorcycle', 'motorbike', 'bike'], true)))
                                @if($order->payment_status === 'paid' && !$order->pickup_confirmed_by_rider && !in_array($order->order_status, ['out_for_delivery', 'delivered', 'cancelled', 'returned', 'disputed'], true))
                                    <form method="POST" action="{{ route('logistics.orders.assign_rider', $order->id) }}" style="margin-top:8px; display:flex; gap:5px; flex-wrap:wrap;">
                                        @csrf
                                        <select name="rider_id" required style="max-width:160px; font-size:11px; padding:5px;">
                                            <option value="">Choose {{ $isBulkyForDispatch ? 'Keke' : 'motorcycle' }}</option>
                                            @foreach($eligibleRiders as $candidate)
                                                <option value="{{ $candidate->id }}" @selected((int) $order->rider_id === (int) $candidate->id)>{{ $candidate->user->name ?? 'Fleet Rider' }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn-dash btn-dash-primary" style="padding:5px 8px; font-size:11px;">Assign</button>
                                    </form>
                                @endif
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
                            <td colspan="8" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No active waybill dispatches in progress right now.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabs = Array.from(document.querySelectorAll('[data-corridor-filter]'));
            const rows = Array.from(document.querySelectorAll('tr[data-corridor-id]'));
            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    const selected = tab.getAttribute('data-corridor-filter');
                    tabs.forEach(function (other) {
                        other.setAttribute('aria-pressed', other === tab ? 'true' : 'false');
                    });
                    rows.forEach(function (row) {
                        row.style.display = selected === 'all' || row.getAttribute('data-corridor-id') === selected ? '' : 'none';
                    });
                });
            });
        });
    </script>
@endsection