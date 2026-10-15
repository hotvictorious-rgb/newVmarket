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
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">All Customer Orders ({{ count($orders) }})</div>
                <span class="dash-card-subtitle">Transactions protected by Victorious Escrow Guarantee</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order # &amp; Date</th>
                        <th>Customer</th>
                        <th>Gross Total</th>
                        <th>90% Merchant Cut</th>
                        <th>Fulfillment</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
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
                            <td style="font-weight: 800; color: #059669;">
                                ₦{{ number_format($order->vendor_earning_90, 2) }}
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ ucwords(str_replace('_', ' ', $order->fulfillment_type)) }}</span>
                            </td>
                            <td>
                                @if($order->order_status === 'delivered')
                                    <span class="dash-badge dash-badge-success">Delivered</span>
                                @elseif($order->order_status === 'processing')
                                    <span class="dash-badge dash-badge-gold">Processing</span>
                                @else
                                    <span class="dash-badge dash-badge-purple">{{ ucwords($order->order_status) }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No customer orders recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection