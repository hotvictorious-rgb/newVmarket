@extends('layouts.dashboard')

@section('title', '90% Payout Bank Account — Vendor')
@section('role_title', 'Merchant Commerce Suite')
@section('page_title', '90% Payout Bank Account')
@section('role_badge', 'VENDOR')
@section('role_badge_class', 'role-badge-vendor')

@section('sidebar_menu')
    @include('vendor.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('vendor.dashboard') }}">Merchant</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">90% Payout Bank Account</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">💳</span>
                <span>90% Payout Bank Account &amp; Financial Settlement</span>
            </h1>
            <p class="page-header-subtitle">
                Guaranteed 90% merchant revenue share. All payments are held safely in Victorious escrow until customer delivery verification.
            </p>
        </div>
    </div>

    <!-- Financial Stats Grid -->
    <div class="stat-grid-4">
        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Gross Merchant Sales</span>
                <span class="stat-card-value">₦{{ number_format($vendorMetrics['gross_sales'] ?? 0, 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">100% Escrow Volume</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">📊</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">90% Net Merchant Earning</span>
                <span class="stat-card-value">₦{{ number_format($vendorMetrics['net_earnings'] ?? 0, 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Direct Bank Transfer Share</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">💰</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Total Disbursed</span>
                <span class="stat-card-value">₦{{ number_format($vendorMetrics['settled_amount'] ?? 0, 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Cleared to Bank Account</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">🏦</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">Escrow Pending Delivery</span>
                <span class="stat-card-value">₦{{ number_format($vendorMetrics['pending_settlement'] ?? 0, 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">Awaiting OTP Handshake</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">⏳</div>
        </div>
    </div>

    <!-- Bank Account Details Card -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Designated Settlement Account</div>
                <span class="dash-card-subtitle">Verified NIBSS Nigerian bank account for automated earnings transfer</span>
            </div>
        </div>
        <div class="dash-card-body">
            <div style="background: #F8FAFC; border: 1.5px solid var(--adm-border); border-radius: 12px; padding: 22px; max-width: 600px;">
                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 16px;">
                    <div style="width: 48px; height: 48px; border-radius: 10px; background: #EEF2FF; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                        🏦
                    </div>
                    <div>
                        <div style="font-size: 17px; font-weight: 800; color: var(--adm-text);">{{ $vendor->bank_name ?? 'Zenith Bank PLC' }}</div>
                        <div style="font-size: 13px; color: var(--adm-text-muted);">Account Name: <strong style="color: var(--adm-text);">{{ $vendor->account_holder_name ?? $vendor->business_name }}</strong></div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; padding-top: 14px; border-top: 1px solid var(--adm-border);">
                    <div>
                        <span style="font-size: 11.5px; color: var(--adm-text-muted); text-transform: uppercase; font-weight: 700;">Account Number</span>
                        <div style="font-size: 18px; font-weight: 800; color: var(--adm-primary); font-family: monospace; letter-spacing: 1px;">
                            {{ $vendor->account_number ?? $vendor->bank_account_number ?? '1012345678' }}
                        </div>
                    </div>
                    <div>
                        <span style="font-size: 11.5px; color: var(--adm-text-muted); text-transform: uppercase; font-weight: 700;">Settlement Policy</span>
                        <div style="font-size: 13.5px; font-weight: 700; color: #059669;">
                            T+0 Immediate Upon Handshake
                        </div>
                    </div>
                </div>

                @if($vendor->isWithinBankCooldown())
                    <div style="margin-top: 16px; background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 8px; padding: 12px; font-size: 13px; color: #92400E;">
                        ⚠️ <strong>48-Hour Anti-Theft Cooldown Active:</strong> Your bank account was updated on {{ $vendor->bank_updated_at ? $vendor->bank_updated_at->format('M d, Y H:i') : 'recently' }}. Payout disbursements remain locked until 48 hours elapse.
                    </div>
                @endif
            </div>

            <!-- Update Bank Details Modal / Form -->
            <div style="margin-top: 24px; max-width: 600px; background: #FFFFFF; border: 1px solid var(--adm-border); border-radius: 12px; padding: 20px;">
                <h3 style="font-size: 15px; font-weight: 800; color: var(--adm-text); margin-bottom: 6px;">Modify Designated Bank Details</h3>
                <p style="font-size: 12.5px; color: var(--adm-text-muted); margin-bottom: 16px;">
                    🛡️ <em>Security Invariant:</em> Updating your bank account triggers an automatic 48-Hour Security Lock on all payouts.
                </p>

                <form method="POST" action="{{ route('vendor.bank.update') }}">
                    @csrf
                    <div style="display: grid; grid-template-columns: 1fr; gap: 14px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Bank Name</label>
                            <input type="text" name="bank_name" value="{{ old('bank_name', $vendor->bank_name) }}" required class="form-control" style="width: 100%; padding: 9px 12px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;" placeholder="e.g. Zenith Bank PLC">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">NUBAN Account (10 Digits)</label>
                                <input type="text" name="account_number" maxlength="10" pattern="[0-9]{10}" value="{{ old('account_number', $vendor->account_number) }}" required class="form-control" style="width: 100%; padding: 9px 12px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px; font-family: monospace;" placeholder="0123456789">
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Account Holder Name</label>
                                <input type="text" name="account_name" value="{{ old('account_name', $vendor->account_name ?? $vendor->business_name) }}" required class="form-control" style="width: 100%; padding: 9px 12px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;" placeholder="Full verified name">
                            </div>
                        </div>

                        <div style="padding-top: 6px;">
                            <button type="submit" class="btn btn-primary" style="background: var(--adm-primary); color: #FFF; padding: 10px 18px; border: none; border-radius: 8px; font-weight: 700; font-size: 13.5px; cursor: pointer;">
                                Update Bank Account &amp; Initiate 48h Lock
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection