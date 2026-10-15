@extends('layouts.dashboard')

@section('title', 'Merchant Accreditation & KYC Governance Desk')
@section('role_title', 'Super Admin')
@section('page_title', 'Merchant Accreditation Desk')
@section('role_badge', 'SUPER ADMIN')
@section('role_badge_class', 'role-badge-super-admin')

@section('sidebar_menu')
    @include('admin.partials._sidebar')
@endsection

@section('content')
    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏪</span>
                <span>Merchant Accreditation &amp; KYC Governance Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Examine legal CAC/NIN verification, physical store inspection, and disburse permissions to sell on Victorious MARKET.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.dashboard') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Command Tower
            </a>
            <span class="dash-badge dash-badge-purple" style="padding: 6px 12px; font-size: 12px;">
                🛡️ Sovereign Quality Gate
            </span>
        </div>
    </div>

    <!-- 4 KYC Metric Cards -->
    <div class="stat-grid-4" style="margin-bottom: 24px;">
        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Accredited Active</span>
                <span class="stat-card-value">{{ $allVendors->where('kyc_status', 'verified')->count() }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Certified Quality Vendors</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">✅</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">Pending Verification</span>
                <span class="stat-card-value">{{ $allVendors->where('kyc_status', '!=', 'verified')->count() }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">Awaiting Document Review</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">⏳</div>
        </div>

        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Total Registered</span>
                <span class="stat-card-value">{{ $allVendors->count() }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">Ecosystem Merchants</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">🏬</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Shop Counters</span>
                <span class="stat-card-value">{{ $allVendors->sum(fn($v) => $v->shops->count()) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Uyo &amp; LGA Physical Plazas</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">📍</div>
        </div>
    </div>

    <!-- Merchant Accreditation Table Card -->
    <div class="dash-card" id="merchants-desk">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>🏛️</span>
                <div>
                    <div>Accredited Merchant KYC &amp; Banking Desk</div>
                    <span class="dash-card-subtitle">Examine legal CAC/NIN credentials, verify physical plaza branches, and manage marketplace listing status.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-purple">Sovereign Quality Gate</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Merchant / Business</th>
                        <th>CAC Number</th>
                        <th>Director NIN</th>
                        <th>Settlement Bank</th>
                        <th>Branches</th>
                        <th>KYC Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allVendors ?? [] as $v)
                        <tr>
                            <td>
                                <strong style="font-size: 13.5px; color: var(--adm-text);">{{ $v->business_name }}</strong>
                                <div style="font-size: 11.5px; color: var(--adm-text-muted);">Owner: {{ $v->user->name ?? 'N/A' }} ({{ $v->user->phone ?? 'N/A' }})</div>
                                <div style="font-size: 11px; color: #64748B;">{{ $v->user->email ?? 'N/A' }}</div>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ $v->cac_number ?? 'Pending CAC' }}</span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-purple">{{ $v->nin_number ?? 'Pending NIN' }}</span>
                            </td>
                            <td>
                                <div><strong>{{ $v->bank_name ?? 'Zenith Bank' }}</strong></div>
                                <div style="font-family: monospace; font-size: 12px; color: var(--adm-text-muted);">{{ $v->account_number ?? '1012345678' }}</div>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-gold" style="font-size: 11px;">
                                    🏬 {{ $v->shops->count() }} Shops
                                </span>
                            </td>
                            <td>
                                @if($v->kyc_status === 'verified')
                                    <span class="dash-badge dash-badge-success">● Accredited Active</span>
                                @else
                                    <span class="dash-badge dash-badge-danger">● {{ ucfirst($v->kyc_status ?? 'Pending') }}</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.vendors.accredit', $v->id) }}" style="display: flex; gap: 6px; margin: 0;">
                                    @csrf
                                    @if($v->kyc_status === 'verified')
                                        <button type="submit" name="action" value="reject" class="btn-dash btn-dash-danger" style="padding: 4px 10px; font-size: 11px;" title="Suspend Merchant">
                                            Suspend
                                        </button>
                                    @else
                                        <button type="submit" name="action" value="verify" class="btn-dash btn-dash-success" style="padding: 4px 10px; font-size: 11px;" title="Approve KYC">
                                            ✓ Approve KYC
                                        </button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: var(--adm-text-muted);">
                                No merchants registered yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection