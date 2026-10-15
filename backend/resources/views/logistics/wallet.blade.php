@extends('layouts.dashboard')

@section('title', '90% Logistics Delivery Wallet & Corporate Payouts — Logistics Fleet')
@section('role_title', 'Logistics Fleet Operations Center')
@section('page_title', '90% Delivery Fee Wallet & Payout Desk')
@section('role_badge', 'LOGISTICS HQ')
@section('role_badge_class', 'role-badge-logistics')

@section('sidebar_menu')
    @include('logistics.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('logistics.dashboard') }}">Logistics Fleet</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">90% Delivery Fee Wallet &amp; Payouts</span>
    </div>

    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">💼</span>
                <span>90% Logistics Delivery Fee Wallet &amp; Payout Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Automated mathematical 90% delivery revenue credited upon verified customer delivery OTP handshake. Victorious Market retains 10% platform fee.
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button onclick="document.getElementById('bankModal').style.display='flex'" class="btn-dash btn-dash-outline" style="padding: 7px 14px; font-size: 12px; font-weight: 700;">
                🏦 Corporate Bank Account
            </button>
            <button onclick="document.getElementById('withdrawalModal').style.display='flex'" class="btn-dash btn-dash-success" style="padding: 7px 16px; font-size: 12px; font-weight: 700;">
                💸 Request Bank Withdrawal
            </button>
        </div>
    </div>

    @if(session('status'))
        <div style="margin-bottom: 20px; padding: 12px 18px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); border: 1px solid #10B981; color: #047857; font-weight: 600; font-size: 13px;">
            ✅ {{ session('status') }}
        </div>
    @endif
    @if(session('error'))
        <div style="margin-bottom: 20px; padding: 12px 18px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border: 1px solid #EF4444; color: #B91C1C; font-weight: 600; font-size: 13px;">
            ⚠️ {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div style="margin-bottom: 20px; padding: 12px 18px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border: 1px solid #EF4444; color: #B91C1C; font-weight: 600; font-size: 13px;">
            <ul style="margin: 0; padding-left: 18px;">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- 4 Stats Cards -->
    <div class="stat-grid-4" style="margin-bottom: 24px;">
        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Corporate Wallet Balance</span>
                <span class="stat-card-value">₦{{ number_format((float)($company->wallet_balance ?? 0), 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Direct 90% Delivery Escrow</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">💼</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Delivered Runs Settled</span>
                <span class="stat-card-value">{{ count($completedRuns) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">OTP Cleared Handshakes</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">🏁</div>
        </div>

        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Couriers Online</span>
                <span class="stat-card-value">{{ $fleetMetrics['on_road'] ?? 0 }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">Across Akwa Ibom</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">🏍️</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">Settlement Formula</span>
                <span class="stat-card-value">90% / 10%</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">90% Fleet / 10% VMarket</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">⚡</div>
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
                            {{ $company->bank_name }} &bull; <span style="font-family: monospace; letter-spacing: 1px;">{{ $company->account_number }}</span>
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
                    <span class="dash-badge dash-badge-amber" style="padding: 6px 14px; font-size: 11.5px; font-weight: 700;">
                        🛡️ 48-Hour Anti-Fraud Cooldown Active (Unlocks {{ $company->bank_updated_at->addHours(48)->diffForHumans() }})
                    </span>
                @elseif($company->account_number)
                    <span class="dash-badge dash-badge-success" style="padding: 6px 14px; font-size: 11.5px; font-weight: 700;">
                        ✅ Verified &amp; Withdrawal Ready
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
                <span class="dash-card-subtitle">Track disbursements of accumulated 90% delivery revenue to your corporate bank account</span>
            </div>
            <button onclick="document.getElementById('withdrawalModal').style.display='flex'" class="btn-dash btn-dash-primary" style="padding: 6px 12px; font-size: 11.5px;">
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
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payoutRequests ?? [] as $req)
                        <tr>
                            <td>
                                <strong style="color: var(--adm-primary); font-family: monospace;">REQ-#{{ str_pad($req->id, 5, '0', STR_PAD_LEFT) }}</strong>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">{{ $req->created_at->format('d M, Y H:i') }}</div>
                            </td>
                            <td>
                                <strong style="font-size: 14.5px; color: #047857;">₦{{ number_format((float)$req->amount, 2) }}</strong>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-text);">{{ $req->bank_name }}</div>
                                <div style="font-family: monospace; font-size: 11.5px; color: var(--adm-text-muted);">{{ $req->account_number }}</div>
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
                                    <span style="font-size: 12px; color: var(--adm-text); font-weight: 600;">{{ $req->disbursed_at->format('d M, Y H:i') }}</span>
                                @else
                                    <span style="font-size: 11.5px; color: var(--adm-text-muted);">Pending Release</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--adm-text-muted);">{{ $req->admin_notes ?? 'Direct NUBAN Release' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 24px; color: var(--adm-text-muted);">
                                No withdrawal requests submitted yet. Use "Request Bank Withdrawal" to cash out your balance.
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
                        <th>Order # &amp; Date</th>
                        <th>Shopper &amp; Phone</th>
                        <th>Plaza Branch</th>
                        <th>Courier</th>
                        <th>Total Delivery Fee</th>
                        <th>90% Fleet Cut</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($completedRuns as $run)
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-primary);">{{ $run->order_number }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $run->updated_at->format('d M, Y H:i') }}</span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $run->customer->name ?? 'Shopper' }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $run->customer->phone ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ $run->shop->name ?? 'Plaza Branch' }}</span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-purple">{{ $run->rider->user->name ?? 'Courier' }}</span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">₦{{ number_format((float)($run->delivery_fee ?? 0), 2) }}</div>
                                <span style="font-size: 11px; color: var(--adm-text-muted);">VMarket 10%: ₦{{ number_format((float)($run->platform_delivery_fee ?? bcmul((string)($run->delivery_fee ?? 0), '0.10', 2)), 2) }}</span>
                            </td>
                            <td style="font-weight: 800; color: #059669;">
                                ₦{{ number_format((float)($run->logistics_fee_retained ?? bcmul((string)($run->delivery_fee ?? 0), '0.90', 2)), 2) }}
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-success">✅ Delivered &amp; Settled</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No completed delivery dispatches recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL 1: Bank Account Details -->
    <div id="bankModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 16px;">
        <div style="background: var(--adm-card-bg, #ffffff); border-radius: 14px; max-width: 480px; width: 100%; padding: 26px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: var(--adm-text);">
                    🏦 Corporate Bank Account
                </h3>
                <button onclick="document.getElementById('bankModal').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--adm-text-muted);">&times;</button>
            </div>

            <div style="font-size: 12px; color: var(--adm-text-muted); margin-bottom: 16px; background: rgba(245, 158, 11, 0.08); padding: 10px 14px; border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.2);">
                🛡️ <strong>Anti-Fraud Cooldown:</strong> Changing bank details activates a 48-hour withdrawal security lock to protect your funds against unauthorized transfers.
            </div>

            <form method="POST" action="{{ route('logistics.bank.update') }}">
                @csrf
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px;">Bank Name</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name', $company->bank_name ?? 'Zenith Bank PLC') }}" required class="form-control" placeholder="e.g. Zenith Bank, GTBank, FirstBank" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--adm-card-border, #CBD5E1);">
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px;">10-Digit NUBAN Account Number</label>
                    <input type="text" name="account_number" maxlength="10" minlength="10" pattern="[0-9]{10}" value="{{ old('account_number', $company->account_number) }}" required class="form-control" placeholder="10-digit NUBAN" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--adm-card-border, #CBD5E1); font-family: monospace; font-size: 14px;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px;">Corporate Account Name</label>
                    <input type="text" name="account_name" value="{{ old('account_name', $company->account_name ?? $company->company_name) }}" required class="form-control" placeholder="Official Corporate Name on Bank Record" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--adm-card-border, #CBD5E1);">
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="document.getElementById('bankModal').style.display='none'" class="btn-dash btn-dash-outline">Cancel</button>
                    <button type="submit" class="btn-dash btn-dash-primary" style="font-weight: 700;">Save Bank Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: Request Withdrawal -->
    <div id="withdrawalModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 16px;">
        <div style="background: var(--adm-card-bg, #ffffff); border-radius: 14px; max-width: 480px; width: 100%; padding: 26px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: var(--adm-text);">
                    💸 Request Corporate Withdrawal
                </h3>
                <button onclick="document.getElementById('withdrawalModal').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--adm-text-muted);">&times;</button>
            </div>

            <div style="background: rgba(16, 185, 129, 0.08); padding: 12px 16px; border-radius: 10px; border: 1px solid rgba(16, 185, 129, 0.2); margin-bottom: 18px;">
                <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #047857;">Available Balance for Cashout</div>
                <div style="font-size: 22px; font-weight: 900; color: #047857; margin-top: 2px;">
                    ₦{{ number_format((float)($company->wallet_balance ?? 0), 2) }}
                </div>
                <div style="font-size: 11.5px; color: var(--adm-text-muted); margin-top: 4px;">
                    Target: <strong>{{ $company->bank_name ?? 'Configured Bank' }}</strong> ({{ $company->account_number ?? 'No NUBAN' }})
                </div>
            </div>

            @if($company->isWithinBankCooldown())
                <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid #EF4444; color: #B91C1C; padding: 12px 14px; border-radius: 8px; font-size: 12.5px; margin-bottom: 16px;">
                    ⚠️ <strong>Security Cooldown Active:</strong> Bank account was updated recently. Withdrawals are temporarily locked for 48 hours for anti-fraud protection.
                </div>
            @endif

            <form method="POST" action="{{ route('logistics.payout.request') }}">
                @csrf
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin: 0;">Withdrawal Amount (₦)</label>
                        <button type="button" onclick="document.getElementById('payoutAmountInput').value='{{ (float)($company->wallet_balance ?? 0) }}'" style="background: none; border: none; color: var(--adm-primary); font-size: 11.5px; font-weight: 700; cursor: pointer; padding: 0;">
                            Withdraw All (Max)
                        </button>
                    </div>
                    <input type="number" id="payoutAmountInput" name="amount" step="0.01" min="500" max="{{ (float)($company->wallet_balance ?? 0) }}" required class="form-control" placeholder="Minimum ₦500" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--adm-card-border, #CBD5E1); font-size: 16px; font-weight: 700;">
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="document.getElementById('withdrawalModal').style.display='none'" class="btn-dash btn-dash-outline">Cancel</button>
                    <button type="submit" class="btn-dash btn-dash-success" {{ $company->isWithinBankCooldown() || (float)($company->wallet_balance ?? 0) < 500 ? 'disabled' : '' }} style="font-weight: 700;">
                        Submit Withdrawal Request
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection