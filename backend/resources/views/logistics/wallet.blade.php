@extends('layouts.dashboard')

@section('title', 'Company Corridor Payable Wallet & Corporate Payouts — Logistics Fleet')
@section('role_title', 'Logistics Fleet Operations Center')
@section('page_title', 'Company Corridor Payable Wallet & Corporate Payout Desk')
@section('role_badge', 'LOGISTICS HQ')
@section('role_badge_class', 'role-badge-logistics')

@section('sidebar_menu')
    @include('logistics.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('logistics.dashboard') }}">Logistics Fleet</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Company Corridor Payable Wallet &amp; Payouts</span>
    </div>

    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">💼</span>
                <span>Company Corridor Payable Wallet &amp; Treasury Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Company corridor payables are credited after verified customer delivery OTP and required proof checks. Customer delivery fees and company tariffs are configured independently.
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button type="button" onclick="document.getElementById('bankModal').style.display='flex'" class="btn-dash btn-dash-outline" style="padding: 7px 14px; font-size: 12px; font-weight: 700;">
                🏦 Corporate Bank Account
            </button>
            <button type="button" onclick="document.getElementById('withdrawalModal').style.display='flex'" class="btn-dash btn-dash-success" style="padding: 7px 18px; font-size: 12.5px; font-weight: 800; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);">
                ⚡ Request Bank Withdrawal
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

    <!-- 4 Stats Cards: Total Earnings vs Withdrawable Balance -->
    <div class="stat-grid-4" style="margin-bottom: 24px;">
        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Lifetime Delivery Earnings</span>
                <span class="stat-card-value">₦{{ number_format((float)($fleetMetrics['total_delivery_earnings'] ?? 0), 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">All-Time Completed Runs</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">🚚</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">In-Transit Custody Escrow</span>
                <span class="stat-card-value">₦{{ number_format((float)($fleetMetrics['in_transit_escrow'] ?? 0), 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">Couriers on Road (Pending OTP)</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">📦</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Locked in Pending Review</span>
                <span class="stat-card-value">₦{{ number_format((float)($fleetMetrics['pending_payout'] ?? 0), 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Awaiting Super Admin Approval</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">⏳</div>
        </div>

        <div class="stat-card stat-card-border-emerald" style="border: 2px solid #10B981; background: linear-gradient(135deg, rgba(16, 185, 129, 0.04) 0%, rgba(16, 185, 129, 0.12) 100%);">
            <div class="stat-card-info">
                <span class="stat-card-label" style="font-weight: 800; color: #065F46; letter-spacing: 0.5px;">AVAILABLE CORPORATE WALLET</span>
                <span class="stat-card-value" style="color: #047857; font-size: 24px; font-weight: 900;">₦{{ number_format((float)($fleetMetrics['withdrawable_balance'] ?? $company->wallet_balance ?? 0), 2) }}</span>
                <span class="stat-card-subtext" style="color: #059669; font-weight: 700;">Cleared &amp; Ready for Withdrawal</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald" style="background: #10B981; color: #FFF;">💼</div>
        </div>
    </div>

    <!-- Corporate Bank Account & Security Cooldown Banner -->
    <div style="background: var(--adm-card-bg, #ffffff); border: 1.5px solid var(--adm-card-border, #E2E8F0); border-radius: 12px; padding: 18px 22px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="font-size: 32px; background: rgba(94, 23, 235, 0.08); width: 52px; height: 52px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    🏛️
                </div>
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--adm-text-muted); letter-spacing: 0.5px;">Registered Corporate Payout Account</div>
                    @if($company->account_number)
                        <div style="font-size: 16px; font-weight: 800; color: var(--adm-text); margin-top: 2px;">
                            {{ $company->bank_name }} &bull; <span style="font-family: monospace; letter-spacing: 1px; color: var(--adm-primary);">{{ $company->account_number }}</span>
                        </div>
                        <div style="font-size: 12px; color: var(--adm-text-muted);">
                            Account Name: <strong style="color: var(--adm-text);">{{ $company->account_name }}</strong>
                        </div>
                    @else
                        <div style="font-size: 14px; font-weight: 700; color: #DC2626; margin-top: 2px;">
                            ⚠️ No Corporate Bank Account Configured
                        </div>
                        <div style="font-size: 12px; color: var(--adm-text-muted);">
                            Click "Corporate Bank Account" to configure your NUBAN details for escrow withdrawals.
                        </div>
                    @endif
                </div>
            </div>

            <div>
                @if($company->isWithinBankCooldown())
                    <span class="dash-badge dash-badge-amber" style="padding: 7px 14px; font-size: 12px; font-weight: 700;">
                        🛡️ 48-Hour Anti-Fraud Cooldown Active (Unlocks in {{ max(1, 48 - (int)now()->diffInHours($company->bank_updated_at)) }}h)
                    </span>
                @elseif($company->account_number)
                    <span class="dash-badge dash-badge-success" style="padding: 7px 14px; font-size: 12px; font-weight: 700;">
                        ✅ Verified &amp; Ready for Instant Payout
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Corporate Withdrawal Requests Queue -->
    <div class="dash-card" style="margin-bottom: 28px;">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Corporate Bank Withdrawal Requests ({{ count($payoutRequests ?? []) }})</div>
                <span class="dash-card-subtitle">Track disbursements of approved corridor payables to your corporate bank account</span>
            </div>
            <button type="button" onclick="document.getElementById('withdrawalModal').style.display='flex'" class="btn-dash btn-dash-primary" style="padding: 6px 14px; font-size: 11.5px;">
                + New Withdrawal
            </button>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Request Ref</th>
                        <th>Amount</th>
                        <th>Bank Account</th>
                        <th>Status</th>
                        <th>Disbursed Date</th>
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
                                <div style="font-size: 10.5px; color: var(--adm-text-muted);">Approved corridor tariff payable</div>
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
                                    <span class="dash-badge dash-badge-amber">⏳ In Review</span>
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
                                        Funds atomically refunded to your corporate wallet.
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
                                <strong style="font-size: 14px; color: var(--adm-text);">No Withdrawal Requests Recorded</strong>
                                <div style="font-size: 12.5px; margin-top: 4px;">Click "Request Bank Withdrawal" to cash out your available corporate delivery earnings.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Completed Dispatches Table -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Completed Dispatches &amp; Delivery Fee Ledger ({{ count($completedRuns) }})</div>
                <span class="dash-card-subtitle">Verified dispatches finalized with customer 6-digit cryptographic OTP handshake</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order &amp; Date</th>
                        <th>Merchant Shop</th>
                        <th>Courier Rider</th>
                        <th>Gross Delivery Fee</th>
                        <th>90% Fleet Earning</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($completedRuns as $order)
                        <tr>
                            <td>
                                <strong style="font-family: monospace; font-size: 13px; color: var(--adm-primary);">{{ $order->order_number }}</strong>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">{{ $order->delivered_at ? $order->delivered_at->format('d M, Y H:i') : $order->created_at->format('d M, Y') }}</div>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-text);">{{ $order->shop->name ?? 'Merchant Shop' }}</div>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">LGA: {{ $order->shop->lga->name ?? 'N/A' }}</div>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-text);">{{ $order->rider->user->name ?? 'Courier' }}</div>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">ID: #{{ $order->rider->id ?? '—' }}</div>
                            </td>
                            <td>
                                <strong style="font-size: 13.5px; color: var(--adm-text);">₦{{ number_format((float)$order->delivery_fee, 2) }}</strong>
                            </td>
                            <td>
                                <strong style="font-size: 14px; color: #047857;">₦{{ number_format((float)bcmul((string)$order->delivery_fee, '0.90', 2), 2) }}</strong>
                                <div style="font-size: 10.5px; color: var(--adm-text-muted);">90.00% Net Credited</div>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-success">✅ OTP Verified</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 32px; color: var(--adm-text-muted);">
                                No completed delivery runs recorded for this fleet yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- WITHDRAWAL MODAL -->
    <div id="withdrawalModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: var(--adm-card-bg, #ffffff); border-radius: 16px; border: 1px solid var(--adm-border, #E2E8F0); width: 100%; max-width: 520px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); overflow: hidden;">
            <div style="padding: 20px 24px; border-bottom: 1px solid var(--adm-border, #E2E8F0); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: var(--adm-text); display: flex; align-items: center; gap: 8px;">
                    <span>⚡</span> Request Corporate Fleet Withdrawal
                </h3>
                <button type="button" onclick="document.getElementById('withdrawalModal').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--adm-text-muted);">&times;</button>
            </div>

            <form action="{{ route('logistics.payout.request') }}" method="POST" id="logisticsPayoutForm">
                @csrf
                <div style="padding: 24px;">
                    <!-- Available Balance Banner -->
                    <div style="background: #F0FDF4; border: 1.5px solid #86EFAC; border-radius: 10px; padding: 14px 18px; margin-bottom: 18px;">
                        <span style="font-size: 11.5px; text-transform: uppercase; font-weight: 800; color: #15803D;">Available Corporate Wallet Balance</span>
                        <div style="font-size: 24px; font-weight: 900; color: #166534; font-family: monospace;">
                            ₦{{ number_format((float)($company->wallet_balance ?? 0), 2) }}
                        </div>
                        <p style="font-size: 11.5px; color: #15803D; margin: 4px 0 0 0;">
                            Company corridor payables credited after verified OTP doorstep deliveries and required proof checks.
                        </p>
                    </div>

                    @if($company->isWithinBankCooldown())
                        <div style="background: #FEF3C7; border: 1.5px solid #F59E0B; border-radius: 10px; padding: 14px; margin-bottom: 18px; font-size: 12.5px; color: #92400E;">
                            ⚠️ <strong>48-Hour Bank Cooldown Active:</strong> Bank account modifications trigger a 48-hour security lock. You will be able to request withdrawals once the security lock elapses.
                        </div>
                    @endif

                    <!-- Destination Account Confirmation -->
                    <div style="background: #F8FAFC; border: 1px solid var(--adm-border, #E2E8F0); border-radius: 10px; padding: 12px 16px; margin-bottom: 18px;">
                        <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--adm-text-muted);">Destination Bank Account</span>
                        <div style="font-size: 14px; font-weight: 800; color: var(--adm-text); margin-top: 2px;">
                            {{ $company->bank_name ?? 'Not Configured' }} &bull; <span style="font-family: monospace;">{{ $company->account_number ?? '—' }}</span>
                        </div>
                        <div style="font-size: 12px; color: var(--adm-text-muted);">Acct Name: <strong>{{ $company->account_name ?? $company->company_name }}</strong></div>
                    </div>

                    <!-- Amount Input -->
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px; color: var(--adm-text);">
                            Withdrawal Amount (₦)
                        </label>
                        <input type="number" step="0.01" min="500" max="{{ (float)($company->wallet_balance ?? 0) }}" name="amount" id="logisticsAmountInput" required placeholder="Min 500.00"
                            style="width: 100%; padding: 12px 14px; border: 1.5px solid var(--adm-border, #E2E8F0); border-radius: 8px; font-size: 16px; font-weight: 800; font-family: monospace; color: var(--adm-text); background: var(--adm-card-bg, #fff);">
                    </div>

                    <!-- Quick Percentage Buttons -->
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 20px;">
                        <button type="button" onclick="setLogisticsAmount(0.25)" class="btn-dash btn-dash-outline" style="padding: 6px; font-size: 11.5px; font-weight: 700;">25%</button>
                        <button type="button" onclick="setLogisticsAmount(0.50)" class="btn-dash btn-dash-outline" style="padding: 6px; font-size: 11.5px; font-weight: 700;">50%</button>
                        <button type="button" onclick="setLogisticsAmount(0.75)" class="btn-dash btn-dash-outline" style="padding: 6px; font-size: 11.5px; font-weight: 700;">75%</button>
                        <button type="button" onclick="setLogisticsAmount(1.00)" class="btn-dash btn-dash-outline" style="padding: 6px; font-size: 11.5px; font-weight: 700; color: #047857; border-color: #10B981;">100% (Max)</button>
                    </div>

                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" onclick="document.getElementById('withdrawalModal').style.display='none'" class="btn-dash btn-dash-outline" style="padding: 10px 18px; font-weight: 700;">
                            Cancel
                        </button>
                        <button type="submit" id="submitLogisticsWithdrawalBtn" class="btn-dash btn-dash-success" style="padding: 10px 22px; font-weight: 800; font-size: 13.5px;"
                            @if($company->isWithinBankCooldown() || (float)($company->wallet_balance ?? 0) < 500) disabled @endif>
                            Submit Corporate Withdrawal
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- CORPORATE BANK MODAL -->
    <div id="bankModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: var(--adm-card-bg, #ffffff); border-radius: 16px; border: 1px solid var(--adm-border, #E2E8F0); width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); overflow: hidden;">
            <div style="padding: 20px 24px; border-bottom: 1px solid var(--adm-border, #E2E8F0); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: var(--adm-text); display: flex; align-items: center; gap: 8px;">
                    <span>🏦</span> Corporate NUBAN Bank Details
                </h3>
                <button type="button" onclick="document.getElementById('bankModal').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--adm-text-muted);">&times;</button>
            </div>

            <form action="{{ route('logistics.bank.update') }}" method="POST">
                @csrf
                <div style="padding: 24px;">
                    <div style="background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 8px; padding: 12px; margin-bottom: 18px; font-size: 12.5px; color: #92400E;">
                        🛡️ <strong>48-Hour Anti-Theft Lock:</strong> Updating your bank details triggers an automatic 48-hour security cooldown during which corporate withdrawal disbursements are locked.
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Bank Name</label>
                        <input type="text" name="bank_name" value="{{ old('bank_name', $company->bank_name) }}" required class="form-control" style="width: 100%; padding: 10px 12px; border: 1.5px solid var(--adm-border, #E2E8F0); border-radius: 8px; font-size: 13.5px;" placeholder="e.g. Zenith Bank PLC">
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Corporate Account Number (10 Digits)</label>
                        <input type="text" name="account_number" maxlength="10" pattern="[0-9]{10}" value="{{ old('account_number', $company->account_number) }}" required class="form-control" style="width: 100%; padding: 10px 12px; border: 1.5px solid var(--adm-border, #E2E8F0); border-radius: 8px; font-size: 14px; font-family: monospace;" placeholder="1012345678">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Account Name</label>
                        <input type="text" name="account_name" value="{{ old('account_name', $company->account_name ?? $company->company_name) }}" required class="form-control" style="width: 100%; padding: 10px 12px; border: 1.5px solid var(--adm-border, #E2E8F0); border-radius: 8px; font-size: 13.5px;" placeholder="Corporate registered name">
                    </div>

                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" onclick="document.getElementById('bankModal').style.display='none'" class="btn-dash btn-dash-outline" style="padding: 10px 18px; font-weight: 700;">
                            Cancel
                        </button>
                        <button type="submit" class="btn-dash btn-dash-primary" style="padding: 10px 22px; font-weight: 800;">
                            Save Corporate Account
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        const maxLogisticsWithdrawable = {{ (float)($company->wallet_balance ?? 0) }};

        function setLogisticsAmount(percentage) {
            const input = document.getElementById('logisticsAmountInput');
            if (!input) return;
            const calculated = Math.floor(maxLogisticsWithdrawable * percentage * 100) / 100;
            input.value = calculated.toFixed(2);
            validateLogisticsAmount();
        }

        function validateLogisticsAmount() {
            const input = document.getElementById('logisticsAmountInput');
            const btn = document.getElementById('submitLogisticsWithdrawalBtn');
            if (!input || !btn) return;

            const val = parseFloat(input.value) || 0;
            if (val > maxLogisticsWithdrawable || val < 500) {
                btn.disabled = true;
                btn.style.opacity = '0.5';
            } else {
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        }

        document.getElementById('logisticsAmountInput')?.addEventListener('input', validateLogisticsAmount);
    </script>
@endsection