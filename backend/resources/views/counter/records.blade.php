@extends('layouts.dashboard')

@section('title', "Today's Inspection Records — Counter Staff")
@section('role_title', 'Counter Staff')
@section('page_title', "Today's Inspection Records")
@section('role_badge', 'COUNTER STAFF')
@section('role_badge_class', 'role-badge-counter')

@section('sidebar_menu')
    @include('counter.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('counter.dashboard') }}">Counter Terminal</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Today's Inspection Records</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">📋</span>
                <span>Today's Inspection Records &amp; Sign-Off Logs</span>
            </h1>
            <p class="page-header-subtitle">
                Immutable audit trail of customer physical product sign-offs and verified counter handovers performed today.
            </p>
        </div>
        <div>
            <span class="dash-badge dash-badge-success" style="padding: 6px 14px; font-size: 12.5px; font-weight: 700;">
                100% Counter Pass Rate
            </span>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Completed Physical Examinations ({{ count($inspectedToday) }})</div>
                <span class="dash-card-subtitle">Verified in-person examinations signed off by branch counter staff</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Pass Code</th>
                        <th>Product Examined</th>
                        <th>Shopper Name</th>
                        <th>Price Signed Off</th>
                        <th>Plaza Branch</th>
                        <th>Outcome</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inspectedToday as $record)
                        <tr>
                            <td>
                                <span class="dash-badge dash-badge-gold" style="font-size: 13px; font-weight: 800;">
                                    {{ $record->reservation_code }}
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-text);">{{ $record->product->name ?? 'Product' }}</div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $record->customer->name ?? 'Shopper' }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $record->customer->phone ?? 'N/A' }}</span>
                            </td>
                            <td style="font-weight: 800; color: #059669;">
                                ₦{{ number_format($record->product->unit_price ?? 0, 2) }}
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ $record->shop->name ?? 'Plaza Counter' }}</span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-success">✅ Passed &amp; Completed</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No physical examinations completed today yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection