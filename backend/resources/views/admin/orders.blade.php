@extends('layouts.dashboard')

@section('title', 'Platform Escrow Orders & Fulfillment Ledger')
@section('role_title', 'Super Admin')
@section('page_title', 'Platform Escrow Orders')
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
                <span style="font-size: 26px;">📦</span>
                <span>Platform Escrow Orders &amp; Fulfillment Ledger</span>
            </h1>
            <p class="page-header-subtitle">
                Comprehensive platform order auditing: Monitor prepaid escrows, in-shop pickup sign-offs, and rider delivery OTP handshakes.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.dashboard') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Command Tower
            </a>
            <span class="dash-badge dash-badge-purple" style="padding: 6px 12px; font-size: 12px;">
                🔒 Sovereign Escrow Locked
            </span>
        </div>
    </div>

    @if(isset($supplierRecoveryOrders) && $supplierRecoveryOrders->isNotEmpty())
        <div class="dash-card" style="margin-bottom: 24px; border: 1px solid #f59e0b;">
            <div class="dash-card-header">
                <div class="dash-card-title">
                    <span>⚠️</span>
                    <div>
                        <div>Paid Orders on Supplier-Rejection Hold</div>
                        <span class="dash-card-subtitle">Reassign only the selected items to a fresh-confirmed Uyo supplier. Original capture records and customer totals remain unchanged.</span>
                    </div>
                </div>
                <span class="dash-badge dash-badge-gold">{{ $supplierRecoveryOrders->count() }} held order(s)</span>
            </div>

            <div style="padding: 16px;">
                @foreach($supplierRecoveryOrders as $recoveryOrder)
                    <div style="border: 1px solid var(--adm-border, #e2e8f0); border-radius: 10px; padding: 16px; margin-bottom: 16px;">
                        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
                            <div>
                                <strong style="font-family:monospace;">{{ $recoveryOrder->order_number }}</strong>
                                <div style="font-size:12px;color:var(--adm-text-muted);">Paid total: ₦{{ number_format((float) $recoveryOrder->grand_total, 2) }} · Cashback: ₦{{ number_format((float) $recoveryOrder->customer_cashback_5, 2) }}</div>
                            </div>
                            <span class="dash-badge dash-badge-gold">Sourcing hold</span>
                        </div>

                        @foreach($recoveryOrder->supplierAssignments->where('status', 'supplier_rejected') as $rejectedAssignment)
                            @php
                                $assignmentItems = $recoveryOrder->items->filter(function ($item) use ($rejectedAssignment) {
                                    return (int) $item->fulfillment_supplier_vendor_id === (int) $rejectedAssignment->supplier_vendor_id
                                        && (int) $item->fulfillment_supplier_shop_id === (int) $rejectedAssignment->supplier_shop_id;
                                });
                            @endphp

                            @if($assignmentItems->isNotEmpty())
                                <div style="background:var(--adm-surface, #f8fafc); border-radius:8px; padding:12px; margin:12px 0;">
                                    <div style="font-weight:700; margin-bottom:4px;">Rejected source: {{ $rejectedAssignment->supplier->business_name ?? 'Supplier' }} — {{ $rejectedAssignment->shop->name ?? 'Uyo shop' }}</div>
                                    <div style="font-size:12px;color:var(--adm-text-muted);margin-bottom:10px;">Select the items to move. If some rejected items remain, the order stays held and cannot be dispatched.</div>

                                    <form method="POST" action="{{ route('admin.orders.supplier_assignments.recover', ['id' => $recoveryOrder->id, 'assignmentId' => $rejectedAssignment->id]) }}">
                                        @csrf
                                        <div style="display:grid; gap:8px; margin-bottom:14px;">
                                            @foreach($assignmentItems as $assignmentItem)
                                                <label style="display:flex; align-items:flex-start; gap:8px; padding:8px; border:1px solid var(--adm-border, #e2e8f0); border-radius:6px; background:#fff;">
                                                    <input type="checkbox" name="order_item_ids[]" value="{{ $assignmentItem->id }}" {{ in_array($assignmentItem->id, (array) old('order_item_ids', [])) ? 'checked' : '' }}>
                                                    <span><strong>{{ $assignmentItem->product_name }}</strong><span style="display:block;font-size:12px;color:#64748b;">Quantity: {{ $assignmentItem->quantity }} · Current agreed supplier cost: ₦{{ number_format((float) $assignmentItem->fulfillment_supplier_cost_snapshot, 2) }} per unit</span></span>
                                                </label>
                                            @endforeach
                                        </div>

                                        <label for="replacement_source_{{ $recoveryOrder->id }}_{{ $rejectedAssignment->id }}" style="display:block;font-weight:600;margin-bottom:5px;">Replacement supplier (one active Uyo shop)</label>
                                        <select id="replacement_source_{{ $recoveryOrder->id }}_{{ $rejectedAssignment->id }}" name="replacement_source" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;margin-bottom:12px;">
                                            <option value="">Choose supplier and shop</option>
                                            @foreach($activeUyoSupplierShops as $supplierShop)
                                                <option value="{{ $supplierShop->vendor_id }}:{{ $supplierShop->id }}" {{ old('replacement_source') === ($supplierShop->vendor_id . ':' . $supplierShop->id) ? 'selected' : '' }}>
                                                    {{ $supplierShop->vendor->business_name ?? $supplierShop->vendor->user->name ?? 'Supplier' }} — {{ $supplierShop->name }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <label for="recovery_reason_{{ $recoveryOrder->id }}_{{ $rejectedAssignment->id }}" style="display:block;font-weight:600;margin-bottom:5px;">Reason for recovery</label>
                                        <textarea id="recovery_reason_{{ $recoveryOrder->id }}_{{ $rejectedAssignment->id }}" name="reason" required minlength="5" maxlength="2000" rows="2" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;margin-bottom:12px;" placeholder="Record the decision and evidence for the supplier change.">{{ old('reason') }}</textarea>

                                        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $supplierRecoveryKeys[$recoveryOrder->id][$rejectedAssignment->id] ?? (string) \Illuminate\Support\Str::uuid()) }}">
                                        <div style="font-size:12px;color:#92400e;margin-bottom:10px;">The system checks fresh offer evidence and supplier-payable reconciliation. A mismatch blocks the operation without changing the order.</div>
                                        <button type="submit" class="btn-dash btn-dash-primary">Recover selected items</button>
                                    </form>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- 4 Order Metric Cards -->
    <div class="stat-grid-4" style="margin-bottom: 24px;">
        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Orders</span>
                <span class="stat-card-value">{{ $recentOrders->whereIn('order_status', ['pending', 'confirmed', 'processing', 'out_for_delivery'])->count() }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">In Fulfillment Pipeline</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">📦</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">In-Shop Pickups</span>
                <span class="stat-card-value">{{ $recentOrders->where('order_type', 'pickup')->count() }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Branch Inspection Pass</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">🏬</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Transit Deliveries</span>
                <span class="stat-card-value">{{ $recentOrders->where('order_type', '!=', 'pickup')->count() }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Dispatched via Riders</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">🚚</div>
        </div>

        <div class="stat-card stat-card-border-gold">
            <div class="stat-card-info">
                <span class="stat-card-label">Gross Escrow Value</span>
                <span class="stat-card-value">&#8358;{{ number_format((float)$recentOrders->sum('order_amount'), 2) }}</span>
                <span class="stat-card-subtext" style="color: #B45309;">Prepaid via Paystack</span>
            </div>
            <div class="stat-card-icon-box icon-box-gold">💰</div>
        </div>
    </div>

    <!-- Orders Ledger Card -->
    <div class="dash-card" id="recent-orders">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>📋</span>
                <div>
                    <div>Master Platform Orders Ledger</div>
                    <span class="dash-card-subtitle">Real-time trace of order journeys across Shopper &rarr; Vendor &rarr; Counter &rarr; Rider.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-info">Live Sync</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order Ref</th>
                        <th>Shopper</th>
                        <th>Merchant &amp; Branch</th>
                        <th>Fulfillment Mode</th>
                        <th>Amount</th>
                        <th>Order Status</th>
                        <th>Settlement Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOrders as $order)
                        <tr>
                            <td>
                                <strong style="font-family: monospace; font-size: 13.5px; color: var(--adm-primary);">{{ $order->order_number }}</strong>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">{{ $order->created_at ? $order->created_at->format('M d, H:i') : 'Recent' }}</div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $order->customer->name ?? 'Guest Shopper' }}</div>
                                <div style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $order->customer->phone ?? '' }}</div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $order->vendor->business_name ?? 'Vendor' }}</div>
                                <div style="font-size: 11.5px; color: #64748B;">Branch: {{ $order->shop->name ?? 'Main Store' }}</div>
                            </td>
                            <td>
                                @if($order->order_type === 'pickup')
                                    <span class="dash-badge dash-badge-gold" style="font-size: 11px;">
                                        🏬 In-Shop Pickup
                                    </span>
                                    <div style="font-size: 10.5px; color: #64748B; margin-top: 2px;">Counter Inspection</div>
                                @else
                                    <span class="dash-badge dash-badge-blue" style="font-size: 11px;">
                                        🚚 Doorstep Delivery
                                    </span>
                                    @if($order->rider && $order->rider->user)
                                        <div style="font-size: 11px; color: #0F172A; font-weight: 600; margin-top: 2px;">Rider: {{ $order->rider->user->name }}</div>
                                        <div style="font-size: 10px; color: #64748B;">Vendor Internal Courier</div>
                                    @else
                                        <div style="font-size: 10.5px; color: #D97706; margin-top: 2px;">Awaiting Rider Assignment</div>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <strong style="font-size: 13.5px; color: var(--adm-text);">&#8358;{{ number_format((float)$order->order_amount, 2) }}</strong>
                                <div style="font-size: 10.5px; color: var(--adm-text-muted);">Prepaid</div>
                            </td>
                            <td>
                                @php
                                    $statusMap = [
                                        'pending' => 'dash-badge-amber',
                                        'confirmed' => 'dash-badge-info',
                                        'processing' => 'dash-badge-purple',
                                        'out_for_delivery' => 'dash-badge-blue',
                                        'delivered' => 'dash-badge-success',
                                        'canceled' => 'dash-badge-danger',
                                    ];
                                    $badge = $statusMap[$order->order_status] ?? 'dash-badge-purple';
                                @endphp
                                <span class="dash-badge {{ $badge }}">
                                    ● {{ ucfirst(str_replace('_', ' ', $order->order_status)) }}
                                </span>
                            </td>
                            <td>
                                @if($order->vendor_settlement_status === 'settled')
                                    <span class="dash-badge dash-badge-success">Disbursed</span>
                                @elseif($order->vendor_settlement_status === 'eligible')
                                    <span class="dash-badge dash-badge-emerald">Ready for Payout</span>
                                @else
                                    <span class="dash-badge dash-badge-gold">Held in Escrow</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                    @php
                                        $auditModalData = [
                                            'order_number' => $order->order_number,
                                            'status' => ucfirst(str_replace('_', ' ', $order->order_status)),
                                            'customer' => $order->customer->name ?? 'Guest Shopper',
                                            'vendor' => $order->vendor->business_name ?? 'Vendor',
                                            'rider' => $order->rider->user->name ?? 'Unassigned Rider',
                                            'pickup_photo' => $order->pickup_proof_image ? asset($order->pickup_proof_image) : null,
                                            'picked_up_at' => $order->picked_up_at ? $order->picked_up_at->format('M d, H:i') : null,
                                            'delivery_photo' => $order->proof_of_delivery_image ? asset($order->proof_of_delivery_image) : null,
                                            'delivered_at' => $order->received_at ? $order->received_at->format('M d, H:i') : null,
                                            'attempt_photo' => $order->delivery_attempt_photo_path ? asset($order->delivery_attempt_photo_path) : null,
                                            'attempt_at' => $order->last_delivery_attempt_at ? $order->last_delivery_attempt_at->format('M d, H:i') : null,
                                            'rejection_photos' => is_array($order->doorstep_rejection_photos) ? array_map(fn($p) => asset($p), $order->doorstep_rejection_photos) : [],
                                            'item_defects' => $order->items ? $order->items->whereNotNull('defect_photo_path')->map(fn($it) => [
                                                'name' => $it->product_name,
                                                'status' => $it->item_delivery_status ?? $it->fulfillment_status ?? 'pending',
                                                'photo' => asset($it->defect_photo_path),
                                            ])->values()->all() : [],
                                            'return_photo' => (isset($order->returnCases) && $order->returnCases->isNotEmpty() && $order->returnCases->first()->vendor_return_photo_path) ? asset($order->returnCases->first()->vendor_return_photo_path) : null,
                                        ];
                                    @endphp
                                    <button type="button" 
                                        onclick='openAdminAuditModal(@json($auditModalData))'
                                        class="btn-dash btn-dash-primary" 
                                        style="padding: 4px 8px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px; background: #6366F1; border: none; cursor: pointer;">
                                        <span>📸 Visual Proofs</span>
                                        @php
                                            $proofCount = ($order->pickup_proof_image ? 1 : 0) 
                                                + ($order->proof_of_delivery_image ? 1 : 0) 
                                                + ($order->delivery_attempt_photo_path ? 1 : 0)
                                                + (isset($order->items) && $order->items->whereNotNull('defect_photo_path')->count() > 0 ? 1 : 0);
                                        @endphp
                                        @if($proofCount > 0)
                                            <span style="background: #FFFFFF; color: #4338CA; border-radius: 999px; padding: 1px 5px; font-size: 9.5px; font-weight: 800;">{{ $proofCount }}</span>
                                        @endif
                                    </button>
                                    <a href="{{ route('order.status', $order->order_number) }}" target="_blank" class="btn-dash btn-dash-outline" style="padding: 4px 8px; font-size: 11px;">
                                        Track ↗
                                    </a>
                                    @if($order->retailer_model_order && $order->fulfillment_type === 'doorstep_delivery' && $order->payment_status === 'paid' && $order->sourcing_status === 'supplier_confirmed' && !$order->received_at && !$order->rider_id && !in_array($order->order_status, ['delivered', 'returned', 'cancelled', 'canceled', 'refunded'], true))
                                        <form method="POST" action="{{ route('admin.orders.logistics_company.assign', $order->id) }}" style="display:grid; gap:5px; min-width:190px; margin-top:6px;">
                                            @csrf
                                            <small style="color:var(--adm-text-muted);">Assign approved logistics company</small>
                                            <select name="logistics_company_id" required style="max-width:220px;">
                                                <option value="">Select company</option>
                                                @foreach($activeLogisticsCompanies as $company)
                                                    <option value="{{ $company->id }}" @selected((int)$order->logistics_company_id === (int)$company->id)>{{ $company->company_name }}</option>
                                                @endforeach
                                            </select>
                                            <input name="reason" type="text" required maxlength="2000" placeholder="Assignment reason" aria-label="Logistics assignment reason" style="max-width:220px;">
                                            <button type="submit" class="btn-dash btn-dash-primary" style="padding:4px 8px;font-size:11px;">Assign company</button>
                                        </form>
                                    @endif
                                    @if($order->retailer_model_order && $order->fulfillment_type === 'doorstep_delivery' && $order->logistics_company_id && !$order->pickup_confirmed_by_rider && !$order->received_at && !in_array($order->order_status, ['delivered', 'returned', 'cancelled', 'canceled', 'refunded'], true))
                                        <form method="POST" action="{{ route('admin.orders.logistics_company_payable.override', $order->id) }}" style="display:grid; gap:5px; min-width:190px; margin-top:6px;">
                                            @csrf
                                            <small style="color:var(--adm-text-muted);">Company payable tariff snapshot:
                                                @if($order->logistics_company_payable_default_amount !== null)
                                                    ₦{{ number_format((float)$order->logistics_company_payable_default_amount, 2) }}
                                                @else
                                                    <strong style="color:#B91C1C;">Not configured — reconcile tariff before assignment/release.</strong>
                                                @endif
                                            </small>
                                            <input name="amount" type="number" min="0.01" step="0.01" required value="{{ $order->logistics_company_payable_amount ?? $order->logistics_company_payable_default_amount ?? '' }}" aria-label="Logistics company payable amount" style="max-width:180px;">
                                            <input name="reason" type="text" required maxlength="2000" placeholder="Reason for higher/lower amount" aria-label="Override reason" style="max-width:220px;">
                                            <button type="submit" class="btn-dash btn-dash-primary" style="padding:4px 8px;font-size:11px;">Save audited company amount</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: var(--adm-text-muted);">
                                No orders registered in the system yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Forensic Visual Audit Modal (Admin Deep Check) -->
    <div id="adminAuditModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75); z-index: 99999; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px);">
        <div style="background: #FFFFFF; border-radius: 20px; max-width: 820px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); border: 1px solid #E2E8F0;">
            
            <!-- Modal Header -->
            <div style="padding: 20px 24px; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center; background: #0F172A; color: #FFFFFF; border-radius: 20px 20px 0 0;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 18px;">🛡️</span>
                        <h3 style="font-size: 16px; font-weight: 800; margin: 0; color: #FFFFFF;" id="modalOrderNumber">Order Forensic Visual Dossier</h3>
                    </div>
                    <span style="font-size: 11.5px; color: #94A3B8; margin-top: 3px; display: block;">
                        Immutable Multi-Party Photographic Audit Trail &bull; Locked upon PIN Verification
                    </span>
                </div>
                <button type="button" onclick="closeAdminAuditModal()" style="background: rgba(255,255,255,0.1); border: none; color: #FFFFFF; width: 32px; height: 32px; border-radius: 50%; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center;">&times;</button>
            </div>

            <!-- Immutability Security Banner -->
            <div style="background: #F0FDF4; border-bottom: 1px solid #BBF7D0; padding: 10px 24px; display: flex; align-items: center; gap: 10px; font-size: 11.5px; color: #166534; font-weight: 700;">
                <span>🔒</span>
                <span>SHA-256 Verified Immutable Evidence: Photos are captured live via rear camera only, written to disk, and sealed upon OTP handshake. Re-submission or retroactive deletion is strictly blocked.</span>
            </div>

            <!-- Modal Content Body -->
            <div style="padding: 24px;" id="modalContentBody">
                <!-- Dynamically Populated by openAdminAuditModal -->
            </div>

            <!-- Modal Footer -->
            <div style="padding: 14px 24px; background: #F8FAFC; border-top: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center; border-radius: 0 0 20px 20px;">
                <span style="font-size: 11px; color: #64748B;">Victorious MARKET Sovereign Escrow Settlement Engine</span>
                <button type="button" onclick="closeAdminAuditModal()" style="background: #0F172A; color: #FFFFFF; font-weight: 700; font-size: 12px; padding: 8px 18px; border-radius: 8px; border: none; cursor: pointer;">
                    Close Dossier
                </button>
            </div>
        </div>
    </div>

    <script>
        function openAdminAuditModal(data) {
            document.getElementById('modalOrderNumber').innerText = 'Forensic Audit: ' + data.order_number + ' (' + data.status + ')';
            
            let html = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px;">
                    <div style="background: #F8FAFC; padding: 12px; border-radius: 10px; border: 1px solid #E2E8F0;">
                        <span style="font-size: 10.5px; color: #64748B; font-weight: 700; display: block; text-transform: uppercase;">Customer</span>
                        <strong style="font-size: 13px; color: #0F172A;">${data.customer}</strong>
                    </div>
                    <div style="background: #F8FAFC; padding: 12px; border-radius: 10px; border: 1px solid #E2E8F0;">
                        <span style="font-size: 10.5px; color: #64748B; font-weight: 700; display: block; text-transform: uppercase;">Merchant Shop</span>
                        <strong style="font-size: 13px; color: #0F172A;">${data.vendor}</strong>
                    </div>
                    <div style="background: #F8FAFC; padding: 12px; border-radius: 10px; border: 1px solid #E2E8F0;">
                        <span style="font-size: 10.5px; color: #64748B; font-weight: 700; display: block; text-transform: uppercase;">Vendor Assigned Rider</span>
                        <strong style="font-size: 13px; color: #0F172A;">${data.rider}</strong>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 18px;">
            `;

            // Leg 0: Courier Merchant Pickup Counter Photo
            if (data.pickup_photo) {
                html += `
                    <div style="background: #FFFFFF; border: 1.5px solid #E2E8F0; border-radius: 14px; padding: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="background: #EEF2FF; color: #4F46E5; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 6px;">LEG 0</span>
                                <strong style="font-size: 13.5px; color: #0F172A;">Merchant Shop Counter Handover Proof</strong>
                            </div>
                            <span style="font-size: 11.5px; color: #64748B;">Snapped: ${data.picked_up_at || 'Verified'}</span>
                        </div>
                        <div style="display: flex; gap: 14px; align-items: center;">
                            <img src="${data.pickup_photo}" alt="Pickup Proof" style="width: 140px; height: 100px; object-fit: cover; border-radius: 10px; border: 1px solid #CBD5E1;">
                            <div>
                                <span style="font-size: 12px; font-weight: 700; color: #059669; display: block;">✓ Dual OTP Handshake Authenticated</span>
                                <p style="font-size: 11.5px; color: #64748B; margin: 4px 0 8px;">Vendor's rider inspected package condition at vendor counter and verified shop OTP.</p>
                                <a href="${data.pickup_photo}" target="_blank" style="font-size: 11px; font-weight: 700; color: #6366F1; text-decoration: none;">View High-Res Photo ↗</a>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div style="background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 14px; padding: 14px; display: flex; align-items: center; gap: 10px; color: #64748B; font-size: 12px;">
                        <span>ℹ️</span> <span>Leg 0: Merchant shop counter pickup photo not recorded or order is in-shop collection.</span>
                    </div>
                `;
            }

            // Leg 1: Doorstep Delivery Handover Photo
            if (data.delivery_photo) {
                html += `
                    <div style="background: #FFFFFF; border: 1.5px solid #10B981; border-radius: 14px; padding: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="background: #ECFDF5; color: #059669; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 6px;">LEG 1</span>
                                <strong style="font-size: 13.5px; color: #0F172A;">Customer Doorstep Handover Proof</strong>
                            </div>
                            <span style="font-size: 11.5px; color: #64748B;">Delivered: ${data.delivered_at || 'Confirmed'}</span>
                        </div>
                        <div style="display: flex; gap: 14px; align-items: center;">
                            <img src="${data.delivery_photo}" alt="Delivery Proof" style="width: 140px; height: 100px; object-fit: cover; border-radius: 10px; border: 1px solid #10B981;">
                            <div>
                                <span style="font-size: 12px; font-weight: 700; color: #059669; display: block;">✓ Recipient Doorstep PIN Verified</span>
                                <p style="font-size: 11.5px; color: #64748B; margin: 4px 0 8px;">Vendor's rider snapped live condition proof during customer PIN verification at doorstep.</p>
                                <a href="${data.delivery_photo}" target="_blank" style="font-size: 11px; font-weight: 700; color: #10B981; text-decoration: none;">View High-Res Photo ↗</a>
                            </div>
                        </div>
                    </div>
                `;
            }

            // Leg 1-Attempt: Delivery Attempt Failure Proof (e.g. locked gate)
            if (data.attempt_photo) {
                html += `
                    <div style="background: #FFFBEB; border: 1.5px solid #F59E0B; border-radius: 14px; padding: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="background: #FEF3C7; color: #B45309; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 6px;">ATTEMPT LOG</span>
                                <strong style="font-size: 13.5px; color: #92400E;">Unreachable Customer / Gate Locked Proof</strong>
                            </div>
                            <span style="font-size: 11.5px; color: #B45309;">Attempted: ${data.attempt_at || 'Logged'}</span>
                        </div>
                        <div style="display: flex; gap: 14px; align-items: center;">
                            <img src="${data.attempt_photo}" alt="Attempt Proof" style="width: 140px; height: 100px; object-fit: cover; border-radius: 10px; border: 1px solid #F59E0B;">
                            <div>
                                <span style="font-size: 12px; font-weight: 700; color: #B45309; display: block;">⚠️ Delivery Attempt Failed Evidence</span>
                                <p style="font-size: 11.5px; color: #78350F; margin: 4px 0 8px;">Rider snapped photographic evidence of locked premises / customer unavailability at destination.</p>
                                <a href="${data.attempt_photo}" target="_blank" style="font-size: 11px; font-weight: 700; color: #B45309; text-decoration: none;">View High-Res Photo ↗</a>
                            </div>
                        </div>
                    </div>
                `;
            }

            // Line-Item Doorstep Defects
            if (data.item_defects && data.item_defects.length > 0) {
                html += `
                    <div style="background: #FEF2F2; border: 1.5px solid #EF4444; border-radius: 14px; padding: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="background: #FEE2E2; color: #991B1B; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 6px;">DEFECT MANIFEST</span>
                                <strong style="font-size: 13.5px; color: #991B1B;">Item-Level Doorstep Rejection Photos (${data.item_defects.length})</strong>
                            </div>
                            <span style="font-size: 11.5px; color: #991B1B; font-weight: 700;">Partial/Full Doorstep Rejection</span>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; margin-top: 10px;">
                `;
                data.item_defects.forEach(function(item) {
                    html += `
                        <div style="background: #FFFFFF; border: 1px solid #FECACA; border-radius: 10px; padding: 8px; text-align: center;">
                            <img src="${item.photo}" alt="${item.name}" style="width: 100%; height: 90px; object-fit: cover; border-radius: 6px; margin-bottom: 6px;">
                            <strong style="font-size: 11px; color: #0F172A; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${item.name}</strong>
                            <span style="font-size: 10px; color: #DC2626; font-weight: 800; text-transform: uppercase;">${item.status}</span>
                        </div>
                    `;
                });
                html += `</div></div>`;
            }

            // Leg 2: Vendor Return Snap
            if (data.return_photo) {
                html += `
                    <div style="background: #FAF5FF; border: 1.5px solid #A855F7; border-radius: 14px; padding: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="background: #F3E8FF; color: #7E22CE; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 6px;">LEG 2</span>
                                <strong style="font-size: 13.5px; color: #6B21A8;">Vendor Shop Return Handover Proof</strong>
                            </div>
                            <span style="font-size: 11.5px; color: #7E22CE; font-weight: 700;">Returned to Counter</span>
                        </div>
                        <div style="display: flex; gap: 14px; align-items: center;">
                            <img src="${data.return_photo}" alt="Return Proof" style="width: 140px; height: 100px; object-fit: cover; border-radius: 10px; border: 1px solid #A855F7;">
                            <div>
                                <span style="font-size: 12px; font-weight: 700; color: #7E22CE; display: block;">✓ Merchant Inward Return Verified</span>
                                <p style="font-size: 11.5px; color: #581C87; margin: 4px 0 8px;">Courier returned rejected/unreachable items to vendor shop and verified vendor OTP with live condition snap.</p>
                                <a href="${data.return_photo}" target="_blank" style="font-size: 11px; font-weight: 700; color: #7E22CE; text-decoration: none;">View High-Res Photo ↗</a>
                            </div>
                        </div>
                    </div>
                `;
            }

            html += `</div>`;
            document.getElementById('modalContentBody').innerHTML = html;
            document.getElementById('adminAuditModal').style.display = 'flex';
        }

        function closeAdminAuditModal() {
            document.getElementById('adminAuditModal').style.display = 'none';
        }
    </script>
@endsection