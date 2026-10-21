@extends('layouts.dashboard')

@section('title', 'Merchant Payouts & Supplier Payables — Vendor')
@section('role_title', 'Merchant Commerce Suite')
@section('page_title', '90% Payout Desk & Bank Settlements')
@section('role_badge', 'VENDOR')
@section('role_badge_class', 'role-badge-vendor')

@section('sidebar_menu')
    @include('vendor.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('vendor.dashboard') }}">Merchant</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">90% Payout Desk &amp; Settlements</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">💳</span>
                <span>Merchant Payouts &amp; Supplier Payables</span>
            </h1>
            <p class="page-header-subtitle">
                Legacy marketplace settlement balances are shown separately from Victorious MARKET item-level supplier payables. Retailer-model supplier orders do not use the legacy 90% merchant payout calculation.
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button type="button" onclick="document.getElementById('bankModal').style.display='flex'" class="btn-dash btn-dash-outline" style="padding: 7px 14px; font-size: 12px; font-weight: 700;">
                🏦 Update Bank Details
            </button>
            <button type="button" onclick="document.getElementById('withdrawalModal').style.display='flex'" class="btn-dash btn-dash-success" style="padding: 7px 18px; font-size: 12.5px; font-weight: 800; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);">
                ⚡ Request Payout
            </button>
        </div>
    </div>

    @if(session('status'))
        <div style="margin-bottom: 20px; padding: 14px 18px; border-radius: 10px; background: rgba(16, 185, 129, 0.1); border: 1.5px solid #10B981; color: #047857; font-weight: 700; font-size: 13.5px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 18px;">✅</span>
            <div>{{ session('status') }}</div>
        </div>
    @endif
    @if(session('error'))
        <div style="margin-bottom: 20px; padding: 14px 18px; border-radius: 10px; background: rgba(239, 68, 68, 0.1); border: 1.5px solid #EF4444; color: #B91C1C; font-weight: 700; font-size: 13.5px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 18px;">⚠️</span>
            <div>{{ session('error') }}</div>
        </div>
    @endif
    @if($errors->any())
        <div style="margin-bottom: 20px; padding: 14px 18px; border-radius: 10px; background: rgba(239, 68, 68, 0.1); border: 1.5px solid #EF4444; color: #B91C1C; font-weight: 600; font-size: 13px;">
            <ul style="margin: 0; padding-left: 20px;">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $activeRetailerSupplierPayout = collect($retailerSupplierPayoutRequests ?? [])->first(
            fn ($payout) => in_array($payout->status, ['pending', 'processing', 'uncertain'], true)
        );
    @endphp
    <section class="dash-card" style="margin-bottom: 24px; padding: 22px;">
        <div class="page-header-row" style="margin-bottom: 12px;">
            <div>
                <h2 style="margin: 0 0 6px; font-size: 18px; font-weight: 800;">Retailer Supplier Payables</h2>
                <p class="page-header-subtitle" style="max-width: 760px;">
                    Separate from legacy merchant sales payouts. This requests eligible item-level supplier costs from Victorious MARKET orders after verified payment, delivery, refund-window expiry, and finance reconciliation.
                </p>
            </div>
            <form method="POST" action="{{ route('vendor.supplier-payout.request') }}">
                @csrf
                <button type="submit" class="btn-dash btn-dash-primary"
                    {{ $activeRetailerSupplierPayout ? 'disabled' : '' }}
                    title="{{ $activeRetailerSupplierPayout ? 'Resolve the active request before requesting another payout.' : 'Calculate and reserve eligible supplier item payables.' }}">
                    Request Supplier Payables
                </button>
            </form>
        </div>
        @if($activeRetailerSupplierPayout)
            <div style="padding: 10px 12px; margin: 12px 0; border-radius: 8px; background: rgba(245, 158, 11, .12);">
                An active supplier payout request #{{ $activeRetailerSupplierPayout->id }} is {{ strtoupper($activeRetailerSupplierPayout->status) }}. Do not create another request; uncertain transfers require treasury reconciliation.
            </div>
        @endif
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr><th>Request</th><th>Amount</th><th>Status</th><th>Gateway</th><th>Items</th><th>Created</th></tr>
                </thead>
                <tbody>
                    @forelse($retailerSupplierPayoutRequests ?? [] as $supplierPayout)
                        <tr>
                            <td>#{{ $supplierPayout->id }}</td>
                            <td>₦{{ number_format((float) $supplierPayout->amount, 2) }}</td>
                            <td>{{ strtoupper($supplierPayout->status) }}</td>
                            <td>{{ $supplierPayout->gateway_status ?? '—' }}</td>
                            <td>{{ $supplierPayout->allocations->count() }}</td>
                            <td>{{ $supplierPayout->created_at?->format('d M Y, H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center">No retailer supplier-payable requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <small>Launch controls: minimum ₦5,000 and maximum ₦500,000 per request. Supplier KYC and bank details must remain valid. These are payout safeguards, not commercial tariff promises.</small>
    </section>

    <!-- 4 Financial Stats Cards: Clarifying Lifetime Earnings vs Withdrawable Balance -->
    <div class="stat-grid-4" style="margin-bottom: 24px;">
        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Legacy Marketplace Gross Sales</span>
                <span class="stat-card-value">₦{{ number_format((float)($vendorMetrics['gross_sales'] ?? 0), 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">100% Customer Volume</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">📈</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Legacy Marketplace Net Earnings</span>
                <span class="stat-card-value">₦{{ number_format((float)($vendorMetrics['net_earnings'] ?? 0), 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Guaranteed Merchandise Cut</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">💰</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">In-Transit / Escrow Hold</span>
                <span class="stat-card-value">₦{{ number_format((float)($vendorMetrics['escrow_held'] ?? 0), 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">Awaiting Delivery / 24h Return</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">⏳</div>
        </div>

        <div class="stat-card stat-card-border-emerald" style="border: 2px solid #10B981; background: linear-gradient(135deg, rgba(16, 185, 129, 0.04) 0%, rgba(16, 185, 129, 0.12) 100%);">
            <div class="stat-card-info">
                <span class="stat-card-label" style="font-weight: 800; color: #065F46; letter-spacing: 0.5px;">LEGACY BALANCE AVAILABLE TO WITHDRAW</span>
                <span class="stat-card-value" style="color: #047857; font-size: 24px; font-weight: 900;">₦{{ number_format((float)($vendorMetrics['withdrawable_balance'] ?? 0), 2) }}</span>
                <span class="stat-card-subtext" style="color: #059669; font-weight: 700;">Cleared &amp; Ready for Transfer</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald" style="background: #10B981; color: #FFF;">🏦</div>
        </div>
    </div>

    <!-- Secondary Balance Breakdown Bar -->
    <div style="background: var(--adm-card-bg, #ffffff); border: 1.5px solid var(--adm-card-border, #E2E8F0); border-radius: 12px; padding: 16px 22px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <div style="display: flex; gap: 28px; flex-wrap: wrap; align-items: center;">
            <div>
                <span style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--adm-text-muted);">Locked in Pending Admin Review</span>
                <div style="font-size: 16px; font-weight: 800; color: #D97706;">
                    ₦{{ number_format((float)($vendorMetrics['pending_payout'] ?? 0), 2) }}
                </div>
            </div>
            <div style="width: 1px; height: 32px; background: var(--adm-border, #E2E8F0);"></div>
            <div>
                <span style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--adm-text-muted);">Total Disbursed to Bank</span>
                <div style="font-size: 16px; font-weight: 800; color: #2563EB;">
                    ₦{{ number_format((float)($vendorMetrics['settled_amount'] ?? 0), 2) }}
                </div>
            </div>
            <div style="width: 1px; height: 32px; background: var(--adm-border, #E2E8F0);"></div>
            <div>
                <span style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--adm-text-muted);">Clearance Policy</span>
                <div style="font-size: 13.5px; font-weight: 700; color: #059669;">
                    24h Customer Return Inspection Window
                </div>
            </div>
        </div>

        <div>
            @if((float)($vendorMetrics['withdrawable_balance'] ?? 0) >= 1000)
                <button type="button" onclick="document.getElementById('withdrawalModal').style.display='flex'" class="btn-dash btn-dash-success" style="padding: 8px 18px; font-size: 12px; font-weight: 700;">
                    Withdraw ₦{{ number_format((float)($vendorMetrics['withdrawable_balance'] ?? 0), 2) }}
                </button>
            @else
                <button type="button" disabled class="btn-dash btn-dash-outline" style="padding: 8px 18px; font-size: 12px; font-weight: 700; opacity: 0.6; cursor: not-allowed;">
                    Min Withdrawal: ₦1,000.00
                </button>
            @endif
        </div>
    </div>

    <!-- Registered Bank Account Banner -->
    <div style="background: var(--adm-card-bg, #ffffff); border: 1.5px solid var(--adm-card-border, #E2E8F0); border-radius: 12px; padding: 18px 22px; margin-bottom: 24px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="font-size: 32px; background: rgba(94, 23, 235, 0.08); width: 52px; height: 52px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    🏛️
                </div>
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--adm-text-muted); letter-spacing: 0.5px;">Registered NUBAN Settlement Account</div>
                    @if($vendor->account_number)
                        <div style="font-size: 16px; font-weight: 800; color: var(--adm-text); margin-top: 2px;">
                            {{ $vendor->bank_name }} &bull; <span style="font-family: monospace; letter-spacing: 1px; color: var(--adm-primary);">{{ $vendor->account_number }}</span>
                        </div>
                        <div style="font-size: 12px; color: var(--adm-text-muted);">
                            Account Name: <strong style="color: var(--adm-text);">{{ $vendor->account_name ?? $vendor->business_name }}</strong>
                        </div>
                    @else
                        <div style="font-size: 14px; font-weight: 700; color: #DC2626; margin-top: 2px;">
                            ⚠️ No Bank Account Configured
                        </div>
                        <div style="font-size: 12px; color: var(--adm-text-muted);">
                            Please configure your verified NUBAN details to enable automated earnings withdrawals.
                        </div>
                    @endif
                </div>
            </div>

            <div>
                @if($vendor->isWithinBankCooldown())
                    <span class="dash-badge dash-badge-amber" style="padding: 7px 14px; font-size: 12px; font-weight: 700;">
                        🛡️ 48-Hour Anti-Theft Cooldown Active (Unlocks in {{ max(1, 48 - (int)now()->diffInHours($vendor->bank_updated_at)) }}h)
                    </span>
                @elseif($vendor->account_number)
                    <span class="dash-badge dash-badge-success" style="padding: 7px 14px; font-size: 12px; font-weight: 700;">
                        ✅ Verified &amp; Ready for Instant Payout
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Merchant Payout Requests Queue -->
    <div class="dash-card" style="margin-bottom: 28px;">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Withdrawal Request History ({{ count($payoutRequests ?? []) }})</div>
                <span class="dash-card-subtitle">Track your automated Paystack disbursements and Super Admin review decisions</span>
            </div>
            <button type="button" onclick="document.getElementById('withdrawalModal').style.display='flex'" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 11.5px;">
                + Request Withdrawal
            </button>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Request Ref</th>
                        <th>Requested Amount</th>
                        <th>Destination Account</th>
                        <th>Status</th>
                        <th>Date Disbursed</th>
                        <th>Notes / Rejection Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payoutRequests ?? [] as $req)
                        <tr>
                            <td>
                                <strong style="color: var(--adm-primary); font-family: monospace; font-size: 13px;">REQ-#{{ str_pad($req->id, 5, '0', STR_PAD_LEFT) }}</strong>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">{{ $req->created_at->format('d M, Y H:i') }}</div>
                            </td>
                            <td>
                                <strong style="font-size: 14.5px; color: #047857;">₦{{ number_format((float)$req->amount, 2) }}</strong>
                                <div style="font-size: 10.5px; color: var(--adm-text-muted);">90% merchandise share</div>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-text);">{{ $req->bank_name }}</div>
                                <div style="font-family: monospace; font-size: 12px; color: #475569;">{{ $req->account_number }}</div>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">{{ $req->account_name }}</div>
                            </td>
                            <td>
                                @if($req->status === 'approved')
                                    <span class="dash-badge dash-badge-success">✅ Disbursed</span>
                                @elseif($req->status === 'pending')
                                    <span class="dash-badge dash-badge-amber">⏳ Pending Review</span>
                                @else
                                    <span class="dash-badge dash-badge-danger">❌ Rejected</span>
                                @endif
                            </td>
                            <td>
                                @if($req->disbursed_at)
                                    <span style="font-size: 12px; color: var(--adm-text); font-weight: 700;">{{ $req->disbursed_at->format('d M, Y H:i') }}</span>
                                @else
                                    <span style="font-size: 11.5px; color: var(--adm-text-muted);">Pending Release</span>
                                @endif
                            </td>
                            <td>
                                @if($req->status === 'rejected')
                                    <div style="font-size: 12px; color: #DC2626; font-weight: 600;">
                                        ⚠️ {{ $req->rejection_reason ?? $req->admin_notes ?? 'Request was declined.' }}
                                    </div>
                                    <div style="font-size: 10.5px; color: #059669; margin-top: 2px;">
                                        Funds atomically unlocked to your available balance.
                                    </div>
                                @else
                                    <span style="font-size: 12px; color: var(--adm-text-muted);">{{ $req->admin_notes ?? 'Direct Paystack automated transfer' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 36px 20px; color: var(--adm-text-muted);">
                                <div style="font-size: 32px; margin-bottom: 8px;">💳</div>
                                <strong style="font-size: 14px; color: var(--adm-text);">No Withdrawal Requests Yet</strong>
                                <div style="font-size: 12.5px; margin-top: 4px;">Click "Request Payout" above to transfer your cleared earnings directly to your bank account.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- REQUEST PAYOUT MODAL -->
    <div id="withdrawalModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: var(--adm-card-bg, #ffffff); border-radius: 16px; border: 1px solid var(--adm-border, #E2E8F0); width: 100%; max-width: 520px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); overflow: hidden;">
            <div style="padding: 20px 24px; border-bottom: 1px solid var(--adm-border, #E2E8F0); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: var(--adm-text); display: flex; align-items: center; gap: 8px;">
                    <span>⚡</span> Request Bank Withdrawal
                </h3>
                <button type="button" onclick="document.getElementById('withdrawalModal').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--adm-text-muted);">&times;</button>
            </div>

            <form action="{{ route('vendor.payout.request') }}" method="POST" id="vendorPayoutForm">
                @csrf
                <div style="padding: 24px;">
                    <!-- Available Balance Banner -->
                    <div style="background: #F0FDF4; border: 1.5px solid #86EFAC; border-radius: 10px; padding: 14px 18px; margin-bottom: 18px;">
                        <span style="font-size: 11.5px; text-transform: uppercase; font-weight: 800; color: #15803D;">Available Withdrawable Balance</span>
                        <div style="font-size: 24px; font-weight: 900; color: #166534; font-family: monospace;">
                            ₦{{ number_format((float)($vendorMetrics['withdrawable_balance'] ?? 0), 2) }}
                        </div>
                        <p style="font-size: 11.5px; color: #15803D; margin: 4px 0 0 0;">
                            Excludes unfulfilled orders and items currently inside the 24-hour customer inspection window.
                        </p>
                    </div>

                    @if($vendor->isWithinBankCooldown())
                        <div style="background: #FEF3C7; border: 1.5px solid #F59E0B; border-radius: 10px; padding: 14px; margin-bottom: 18px; font-size: 12.5px; color: #92400E;">
                            ⚠️ <strong>48-Hour Bank Cooldown Active:</strong> Bank account modifications trigger a 48-hour security lock. You will be able to request withdrawals once the security lock elapses.
                        </div>
                    @endif

                    <!-- Destination Account Confirmation -->
                    <div style="background: #F8FAFC; border: 1px solid var(--adm-border, #E2E8F0); border-radius: 10px; padding: 12px 16px; margin-bottom: 18px;">
                        <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--adm-text-muted);">Destination Bank Account</span>
                        <div style="font-size: 14px; font-weight: 800; color: var(--adm-text); margin-top: 2px;">
                            {{ $vendor->bank_name }} &bull; <span style="font-family: monospace;">{{ $vendor->account_number }}</span>
                        </div>
                        <div style="font-size: 12px; color: var(--adm-text-muted);">Acct Name: <strong>{{ $vendor->account_name ?? $vendor->business_name }}</strong></div>
                    </div>

                    <!-- Amount Input -->
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px; color: var(--adm-text);">
                            Withdrawal Amount (₦)
                        </label>
                        <input type="number" step="0.01" min="1000" max="{{ (float)($vendorMetrics['withdrawable_balance'] ?? 0) }}" name="amount" id="withdrawalAmountInput" required placeholder="Min 1,000.00"
                            style="width: 100%; padding: 12px 14px; border: 1.5px solid var(--adm-border, #E2E8F0); border-radius: 8px; font-size: 16px; font-weight: 800; font-family: monospace; color: var(--adm-text); background: var(--adm-card-bg, #fff);">
                    </div>

                    <!-- Quick Percentage Buttons -->
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 20px;">
                        <button type="button" onclick="setWithdrawalAmount(0.25)" class="btn-dash btn-dash-outline" style="padding: 6px; font-size: 11.5px; font-weight: 700;">25%</button>
                        <button type="button" onclick="setWithdrawalAmount(0.50)" class="btn-dash btn-dash-outline" style="padding: 6px; font-size: 11.5px; font-weight: 700;">50%</button>
                        <button type="button" onclick="setWithdrawalAmount(0.75)" class="btn-dash btn-dash-outline" style="padding: 6px; font-size: 11.5px; font-weight: 700;">75%</button>
                        <button type="button" onclick="setWithdrawalAmount(1.00)" class="btn-dash btn-dash-outline" style="padding: 6px; font-size: 11.5px; font-weight: 700; color: #047857; border-color: #10B981;">100% (Max)</button>
                    </div>

                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" onclick="document.getElementById('withdrawalModal').style.display='none'" class="btn-dash btn-dash-outline" style="padding: 10px 18px; font-weight: 700;">
                            Cancel
                        </button>
                        <button type="submit" id="submitWithdrawalBtn" class="btn-dash btn-dash-success" style="padding: 10px 22px; font-weight: 800; font-size: 13.5px;"
                            @if($vendor->isWithinBankCooldown() || (float)($vendorMetrics['withdrawable_balance'] ?? 0) < 1000) disabled @endif>
                            Submit Withdrawal Request
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- UPDATE BANK DETAILS MODAL -->
    <div id="bankModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: var(--adm-card-bg, #ffffff); border-radius: 16px; border: 1px solid var(--adm-border, #E2E8F0); width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); overflow: hidden;">
            <div style="padding: 20px 24px; border-bottom: 1px solid var(--adm-border, #E2E8F0); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: var(--adm-text); display: flex; align-items: center; gap: 8px;">
                    <span>🏦</span> Update Designated Bank Details
                </h3>
                <button type="button" onclick="document.getElementById('bankModal').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--adm-text-muted);">&times;</button>
            </div>

            <form action="{{ route('vendor.bank.update') }}" method="POST">
                @csrf
                <div style="padding: 24px;">
                    <div style="background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 8px; padding: 12px; margin-bottom: 18px; font-size: 12.5px; color: #92400E;">
                        🛡️ <strong>48-Hour Anti-Fraud Lock:</strong> Updating your bank details triggers an automatic 48-hour security cooldown during which new withdrawal disbursements are suspended.
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Bank Name</label>
                        <input type="text" name="bank_name" value="{{ old('bank_name', $vendor->bank_name) }}" required class="form-control" style="width: 100%; padding: 10px 12px; border: 1.5px solid var(--adm-border, #E2E8F0); border-radius: 8px; font-size: 13.5px;" placeholder="e.g. Zenith Bank PLC">
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Bank Code</label>
                        <input type="text" name="bank_code" value="{{ old('bank_code', $vendor->bank_code) }}" required maxlength="20" pattern="[A-Za-z0-9_-]+" class="form-control" style="width: 100%; padding: 10px 12px; border: 1.5px solid var(--adm-border, #E2E8F0); border-radius: 8px; font-size: 13.5px;" placeholder="e.g. 057">
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">NUBAN Account (10 Digits)</label>
                        <input type="text" name="account_number" maxlength="10" pattern="[0-9]{10}" value="{{ old('account_number', $vendor->account_number) }}" required class="form-control" style="width: 100%; padding: 10px 12px; border: 1.5px solid var(--adm-border, #E2E8F0); border-radius: 8px; font-size: 14px; font-family: monospace;" placeholder="0123456789">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Account Holder Name</label>
                        <input type="text" name="account_name" value="{{ old('account_name', $vendor->account_name ?? $vendor->business_name) }}" required class="form-control" style="width: 100%; padding: 10px 12px; border: 1.5px solid var(--adm-border, #E2E8F0); border-radius: 8px; font-size: 13.5px;" placeholder="Exact name matching BVN/CAC">
                    </div>

                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" onclick="document.getElementById('bankModal').style.display='none'" class="btn-dash btn-dash-outline" style="padding: 10px 18px; font-weight: 700;">
                            Cancel
                        </button>
                        <button type="submit" class="btn-dash btn-dash-primary" style="padding: 10px 22px; font-weight: 800;">
                            Save &amp; Initiate 48h Cooldown
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        const maxWithdrawable = {{ (float)($vendorMetrics['withdrawable_balance'] ?? 0) }};

        function setWithdrawalAmount(percentage) {
            const input = document.getElementById('withdrawalAmountInput');
            if (!input) return;
            const calculated = Math.floor(maxWithdrawable * percentage * 100) / 100;
            input.value = calculated.toFixed(2);
            validateAmount();
        }

        function validateAmount() {
            const input = document.getElementById('withdrawalAmountInput');
            const btn = document.getElementById('submitWithdrawalBtn');
            if (!input || !btn) return;

            const val = parseFloat(input.value) || 0;
            if (val > maxWithdrawable || val < 1000) {
                btn.disabled = true;
                btn.style.opacity = '0.5';
            } else {
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        }

        document.getElementById('withdrawalAmountInput')?.addEventListener('input', validateAmount);
    </script>
@endsection