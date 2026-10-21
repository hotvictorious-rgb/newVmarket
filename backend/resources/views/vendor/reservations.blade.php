@extends('layouts.dashboard')

@section('title', 'Pickup Reservations — Vendor')
@section('role_title', 'Merchant Commerce Suite')
@section('page_title', 'Pickup Reservations')
@section('role_badge', 'VENDOR')
@section('role_badge_class', 'role-badge-vendor')

@section('sidebar_menu')
    @include('vendor.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('vendor.dashboard') }}">Merchant</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Pickup Reservations</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">📝</span>
                <span>In-Shop Pickup Reservations</span>
            </h1>
            <p class="page-header-subtitle">
                Shoppers who reserved items online for physical inspection before payment at your Plaza shop counter.
            </p>
        </div>
    </div>

    @include('vendor.partials._shop_switcher')

    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Active Reservation Passes ({{ count($reservations) }})</div>
                <span class="dash-card-subtitle">Zero prepayment barrier with mandatory physical inspection sign-off</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Pass Code</th>
                        <th>Product Reserved</th>
                        <th>Customer</th>
                        <th>Value</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reservations as $res)
                        <tr>
                            <td>
                                <span class="dash-badge dash-badge-gold" style="font-size: 13px; font-weight: 800; letter-spacing: 0.5px;">
                                    {{ $res->reservation_code }}
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-text);">{{ $res->product->name ?? 'Reserved Item' }}</div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $res->customer->name ?? 'Shopper' }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $res->customer->phone ?? 'N/A' }}</span>
                            </td>
                            <td style="font-weight: 800; color: #059669;">
                                ₦{{ number_format($res->product->unit_price ?? 0, 2) }}
                            </td>
                            <td>
                                @if($res->status === 'inspected')
                                    <span class="dash-badge dash-badge-success">Inspected &amp; Completed</span>
                                @else
                                    <span class="dash-badge dash-badge-info">Pending Inspection</span>
                                @endif
                            </td>
                            <td style="font-size: 12px; color: var(--adm-text-muted);">
                                {{ $res->created_at->format('d M, Y H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No pickup reservations recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection