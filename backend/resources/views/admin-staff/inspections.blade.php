@extends('layouts.dashboard')

@section('title', 'Counter Inspection Monitor')
@section('role_title', 'Admin Staff')
@section('page_title', 'Counter Inspection Monitor')
@section('role_badge', 'ADMIN STAFF')
@section('role_badge_class', 'role-badge-admin-staff')

@section('sidebar_menu')
    @include('admin-staff.partials._sidebar')
@endsection

@section('content')
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏬</span>
                <span>In-Shop Physical Inspection Monitor</span>
            </h1>
            <p class="page-header-subtitle">
                Supervise in-shop inspection check-ins, customer sign-offs, and counter handovers across plazas.
            </p>
        </div>
        <div>
            <a href="{{ route('admin.staff.dashboard') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Staff Desk
            </a>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>🏬</span>
                <div>
                    <div>In-Shop Counter Examination Passes</div>
                    <span class="dash-card-subtitle">Customer physical inspection reservations awaiting or signed off at counter terminals.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-success">{{ count($counterInspections) }} Inspections</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Pass Code</th>
                        <th>Product Reserved</th>
                        <th>Customer</th>
                        <th>Plaza Counter</th>
                        <th>Inspection Status</th>
                        <th>Pass Link</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($counterInspections as $insp)
                        <tr>
                            <td>
                                <strong style="font-family: monospace; font-size: 13.5px; color: var(--adm-primary);">{{ $insp->reservation_code }}</strong>
                            </td>
                            <td>
                                <div><strong>{{ $insp->product->name ?? 'Reserved Item' }}</strong></div>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">&#8358;{{ number_format($insp->product->unit_price ?? 0, 2) }}</div>
                            </td>
                            <td>
                                <div><strong>{{ $insp->customer->name ?? 'Shopper' }}</strong></div>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">{{ $insp->customer->phone ?? '' }}</div>
                            </td>
                            <td>{{ $insp->shop->name ?? 'Plaza Branch' }}</td>
                            <td>
                                <span class="dash-badge {{ $insp->status === 'inspected' ? 'dash-badge-success' : 'dash-badge-amber' }}">
                                    ● {{ ucfirst($insp->status ?? 'Reserved') }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('reservation.pass', $insp->reservation_code) }}" target="_blank" class="btn-dash btn-dash-outline" style="padding: 4px 10px; font-size: 11px;">
                                    View Pass ↗
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: var(--adm-text-muted);">
                                No counter reservations registered.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection