@extends('layouts.dashboard')

@section('title', 'Treasury Payout Approvals & Escrow Settlement Desk — Super Admin')
@section('role_title', 'Super Admin Command Tower')
@section('page_title', 'Treasury Payout Approvals & Escrow Settlement Desk')
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
                <span style="font-size: 26px;">🏛️</span>
                <span>90/5/5 Sovereign Escrow &amp; Settlement Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Dual-Commission Escrow &amp; Corporate Settlement Desk &mdash; Sovereign 90/5/5 Mathematical Split Formula &mdash; Super Admin Treasury Governance &mdash; Authorize or reject automated Paystack NUBAN bank transfers for both <strong>Merchants (90% Sales)</strong> and <strong>Logistics Fleets (90% Delivery)</strong>. Zero manual receipts. Rejections atomically restore claimant balances.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.dashboard') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Command Tower
            </a>
            <span class="dash-badge dash-badge-gold" style="padding: 6px 12px; font-size: 12px;">
                🛡️ Zero-Drift Sovereign Guarantee
            </span>
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

    <!-- 4 Financial Metrics -->
    @php
        $pendingVendorsList = $pendingVendorPayouts ?? collect();
        $pendingLogisticsList = $pendingLogisticsPayouts ?? collect();
        $vendorPendingSum = $pendingVendorsList->where('status', 'pending')->sum('amount');
        $logisticsPendingSum = $pendingLogisticsList->where('status', 'pending')->sum('amount');
    @endphp

    <div class="stat-grid-4" style="margin-bottom: 24px;">
        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">VMarket Total Commission</span>
                <span class="stat-card-value">&#8358;{{ number_format((float)($totalVMarketEarnings ?? 0), 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">5% Vendor + 10% Delivery Cuts</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">💼</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Pending Vendor Withdrawals</span>
                <span class="stat-card-value">{{ $pendingVendorsList->where('status', 'pending')->count() }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">&#8358;{{ number_format((float)$vendorPendingSum, 2) }} Queued</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">🏪</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Pending Fleet Withdrawals</span>
                <span class="stat-card-value">{{ $pendingLogisticsList->where('status', 'pending')->count() }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">&#8358;{{ number_format((float)$logisticsPendingSum, 2) }} Queued</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">🚚</div>
        </div>

        <div class="stat-card stat-card-border-gold">
            <div class="stat-card-info">
                <span class="stat-card-label">5% Customer Cashback</span>
                <span class="stat-card-value">&#8358;{{ number_format((float)($totalCashbackIssued ?? 0), 2) }}</span>
                <span class="stat-card-subtext" style="color: #B45309;">Credited to Shoppers</span>
            </div>
            <div class="stat-card-icon-box icon-box-gold">🪙</div>
        </div>
    </div>

    <!-- Escrow & Maturation Policy Settings -->
    <div class="dash-card" style="margin-bottom: 28px; border-left: 4px solid var(--adm-primary);" id="financial-policy-desk">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>⏱️</span>
                <div>
                    <div>Platform Escrow &amp; Cashback Maturation Policy</div>
                    <span class="dash-card-subtitle">Set the mandatory post-delivery inspection &amp; refund window (hours). Merchant earnings and customer cashback remain locked in escrow until this window elapses with zero active disputes.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-gold">Super Admin Governance</span>
        </div>
        <div style="padding: 20px 24px;">
            <form action="{{ route('admin.settings.financial') }}" method="POST" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
                @csrf
                <div style="flex: 1; min-width: 260px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--adm-text); margin-bottom: 6px;">
                        Refund Window &amp; Cashback Maturation Period (Hours)
                    </label>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <input type="number" name="refund_window_hours" value="{{ $refundWindowHours ?? 24 }}" min="1" max="720" required
                            style="width: 140px; padding: 10px 14px; border: 1.5px solid var(--adm-border, #E2E8F0); border-radius: 8px; font-size: 15px; font-weight: 700; color: var(--adm-text); background: var(--adm-card-bg, #fff);">
                        <span style="font-size: 13.5px; font-weight: 600; color: var(--adm-text-muted);">hours (e.g. 24 = 1 full day)</span>
                    </div>
                </div>
                <div>
                    <button type="submit" class="btn-dash btn-dash-primary" style="padding: 10px 22px; font-size: 13.5px; font-weight: 700;">
                        Save Escrow Policy
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid var(--adm-border, #E2E8F0); padding-bottom: 12px;">
        <button type="button" onclick="switchDeskTab('vendorTab')" id="tabBtn-vendorTab" class="btn-dash btn-dash-primary" style="padding: 8px 18px; font-size: 13px; font-weight: 800;">
            🏪 Merchant Payout Requests ({{ $pendingVendorsList->where('status', 'pending')->count() }})
        </button>
        <button type="button" onclick="switchDeskTab('logisticsTab')" id="tabBtn-logisticsTab" class="btn-dash btn-dash-outline" style="padding: 8px 18px; font-size: 13px; font-weight: 800;">
            🚚 Logistics Fleet Requests ({{ $pendingLogisticsList->where('status', 'pending')->count() }})
        </button>
        <button type="button" onclick="switchDeskTab('ordersTab')" id="tabBtn-ordersTab" class="btn-dash btn-dash-outline" style="padding: 8px 18px; font-size: 13px; font-weight: 800;">
            📦 Escrow Clearance Ledger ({{ count($pendingSettlements) }})
        </button>
    </div>

    <!-- TAB 1: VENDOR PAYOUT REQUESTS QUEUE -->
    <div id="vendorTab" class="desk-tab-content">
        <div class="dash-card" style="margin-bottom: 28px;">
            <div class="dash-card-header">
                <div class="dash-card-title">
                    <span>🏪</span>
                    <div>
                        <div>Merchant 90% Merchandise Escrow Queue &amp; Withdrawal Requests ({{ $pendingVendorsList->where('status', 'pending')->count() }} Pending)</div>
                        <span class="dash-card-subtitle">Merchants requesting automated Paystack bank disbursement of cleared merchandise earnings.</span>
                    </div>
                </div>
                <span class="dash-badge dash-badge-success">Merchant Treasury</span>
            </div>

            <div class="dash-table-responsive">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Request Ref &amp; Date</th>
                            <th>Merchant Store</th>
                            <th>Requested Amount</th>
                            <th>Verified NUBAN Bank</th>
                            <th>Security Status</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingVendorsList as $payout)
                            <tr>
                                <td>
                                    <strong style="font-size: 13px; color: var(--adm-primary); font-family: monospace;">REQ-#{{ str_pad($payout->id, 5, '0', STR_PAD_LEFT) }}</strong>
                                    <div style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $payout->created_at->format('d M, Y H:i') }}</div>
                                </td>
                                <td>
                                    <strong style="font-size: 13.5px; color: var(--adm-text);">{{ $payout->vendor->business_name ?? 'Merchant' }}</strong>
                                    <div style="font-size: 11px; color: var(--adm-text-muted);">Vendor ID: #{{ $payout->vendor_id }}</div>
                                </td>
                                <td>
                                    <strong style="font-size: 15px; color: #047857;">&#8358;{{ number_format((float)$payout->amount, 2) }}</strong>
                                    <div style="font-size: 10.5px; color: var(--adm-text-muted);">90% merchandise share</div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--adm-text);">{{ $payout->bank_name }}</div>
                                    <div style="font-family: monospace; font-size: 12px; color: #475569;">{{ $payout->account_number }}</div>
                                    <div style="font-size: 11px; color: var(--adm-text-muted);">Acct: {{ $payout->account_name }}</div>
                                </td>
                                <td>
                                    @if($payout->vendor && $payout->vendor->isWithinBankCooldown())
                                        <span class="dash-badge dash-badge-amber" style="font-size: 11px;">
                                            🛡️ Cooldown Active
                                        </span>
                                    @else
                                        <span class="dash-badge dash-badge-success" style="font-size: 11px;">
                                            ✅ Cooldown Passed
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($payout->status === 'approved')
                                        <span class="dash-badge dash-badge-success">✅ Disbursed</span>
                                        <div style="font-size: 10.5px; color: var(--adm-text-muted); margin-top: 2px;">{{ $payout->disbursed_at ? $payout->disbursed_at->format('d M, H:i') : '' }}</div>
                                    @elseif($payout->status === 'pending')
                                        <span class="dash-badge dash-badge-amber">⏳ Pending Review</span>
                                    @else
                                        <span class="dash-badge dash-badge-danger">❌ Rejected</span>
                                    @endif
                                </td>
                                <td>
                                    @if($payout->status === 'pending')
                                        <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                            @if($payout->processing_token === 'HOLD_TIMEOUT_RECONCILIATION')
                                                <form method="POST" action="{{ route('admin.reconcile.vendor_payout') }}" style="margin: 0;" onsubmit="return confirm('Query Paystack Gateway to verify if transfer for Payout #{{ $payout->id }} completed?');">
                                                    @csrf
                                                    <input type="hidden" name="payout_id" value="{{ $payout->id }}">
                                                    <button type="submit" class="btn-dash" style="padding: 6px 12px; font-size: 11.5px; font-weight: 700; background: #eab308; color: #000; border: none; border-radius: 4px; cursor: pointer;">
                                                        ⚡ Reconcile Hold
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.disburse.vendor_payout') }}" style="margin: 0;" onsubmit="return confirm('Authorize automated Paystack transfer of &#8358;{{ number_format((float)$payout->amount, 2) }} to {{ $payout->vendor->business_name ?? 'Merchant' }} ({{ $payout->bank_name }} - {{ $payout->account_number }})?');">
                                                    @csrf
                                                    <input type="hidden" name="payout_id" value="{{ $payout->id }}">
                                                    <button type="submit" class="btn-dash btn-dash-success" style="padding: 6px 12px; font-size: 11.5px; font-weight: 700;">
                                                        ✓ Approve (Paystack)
                                                    </button>
                                                </form>
                                            @endif

                                            <button type="button" onclick="openRejectVendorModal({{ $payout->id }}, '{{ number_format((float)$payout->amount, 2) }}', '{{ addslashes($payout->vendor->business_name ?? 'Merchant') }}')" class="btn-dash btn-dash-danger" style="padding: 6px 10px; font-size: 11.5px; font-weight: 700;">
                                                ✕ Reject
                                            </button>
                                        </div>
                                    @else
                                        <span style="font-size: 11px; color: var(--adm-text-muted);">
                                            {{ $payout->admin_notes ?? 'Settled' }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 36px 20px; color: var(--adm-text-muted);">
                                    <div style="font-size: 32px; margin-bottom: 8px;">🎉</div>
                                    <strong style="font-size: 14px; color: var(--adm-text);">No Merchant Payout Requests Pending</strong>
                                    <div style="font-size: 12px; margin-top: 4px;">All submitted merchant withdrawal requests have been processed.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: LOGISTICS FLEET PAYOUT REQUESTS QUEUE -->
    <div id="logisticsTab" class="desk-tab-content" style="display: none;">
        <div class="dash-card" style="margin-bottom: 28px;">
            <div class="dash-card-header">
                <div class="dash-card-title">
                    <span>🚚</span>
                    <div>
                        <div>Logistics Fleet Corporate Withdrawal Queue ({{ $pendingLogisticsList->where('status', 'pending')->count() }} Pending)</div>
                        <span class="dash-card-subtitle">Logistics operators requesting bank payout from their verified 90% delivery fee earnings.</span>
                    </div>
                </div>
                <span class="dash-badge dash-badge-info">Fleet Treasury</span>
            </div>

            <div class="dash-table-responsive">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Request Ref &amp; Date</th>
                            <th>Fleet Operator</th>
                            <th>Requested Amount</th>
                            <th>Verified Corporate Bank</th>
                            <th>Security Status</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingLogisticsList as $payout)
                            <tr>
                                <td>
                                    <strong style="font-size: 13px; color: var(--adm-primary); font-family: monospace;">REQ-#{{ str_pad($payout->id, 5, '0', STR_PAD_LEFT) }}</strong>
                                    <div style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $payout->created_at->format('d M, Y H:i') }}</div>
                                </td>
                                <td>
                                    <strong style="font-size: 13px; color: var(--adm-text);">{{ $payout->company->company_name ?? 'Logistics Fleet' }}</strong>
                                    <div style="font-size: 11px; color: var(--adm-text-muted);">{{ $payout->company->contact_phone ?? 'N/A' }}</div>
                                </td>
                                <td>
                                    <strong style="font-size: 14.5px; color: #047857;">&#8358;{{ number_format((float)$payout->amount, 2) }}</strong>
                                    <div style="font-size: 10.5px; color: var(--adm-text-muted);">90% delivery cut</div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--adm-text);">{{ $payout->bank_name }}</div>
                                    <div style="font-family: monospace; font-size: 12px; color: #475569;">{{ $payout->account_number }}</div>
                                    <div style="font-size: 11px; color: var(--adm-text-muted);">Acct: {{ $payout->account_name }}</div>
                                </td>
                                <td>
                                    @if($payout->company && $payout->company->isWithinBankCooldown())
                                        <span class="dash-badge dash-badge-amber" style="font-size: 11px;">
                                            🛡️ Cooldown Active
                                        </span>
                                    @else
                                        <span class="dash-badge dash-badge-success" style="font-size: 11px;">
                                            ✅ Cooldown Passed
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($payout->status === 'approved')
                                        <span class="dash-badge dash-badge-success">✅ Disbursed</span>
                                        <div style="font-size: 10.5px; color: var(--adm-text-muted); margin-top: 2px;">{{ $payout->disbursed_at ? $payout->disbursed_at->format('d M, H:i') : '' }}</div>
                                    @elseif($payout->status === 'pending')
                                        <span class="dash-badge dash-badge-amber">⏳ Pending Review</span>
                                    @else
                                        <span class="dash-badge dash-badge-danger">❌ Rejected</span>
                                    @endif
                                </td>
                                <td>
                                    @if($payout->status === 'pending')
                                        <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                            @if($payout->processing_token === 'HOLD_TIMEOUT_RECONCILIATION')
                                                <form method="POST" action="{{ route('admin.reconcile.logistics_payout') }}" style="margin: 0;" onsubmit="return confirm('Query Paystack Gateway to verify if transfer for Logistics Payout #{{ $payout->id }} completed?');">
                                                    @csrf
                                                    <input type="hidden" name="payout_id" value="{{ $payout->id }}">
                                                    <button type="submit" class="btn-dash" style="padding: 6px 12px; font-size: 11.5px; font-weight: 700; background: #eab308; color: #000; border: none; border-radius: 4px; cursor: pointer;">
                                                        ⚡ Reconcile Hold
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.disburse.logistics_payout') }}" style="margin: 0;" onsubmit="return confirm('Authorize automated Paystack transfer of &#8358;{{ number_format((float)$payout->amount, 2) }} to {{ $payout->company->company_name ?? 'Logistics Fleet' }} ({{ $payout->bank_name }} - {{ $payout->account_number }})?');">
                                                    @csrf
                                                    <input type="hidden" name="payout_id" value="{{ $payout->id }}">
                                                    <button type="submit" class="btn-dash btn-dash-success" style="padding: 6px 12px; font-size: 11.5px; font-weight: 700;">
                                                        ✓ Approve (Paystack)
                                                    </button>
                                                </form>
                                            @endif

                                            <button type="button" onclick="openRejectLogisticsModal({{ $payout->id }}, '{{ number_format((float)$payout->amount, 2) }}', '{{ addslashes($payout->company->company_name ?? 'Logistics Fleet') }}')" class="btn-dash btn-dash-danger" style="padding: 6px 10px; font-size: 11.5px; font-weight: 700;">
                                                ✕ Reject
                                            </button>
                                        </div>
                                    @else
                                        <span style="font-size: 11px; color: var(--adm-text-muted);">
                                            {{ $payout->admin_notes ?? 'Settled' }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 36px 20px; color: var(--adm-text-muted);">
                                    <div style="font-size: 32px; margin-bottom: 8px;">🚚</div>
                                    <strong style="font-size: 14px; color: var(--adm-text);">No Fleet Payout Requests Pending</strong>
                                    <div style="font-size: 12px; margin-top: 4px;">No logistics corporate withdrawal requests currently in queue.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: ESCROW CLEARING ORDERS -->
    <div id="ordersTab" class="desk-tab-content" style="display: none;">
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-title">
                    <span>📦</span>
                    <div>
                        <div>Individual Order Escrow Clearance Queue ({{ count($pendingSettlements) }} Orders Maturing)</div>
                        <span class="dash-card-subtitle">Orders completed with OTP handshake and clearing through the sovereign 24h customer inspection window.</span>
                    </div>
                </div>
                <span class="dash-badge dash-badge-gold">Audit Trail</span>
            </div>

            <div class="dash-table-responsive">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Order Ref</th>
                            <th>Merchant Store</th>
                            <th>Customer Subtotal</th>
                            <th>90% Merchant Share</th>
                            <th>Escrow Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingSettlements as $order)
                            <tr>
                                <td>
                                    <strong style="font-family: monospace; font-size: 13.5px; color: var(--adm-primary);">{{ $order->order_number }}</strong>
                                    <div style="font-size: 11.5px; color: var(--adm-text-muted);">Shopper: {{ $order->customer->name ?? 'Customer' }}</div>
                                </td>
                                <td>
                                    <strong style="font-size: 13px; color: var(--adm-text);">{{ $order->vendor->business_name ?? 'Vendor' }}</strong>
                                    <div style="font-size: 11px; color: var(--adm-text-muted);">Paid via {{ ucfirst(str_replace('_', ' ', $order->payment_method ?? 'paystack')) }}</div>
                                </td>
                                <td>
                                    <strong style="font-size: 13.5px; color: var(--adm-text);">&#8358;{{ number_format((float)($order->merchandise_subtotal ?? $order->total_amount), 2) }}</strong>
                                </td>
                                <td>
                                    <strong style="font-size: 14px; color: #047857;">&#8358;{{ number_format((float)($order->vendor_net_90 ?? $order->vendor_settlement_amount), 2) }}</strong>
                                    <div style="font-size: 10.5px; color: var(--adm-text-muted);">90.00% exact split</div>
                                </td>
                                <td>
                                    <span class="dash-badge dash-badge-success">● Cleared for Withdrawal</span>
                                    <div style="font-size: 11px; color: var(--adm-text-muted); margin-top: 2px;">Handshake Verified</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 36px 20px; color: var(--adm-text-muted);">
                                    <div style="font-size: 32px; margin-bottom: 8px;">🎉</div>
                                    <strong style="font-size: 14px; color: var(--adm-text);">All Eligible Orders Settled!</strong>
                                    <div style="font-size: 12.5px; margin-top: 4px;">No individual orders currently pending maturation in escrow.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- REJECT VENDOR PAYOUT MODAL -->
    <div id="rejectVendorModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: var(--adm-card-bg, #ffffff); border-radius: 16px; border: 1px solid var(--adm-border, #E2E8F0); width: 100%; max-width: 520px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); overflow: hidden;">
            <div style="padding: 20px 24px; border-bottom: 1px solid var(--adm-border, #E2E8F0); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #DC2626; display: flex; align-items: center; gap: 8px;">
                    <span>❌</span> Reject Merchant Withdrawal Request
                </h3>
                <button type="button" onclick="document.getElementById('rejectVendorModal').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--adm-text-muted);">&times;</button>
            </div>

            <form action="{{ route('admin.reject.vendor_payout') }}" method="POST">
                @csrf
                <input type="hidden" name="payout_id" id="rejectVendorPayoutId">
                <div style="padding: 24px;">
                    <div style="background: #FEF2F2; border: 1.5px solid #F87171; border-radius: 10px; padding: 14px; margin-bottom: 18px; font-size: 13px; color: #991B1B;">
                        <strong>Atomic Balance Restoration Guarantee:</strong> Rejecting this request for <strong id="rejectVendorAmount">₦0.00</strong> will immediately and atomically unlock these funds back into <strong id="rejectVendorName">Merchant</strong>'s withdrawable balance.
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--adm-text);">
                            Mandatory Reason for Rejection <span style="color: #DC2626;">*</span>
                        </label>
                        <textarea name="rejection_reason" required minlength="5" maxlength="1000" rows="3" class="form-control" placeholder="Explain clearly why this payout cannot be disbursed (e.g. NUBAN name mismatch with CAC, KYC document pending verification, dispute investigation active)..."
                            style="width: 100%; padding: 12px; border: 1.5px solid var(--adm-border, #E2E8F0); border-radius: 8px; font-size: 13.5px;"></textarea>
                    </div>

                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" onclick="document.getElementById('rejectVendorModal').style.display='none'" class="btn-dash btn-dash-outline" style="padding: 10px 18px; font-weight: 700;">
                            Cancel
                        </button>
                        <button type="submit" class="btn-dash btn-dash-danger" style="padding: 10px 22px; font-weight: 800;">
                            Confirm Rejection &amp; Restore Balance
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- REJECT LOGISTICS PAYOUT MODAL -->
    <div id="rejectLogisticsModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: var(--adm-card-bg, #ffffff); border-radius: 16px; border: 1px solid var(--adm-border, #E2E8F0); width: 100%; max-width: 520px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); overflow: hidden;">
            <div style="padding: 20px 24px; border-bottom: 1px solid var(--adm-border, #E2E8F0); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #DC2626; display: flex; align-items: center; gap: 8px;">
                    <span>❌</span> Reject Fleet Corporate Withdrawal Request
                </h3>
                <button type="button" onclick="document.getElementById('rejectLogisticsModal').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--adm-text-muted);">&times;</button>
            </div>

            <form action="{{ route('admin.reject.logistics_payout') }}" method="POST">
                @csrf
                <input type="hidden" name="payout_id" id="rejectLogisticsPayoutId">
                <div style="padding: 24px;">
                    <div style="background: #FEF2F2; border: 1.5px solid #F87171; border-radius: 10px; padding: 14px; margin-bottom: 18px; font-size: 13px; color: #991B1B;">
                        <strong>Atomic Wallet Refund Guarantee:</strong> Rejecting this request for <strong id="rejectLogisticsAmount">₦0.00</strong> will atomically credit the debited funds back into <strong id="rejectLogisticsName">Logistics Fleet</strong>'s corporate wallet balance with double-entry journal reversal.
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--adm-text);">
                            Mandatory Reason for Rejection <span style="color: #DC2626;">*</span>
                        </label>
                        <textarea name="rejection_reason" required minlength="5" maxlength="1000" rows="3" class="form-control" placeholder="Explain clearly why this corporate payout was rejected (e.g. Account name mismatch, active courier parcel loss investigation)..."
                            style="width: 100%; padding: 12px; border: 1.5px solid var(--adm-border, #E2E8F0); border-radius: 8px; font-size: 13.5px;"></textarea>
                    </div>

                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" onclick="document.getElementById('rejectLogisticsModal').style.display='none'" class="btn-dash btn-dash-outline" style="padding: 10px 18px; font-weight: 700;">
                            Cancel
                        </button>
                        <button type="submit" class="btn-dash btn-dash-danger" style="padding: 10px 22px; font-weight: 800;">
                            Confirm Rejection &amp; Refund Wallet
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchDeskTab(tabId) {
            document.querySelectorAll('.desk-tab-content').forEach(el => el.style.display = 'none');
            document.getElementById(tabId).style.display = 'block';

            ['vendorTab', 'logisticsTab', 'ordersTab'].forEach(t => {
                const btn = document.getElementById('tabBtn-' + t);
                if (btn) {
                    if (t === tabId) {
                        btn.className = 'btn-dash btn-dash-primary';
                    } else {
                        btn.className = 'btn-dash btn-dash-outline';
                    }
                }
            });
        }

        function openRejectVendorModal(payoutId, formattedAmount, vendorName) {
            document.getElementById('rejectVendorPayoutId').value = payoutId;
            document.getElementById('rejectVendorAmount').textContent = '₦' + formattedAmount;
            document.getElementById('rejectVendorName').textContent = vendorName;
            document.getElementById('rejectVendorModal').style.display = 'flex';
        }

        function openRejectLogisticsModal(payoutId, formattedAmount, companyName) {
            document.getElementById('rejectLogisticsPayoutId').value = payoutId;
            document.getElementById('rejectLogisticsAmount').textContent = '₦' + formattedAmount;
            document.getElementById('rejectLogisticsName').textContent = companyName;
            document.getElementById('rejectLogisticsModal').style.display = 'flex';
        }
    </script>
@endsection
