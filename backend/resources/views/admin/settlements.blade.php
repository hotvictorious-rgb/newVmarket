@extends('layouts.dashboard')

@section('title', 'Dual-Commission Escrow & Settlement Desk — Super Admin')
@section('role_title', 'Super Admin Command Tower')
@section('page_title', 'Dual-Commission & Corporate Settlement Desk')
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
                <span style="font-size: 26px;">💰</span>
                <span>90/5/5 Sovereign Escrow &amp; Settlement Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Dual-Commission Escrow &amp; Corporate Settlement Desk &mdash; Merchant 90% payout release, Logistics Fleet 90% corporate delivery payout release, and Victorious Market Dual Commission Ledger (5% Vendor + 10% Delivery). Zero-drift guarantee (&Delta; = &#8358;0.00).
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
        <div style="margin-bottom: 20px; padding: 12px 18px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); border: 1px solid #10B981; color: #047857; font-weight: 600; font-size: 13px;">
            ✅ {{ session('status') }}
        </div>
    @endif
    @if(session('error'))
        <div style="margin-bottom: 20px; padding: 12px 18px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border: 1px solid #EF4444; color: #B91C1C; font-weight: 600; font-size: 13px;">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    <!-- 4 Financial Metrics (Dual Revenue Streams) -->
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
                <span class="stat-card-label">Eligible Merchant Escrows</span>
                <span class="stat-card-value">{{ count($pendingSettlements) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Verified OTP Releases</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">🏬</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Fleet Payout Requests</span>
                <span class="stat-card-value">{{ count($pendingLogisticsPayouts->where('status', 'pending')) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">&#8358;{{ number_format((float)($totalLogisticsEscrowBalance ?? 0), 2) }} Fleet Escrow</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">🚚</div>
        </div>

        <div class="stat-card stat-card-border-gold">
            <div class="stat-card-info">
                <span class="stat-card-label">5% Customer Cashback</span>
                <span class="stat-card-value">&#8358;{{ number_format((float)($totalCashbackIssued ?? 0), 2) }}</span>
                <span class="stat-card-subtext" style="color: #B45309;">Credited to Shopper Wallets</span>
            </div>
            <div class="stat-card-icon-box icon-box-gold">🪙</div>
        </div>
    </div>

    <!-- Mathematical Invariant Explanation Banner -->
    <div style="background: linear-gradient(135deg, rgba(94, 23, 235, 0.06) 0%, rgba(245, 158, 11, 0.06) 100%); border: 1.5px solid rgba(94, 23, 235, 0.2); border-radius: 12px; padding: 18px 22px; margin-bottom: 24px;">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <strong style="font-size: 14.5px; color: var(--adm-text); display: flex; align-items: center; gap: 8px;">
                    <span>⚖️</span> Sovereign 90/5/5 Mathematical Split Formula &amp; Dual Commission Architecture
                </strong>
                <p style="font-size: 12.5px; color: var(--adm-text-muted); margin-top: 4px; max-width: 880px;">
                    Every customer order paid via Paystack or Digital Gateway cleanly segregates the <strong>Products Amount</strong> from the <strong>Delivery Fee</strong>:
                </p>
                <div style="display: flex; gap: 14px; margin-top: 10px; flex-wrap: wrap;">
                    <span class="dash-badge dash-badge-success" style="font-size: 12px; padding: 6px 14px;">
                        🏬 <strong>Products Subtotal:</strong> 90% Vendor Net &bull; 5% Shopper Cashback &bull; 5% VMarket Vendor Commission
                    </span>
                    <span class="dash-badge dash-badge-info" style="font-size: 12px; padding: 6px 14px;">
                        🚚 <strong>Delivery Fee:</strong> 90% Logistics Company Fleet Net &bull; 10% VMarket Delivery Commission
                    </span>
                    <span class="dash-badge dash-badge-gold" style="font-size: 12px; padding: 6px 14px;">
                        🛡️ <strong>Zero Drift Proof:</strong> 100% Closed System (&Delta; = &#8358;0.00 via BCMath)
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION: Secure Platform Escrow & Cashback Maturation Policy (Super Admin Only) -->
    <div class="dash-card" style="margin-bottom: 28px; border-left: 4px solid var(--adm-primary);" id="financial-policy-desk">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>⏱️</span>
                <div>
                    <div>Platform Escrow &amp; Cashback Maturation Policy</div>
                    <span class="dash-card-subtitle">Set the mandatory post-delivery inspection &amp; refund window (hours). Cashback remains locked and unredeemable until this window elapses with zero active disputes.</span>
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
                            style="width: 140px; padding: 10px 14px; border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: 15px; font-weight: 700; color: var(--adm-text); background: var(--adm-card-bg);">
                        <span style="font-size: 13.5px; font-weight: 600; color: var(--adm-text-muted);">hours (e.g. 24 = 1 full day)</span>
                    </div>
                    <p style="font-size: 12px; color: var(--adm-text-muted); margin-top: 6px; margin-bottom: 0;">
                        🔒 All customer cashback rewards accrued from order deliveries enter <code>pending_maturation</code> state upon physical receipt and become redeemable strictly after this window expires without disputes.
                    </p>
                </div>
                <div>
                    <button type="submit" class="btn-dash btn-dash-primary" style="padding: 10px 22px; font-size: 13.5px; font-weight: 600;">
                        Save Escrow &amp; Maturation Policy
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- SECTION 1: Logistics Fleet Corporate Payout Requests -->
    <div class="dash-card" style="margin-bottom: 28px;" id="logistics-payout-desk">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>🚚</span>
                <div>
                    <div>Logistics Fleet Corporate Withdrawal Queue ({{ count($pendingLogisticsPayouts->where('status', 'pending')) }} Pending)</div>
                    <span class="dash-card-subtitle">Logistics operators requesting bank payout from their verified 90% delivery fee earnings.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-info">Fleet Escrow</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Request Date</th>
                        <th>Fleet Operator</th>
                        <th>Requested Amount</th>
                        <th>Verified Corporate Bank</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingLogisticsPayouts as $payout)
                        <tr>
                            <td>
                                <strong style="font-size: 13px; color: var(--adm-primary);">REQ-#{{ str_pad($payout->id, 5, '0', STR_PAD_LEFT) }}</strong>
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
                                    <form method="POST" action="{{ route('admin.disburse.logistics_payout') }}" style="margin: 0;" onsubmit="return confirm('Confirm disbursement of &#8358;{{ number_format((float)$payout->amount, 2) }} to {{ $payout->company->company_name }} ({{ $payout->bank_name }} - {{ $payout->account_number }})?');">
                                        @csrf
                                        <input type="hidden" name="payout_id" value="{{ $payout->id }}">
                                        <button type="submit" class="btn-dash btn-dash-success" style="padding: 6px 12px; font-size: 12px; font-weight: 700;">
                                            ✓ Disburse Corporate Payout
                                        </button>
                                    </form>
                                @else
                                    <span style="font-size: 11px; color: var(--adm-text-muted);">Settled</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 24px; color: var(--adm-text-muted);">
                                No fleet corporate payout requests currently recorded.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECTION 2: Merchant Merchandise Escrow Queue -->
    <div class="dash-card" id="settlement-desk">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>🏬</span>
                <div>
                    <div>Merchant 90% Merchandise Escrow Queue ({{ count($pendingSettlements) }} Orders Ready)</div>
                    <span class="dash-card-subtitle">Orders completed and verified by in-shop staff or customer delivery OTP handshake.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-success">Merchant Escrow</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order Ref</th>
                        <th>Merchant &amp; Store</th>
                        <th>Merchandise Subtotal</th>
                        <th>90% Merchant Share</th>
                        <th>Settlement Bank Account</th>
                        <th>Verification Status</th>
                        <th>Action</th>
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
                                <div style="font-size: 11.5px; color: var(--adm-text-muted);">Paid via {{ ucfirst(str_replace('_', ' ', $order->payment_method ?? 'paystack')) }}</div>
                            </td>
                            <td>
                                <strong style="font-size: 13.5px; color: var(--adm-text);">&#8358;{{ number_format((float)($order->merchandise_subtotal ?? $order->order_amount), 2) }}</strong>
                            </td>
                            <td>
                                <strong style="font-size: 14px; color: #047857;">&#8358;{{ number_format((float)($order->vendor_net_90 ?? $order->vendor_settlement_amount), 2) }}</strong>
                                <div style="font-size: 10.5px; color: var(--adm-text-muted);">90.00% exact split</div>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-text);">{{ $order->vendor->bank_name ?? 'Zenith Bank' }}</div>
                                <div style="font-family: monospace; font-size: 12px; color: #475569;">{{ $order->vendor->account_number ?? '1012345678' }}</div>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">Acct: {{ $order->vendor->business_name ?? 'Merchant' }}</div>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-success">● Eligible for Release</span>
                                <div style="font-size: 11px; color: var(--adm-text-muted); margin-top: 2px;">Handover Verified</div>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.disburse.settlement') }}" style="margin: 0;" onsubmit="return confirm('Release 90% payout of &#8358;{{ number_format((float)($order->vendor_net_90 ?? $order->vendor_settlement_amount), 2) }} to {{ $order->vendor->business_name ?? 'Vendor' }}?');">
                                    @csrf
                                    <input type="hidden" name="order_id" value="{{ $order->id }}">
                                    <button type="submit" class="btn-dash btn-dash-success" style="padding: 6px 12px; font-size: 12px; font-weight: 700;">
                                        ✓ Release 90% Payout
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 36px 20px; color: var(--adm-text-muted);">
                                <div style="font-size: 32px; margin-bottom: 8px;">🎉</div>
                                <strong style="font-size: 14px; color: var(--adm-text);">Settlement Desk is Current!</strong>
                                <div style="font-size: 12.5px; margin-top: 4px;">No merchant payouts are currently awaiting release. All verified escrows have been settled.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection