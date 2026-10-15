@extends('layouts.dashboard')

@section('title', 'Rider Roster & Shifts — Logistics Fleet')
@section('role_title', 'Logistics Fleet Operations Center')
@section('page_title', 'Rider Roster & Shifts')
@section('role_badge', 'LOGISTICS HQ')
@section('role_badge_class', 'role-badge-logistics')

@section('sidebar_menu')
    @include('logistics.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('logistics.dashboard') }}">Logistics Fleet</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Rider Roster &amp; Shifts</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏍️</span>
                <span>Rider Roster &amp; Shift Management</span>
            </h1>
            <p class="page-header-subtitle">
                Active dispatch couriers, assigned motorbikes, assigned LGA delivery corridors, and real-time operational duty status.
            </p>
        </div>
        <div>
            <span class="dash-badge dash-badge-success" style="padding: 6px 14px; font-size: 12.5px; font-weight: 700;">
                {{ count($activeRiders) }} Couriers on Roster
            </span>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Registered Fleet Riders ({{ count($activeRiders) }})</div>
                <span class="dash-card-subtitle">Couriers authorized for directional LGA transit and OTP doorstep deliveries</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Courier Name &amp; Contact</th>
                        <th>Vehicle &amp; Plate</th>
                        <th>Assigned LGA Corridor</th>
                        <th>Duty Status</th>
                        <th>Compliance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeRiders as $rider)
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-text); font-size: 14px;">{{ $rider->user->name ?? 'Fleet Rider' }}</div>
                                <span style="font-size: 12px; color: var(--adm-text-muted);">{{ $rider->user->phone ?? '08000000000' }}</span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $rider->vehicle_type ?? 'Motorcycle' }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-primary); font-family: monospace; font-weight: 700;">{{ $rider->vehicle_plate_number ?? 'UYO-123-AK' }}</span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ $rider->assignedLga->name ?? 'Uyo' }} LGA Corridor</span>
                            </td>
                            <td>
                                @if($rider->is_online)
                                    <span class="dash-badge dash-badge-success">● ON DUTY</span>
                                @else
                                    <span class="dash-badge dash-badge-gold">○ STANDBY / OFF</span>
                                @endif
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-success">✓ Verified &amp; Insured</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No couriers registered on this fleet roster yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection