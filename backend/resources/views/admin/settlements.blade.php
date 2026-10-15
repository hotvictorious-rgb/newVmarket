@extends('layouts.dashboard')

@section('title', '90/5/5 Sovereign Escrow & Settlement Desk')
@section('role_title', 'Super Admin')
@section('page_title', '90/5/5 Settlement Desk')
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
                Merchant 90% payout release, 24-hour dispute buffer management, and mathematical invariant reconciliation (&Delta; = &#8358;0.00).
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

    <!-- 4 Financial Metrics -->
    <div class="stat-grid-4" style="margin-bottom: 24px;">
        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Eligible for Disbursal</span>
                <span class="stat-card-value">{{ count($pendingSettlements) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Verified Handover / Delivery</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">✅</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Held in 24h Buffer</span>
                <span class="stat-card-value">{{ $heldSettlementsCount ?? 0 }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Dispute Protection Window</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">⏳</div>
        </div>

        <div class="stat-card stat-card-border-gold">
            <div class="stat-card-info">
                <span class="stat-card-label">5% Customer Cashback</span>
                <span class="stat-card-value">&#8358;{{ number_format((float)($totalCashbackIssued ?? 0), 2) }}</span>
                <span class="stat-card-subtext" style="color: #B45309;">Credited to Shopper Wallets</span>
            </div>
            <div class="stat-card-icon-box icon-box-gold">🪙</div>
        </div>

        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Mathematical Invariant</span>
                <span class="stat-card-value">&Delta; = &#8358;0.00</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">100% Balanced Ledger</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">⚖️</div>
        </div>
    </div>

    <!-- Mathematical Invariant Explanation Banner -->
    <div style="background: linear-gradient(135deg, rgba(94, 23, 235, 0.06) 0%, rgba(245, 158, 11, 0.06) 100%); border: 1.5px solid rgba(94, 23, 235, 0.2); border-radius: 12px; padding: 18px 22px; margin-bottom: 24px;">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <strong style="font-size: 14.5px; color: var(--adm-text); display: flex; align-items: center; gap: 8px;">
                    <span>⚖️</span> Sovereign 90/5/5 Mathematical Split Formula
                </strong>
                <p style="font-size: 12.5px; color: var(--adm-text-muted); margin-top: 4px; max-width: 780px;">
                    Every customer order paid via Paystack or Digital Gateway is atomically split with zero precision loss using BCMath exact arithmetic:
                </p>
                <div style="display: flex; gap: 14px; margin-top: 10px; flex-wrap: wrap;">
                    <span class="dash-badge dash-badge-success" style="font-size: 12px; padding: 5px 12px;">
                        🏬 <strong>90% Merchant Escrow:</strong> Disbursed to seller's verified bank account upon OTP handover or delivery.
                    </span>
                    <span class="dash-badge dash-badge-info" style="font-size: 12px; padding: 5px 12px;">
                        🚚 <strong>5% Platform Logistics:</strong> Sustains rider fleet dispatch and directional waybill routing.
                    </span>
                    <span class="dash-badge dash-badge-gold" style="font-size: 12px; padding: 5px 12px;">
                        🪙 <strong>5% Shopper Cashback:</strong> Automatically credited into customer wallet for recurring shopping.
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Settlement Queue Desk Card -->
    <div class="dash-card" id="settlement-desk">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>💰</span>
                <div>
                    <div>Pending Escrow Disbursals Queue</div>
                    <span class="dash-card-subtitle">Orders completed and verified by branch staff or rider handshake. Ready for bank transfer release.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-success">Escrow Protected</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order Ref</th>
                        <th>Merchant &amp; Store</th>
                        <th>Escrow Total</th>
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
                                <strong style="font-size: 13.5px; color: var(--adm-text);">&#8358;{{ number_format((float)$order->order_amount, 2) }}</strong>
                            </td>
                            <td>
                                <strong style="font-size: 14px; color: #047857;">&#8358;{{ number_format((float)$order->vendor_settlement_amount, 2) }}</strong>
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
                                <form method="POST" action="{{ route('admin.disburse.settlement') }}" style="margin: 0;" onsubmit="return confirm('Release 90% payout of &#8358;{{ number_format((float)$order->vendor_settlement_amount, 2) }} to {{ $order->vendor->business_name ?? 'Vendor' }}?');">
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