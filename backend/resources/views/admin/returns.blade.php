@extends('layouts.dashboard')

@section('title', 'Returns, Refunds & Inspection Desk — Super Admin')
@section('role_title', 'Super Admin Command Tower')
@section('page_title', 'Returns, Refunds & Inspection Governance Desk')
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
                <span style="font-size: 26px;">🔄</span>
                <span>Returns, Refunds &amp; Inspection Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Item-level return governance, collection point physical custody QA, and manual disbursement controls.
                Strict 24-hour return window enforcement &bull; Zero automatic fund movement.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.collection_points.index') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                📍 Collection Points Hubs
            </a>
            <span class="dash-badge dash-badge-gold" style="padding: 6px 12px; font-size: 12px;">
                🛡️ Manual Refund Disbursement Only
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

    <!-- 4 Metrics Cards -->
    <div class="stat-grid-4" style="margin-bottom: 24px;">
        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Total Return Cases</span>
                <span class="stat-card-value">{{ $metrics['total_cases'] ?? 0 }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">Item-level claims</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">📋</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Awaiting Hub Custody</span>
                <span class="stat-card-value">{{ $metrics['requested_cases'] ?? 0 }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Drop-off pending</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">📍</div>
        </div>

        <div class="stat-card stat-card-border-gold">
            <div class="stat-card-info">
                <span class="stat-card-label">Pending Refund Desk</span>
                <span class="stat-card-value">{{ $metrics['refund_pending_cases'] ?? 0 }}</span>
                <span class="stat-card-subtext" style="color: #B45309;">QA Approved &bull; Awaiting Admin</span>
            </div>
            <div class="stat-card-icon-box icon-box-gold">⏳</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Total Refunded</span>
                <span class="stat-card-value">&#8358;{{ number_format((float)($metrics['total_refunded_naira'] ?? 0), 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Verified Bank Receipts</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">💳</div>
        </div>
    </div>

    <!-- Authorised late/direct-contact claim intake: opening a case does not approve a refund. -->
    <div class="dash-card" style="margin-bottom: 24px; border-left: 4px solid #D97706;">
        <div style="margin-bottom: 14px;">
            <h2 style="font-size: 16px; font-weight: 700; margin: 0 0 6px;">Open Late / Direct-Contact Return Case</h2>
            <p style="font-size: 12px; color: var(--adm-muted); margin: 0;">
                Super Admin only. Use when a customer contacted Victorious MARKET directly or the standard 24-hour request window has passed.
                A case is opened for review only; this form does not approve or dispatch a refund. Statutory rights and warranties still apply.
            </p>
        </div>
        <form method="POST" action="{{ route('admin.returns.open_late') }}" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
            @csrf
            <div>
                <label for="late_order_id" style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Order ID</label>
                <input id="late_order_id" name="order_id" type="number" min="1" required class="form-control" placeholder="Internal order ID">
            </div>
            <div>
                <label for="late_order_item_id" style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Order Item ID</label>
                <input id="late_order_item_id" name="items[0][order_item_id]" type="number" min="1" required class="form-control" placeholder="Internal order item ID">
            </div>
            <div>
                <label for="late_item_quantity" style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Quantity</label>
                <input id="late_item_quantity" name="items[0][quantity]" type="number" min="1" value="1" required class="form-control">
            </div>
            <div>
                <label for="late_return_reason" style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Claim Reason</label>
                <select id="late_return_reason" name="return_reason" required class="form-control">
                    <option value="">Select reason</option>
                    <option value="defective">Defective / not working</option>
                    <option value="wrong_item">Wrong item supplied</option>
                    <option value="not_as_described">Not as described</option>
                    <option value="missing_parts">Missing parts / accessories</option>
                    <option value="other">Other / direct-contact claim</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label for="late_customer_description" style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Customer's Report / Evidence Summary</label>
                <textarea id="late_customer_description" name="customer_description" required minlength="10" maxlength="2000" rows="2" class="form-control" placeholder="Record what the customer reported, when they contacted Victorious MARKET, and any known evidence."></textarea>
            </div>
            <div style="grid-column: 1 / -1;">
                <label for="late_override_reason" style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Why Is Admin Opening This Late Case?</label>
                <textarea id="late_override_reason" name="override_reason" required minlength="10" maxlength="1000" rows="2" class="form-control" placeholder="Required audit reason for overriding the standard request window."></textarea>
            </div>
            <div style="grid-column: 1 / -1; display:flex; justify-content:flex-end;">
                <button type="submit" class="btn-dash btn-dash-primary">Open Case for Review</button>
            </div>
        </form>
    </div>

    <!-- Returns Table & Filter Card -->
    <div class="dash-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="{{ route('admin.returns.index') }}" class="btn-dash {{ empty($statusFilter) || $statusFilter === 'all' ? 'btn-dash-primary' : 'btn-dash-outline' }}" style="font-size: 12px; padding: 6px 12px;">
                    All Cases ({{ $metrics['total_cases'] ?? 0 }})
                </a>
                <a href="{{ route('admin.returns.index', ['status' => 'requested']) }}" class="btn-dash {{ $statusFilter === 'requested' ? 'btn-dash-primary' : 'btn-dash-outline' }}" style="font-size: 12px; padding: 6px 12px;">
                    Requested ({{ $metrics['requested_cases'] ?? 0 }})
                </a>
                <a href="{{ route('admin.returns.index', ['status' => 'inspected']) }}" class="btn-dash {{ $statusFilter === 'inspected' ? 'btn-dash-primary' : 'btn-dash-outline' }}" style="font-size: 12px; padding: 6px 12px;">
                    Hub Inspected ({{ $metrics['inspected_cases'] ?? 0 }})
                </a>
                <a href="{{ route('admin.returns.index', ['status' => 'refund_pending']) }}" class="btn-dash {{ $statusFilter === 'refund_pending' ? 'btn-dash-primary' : 'btn-dash-outline' }}" style="font-size: 12px; padding: 6px 12px;">
                    Refund Pending ({{ $metrics['refund_pending_cases'] ?? 0 }})
                </a>
                <a href="{{ route('admin.returns.index', ['status' => 'refunded']) }}" class="btn-dash {{ $statusFilter === 'refunded' ? 'btn-dash-primary' : 'btn-dash-outline' }}" style="font-size: 12px; padding: 6px 12px;">
                    Refunded
                </a>
                <a href="{{ route('admin.returns.index', ['status' => 'disputed_by_vendor']) }}" class="btn-dash {{ $statusFilter === 'disputed_by_vendor' ? 'btn-dash-primary' : 'btn-dash-outline' }}" style="font-size: 12px; padding: 6px 12px; border-color: #DC2626; color: {{ $statusFilter === 'disputed_by_vendor' ? '#fff' : '#DC2626' }}; background: {{ $statusFilter === 'disputed_by_vendor' ? '#DC2626' : 'transparent' }};">
                    ⚠️ Disputed ({{ $metrics['disputed_cases'] ?? 0 }})
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Case Number</th>
                        <th>Order &amp; Merchant</th>
                        <th>Customer</th>
                        <th>Collection Hub</th>
                        <th>Items / Claim</th>
                        <th>Requested Amount</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($returnCases as $rc)
                        <tr>
                            <td>
                                <strong style="font-size: 13.5px; font-family: monospace; color: var(--adm-text);">
                                    {{ $rc->case_number }}
                                </strong>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">
                                    {{ $rc->created_at->format('M d, Y · h:i A') }}
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 12.5px; font-weight: 600; color: var(--adm-text);">
                                    {{ $rc->order->order_number ?? 'Order #' . $rc->order_id }}
                                </div>
                                <div style="font-size: 11.5px; color: var(--adm-text-muted);">
                                    Vendor: {{ $rc->vendor->vendorProfile->business_name ?? $rc->vendor->name ?? 'Vendor #' . $rc->vendor_id }}
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 12.5px; font-weight: 600; color: var(--adm-text);">
                                    {{ $rc->customer->name ?? 'Customer' }}
                                </div>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">
                                    {{ $rc->customer->phone ?? $rc->customer->email ?? '' }}
                                </div>
                            </td>
                            <td>
                                @if($rc->collectionPoint)
                                    <span style="font-size: 12px; font-weight: 600; color: var(--adm-text);">
                                        📍 {{ $rc->collectionPoint->name }}
                                    </span>
                                    <div style="font-size: 11px; color: var(--adm-text-muted);">
                                        {{ $rc->collectionPoint->code }}
                                    </div>
                                @else
                                    <span style="font-size: 12px; color: var(--adm-text-muted);">&mdash;</span>
                                @endif
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-neutral" style="font-size: 11px;">
                                    {{ $rc->items->count() }} item(s)
                                </span>
                                <div style="font-size: 11px; color: var(--adm-text-muted); margin-top: 2px;">
                                    {{ Str::limit($rc->customer_reason, 32) }}
                                </div>
                            </td>
                            <td>
                                <strong style="font-size: 13px; color: var(--adm-text);">
                                    &#8358;{{ number_format((float)$rc->total_return_amount, 2) }}
                                </strong>
                                @if($rc->refunded_amount > 0)
                                    <div style="font-size: 11px; color: var(--adm-success); font-weight: 600;">
                                        Disbursed: &#8358;{{ number_format((float)$rc->refunded_amount, 2) }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($rc->status === 'requested')
                                    <span class="dash-badge dash-badge-info" style="font-size: 11px;">Awaiting Drop-off</span>
                                @elseif($rc->status === 'received_at_hub')
                                    <span class="dash-badge dash-badge-purple" style="font-size: 11px;">At Hub</span>
                                @elseif($rc->status === 'inspected')
                                    <span class="dash-badge dash-badge-gold" style="font-size: 11px;">Hub Inspected</span>
                                @elseif($rc->status === 'refund_pending')
                                    <span class="dash-badge dash-badge-gold" style="font-size: 11px;">Refund Pending</span>
                                @elseif($rc->status === 'refunded')
                                    <span class="dash-badge dash-badge-success" style="font-size: 11px;">Refund Disbursed</span>
                                @elseif($rc->status === 'rejected')
                                    <span class="dash-badge" style="background: rgba(239, 68, 68, 0.1); color: #DC2626; font-size: 11px;">Rejected</span>
                                @elseif($rc->status === 'disputed_by_vendor' || $rc->vendor_disputed)
                                    <span class="dash-badge" style="background: rgba(220, 38, 38, 0.15); color: #DC2626; font-size: 11px; font-weight: 700;">⚠️ Vendor Disputed</span>
                                @elseif($rc->status === 'in_transit_back_to_vendor')
                                    <span class="dash-badge dash-badge-info" style="font-size: 11px;">Rider Returning</span>
                                @elseif($rc->status === 'returned_to_vendor')
                                    <span class="dash-badge dash-badge-purple" style="font-size: 11px;">At Vendor Shop</span>
                                @elseif($rc->status === 'resolved')
                                    <span class="dash-badge dash-badge-success" style="font-size: 11px;">Dispute Arbitrated</span>
                                @else
                                    <span class="dash-badge dash-badge-neutral" style="font-size: 11px;">{{ ucfirst(str_replace('_', ' ', $rc->status)) }}</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ route('admin.returns.show', $rc->id) }}" class="btn-dash btn-dash-outline" style="font-size: 11.5px; padding: 5px 12px;">
                                    View &amp; Audit &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 36px; color: var(--adm-text-muted);">
                                No return cases found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($returnCases->hasPages())
            <div style="margin-top: 16px;">
                {{ $returnCases->links() }}
            </div>
        @endif
    </div>
@endsection
