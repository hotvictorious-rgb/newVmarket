@extends('layouts.dashboard')

@section('title', 'Platform Operational Audit Logs')
@section('role_title', 'Admin Staff')
@section('page_title', 'Operational Logs')
@section('role_badge', 'ADMIN STAFF')
@section('role_badge_class', 'role-badge-admin-staff')

@section('sidebar_menu')
    @include('admin-staff.partials._sidebar')
@endsection

@section('content')
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">📜</span>
                <span>Platform Operational Logs &amp; Audit Trail</span>
            </h1>
            <p class="page-header-subtitle">
                Immutable trace of sensitive admin actions, verification reviews, and order state mutations.
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
                <span>📜</span>
                <div>
                    <div>Immutable Governance Audit Trail</div>
                    <span class="dash-card-subtitle">Zero-trust audit log entries recording staff and admin operations.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-purple">Immutable Log</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User Principal</th>
                        <th>Action Performed</th>
                        <th>Target Model</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($auditLogs as $log)
                        <tr>
                            <td style="font-family: monospace; font-size: 11.5px; color: var(--adm-text-muted);">
                                {{ $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : 'Recent' }}
                            </td>
                            <td>
                                <strong>{{ $log->user->name ?? 'Staff User' }}</strong>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ $log->action }}</span>
                            </td>
                            <td>
                                <span style="font-family: monospace; font-size: 11.5px;">{{ $log->auditable_type ?? 'N/A' }} #{{ $log->auditable_id ?? '' }}</span>
                            </td>
                            <td>
                                <span style="font-family: monospace; font-size: 11.5px; color: #64748B;">{{ $log->ip_address ?? '127.0.0.1' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: var(--adm-text-muted);">
                                No audit log records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection