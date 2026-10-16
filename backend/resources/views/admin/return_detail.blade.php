@extends('layouts.dashboard')

@section('title', 'Return Case #' . $case->case_number . ' — Super Admin')
@section('role_title', 'Super Admin Command Tower')
@section('page_title', 'Return Case Audit & Manual Refund Desk')
@section('role_badge', 'SUPER ADMIN')
@section('role_badge_class', 'role-badge-super-admin')

@section('sidebar_menu')
    @include('admin.partials._sidebar')
@endsection

@section('content')
    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                <a href="{{ route('admin.returns.index') }}" class="btn-dash btn-dash-outline" style="font-size: 11px; padding: 4px 8px;">
                    &larr; Returns Desk
                </a>
                <span style="font-family: monospace; font-size: 18px; font-weight: 700; color: var(--adm-text);">
                    Case #{{ $case->case_number }}
                </span>
                @if($case->status === 'requested')
                    <span class="dash-badge dash-badge-info">Awaiting Drop-off</span>
                @elseif($case->status === 'received_at_hub')
                    <span class="dash-badge dash-badge-purple">At Collection Hub</span>
                @elseif($case->status === 'inspected')
                    <span class="dash-badge dash-badge-gold">Hub Inspected</span>
                @elseif($case->status === 'refund_pending')
                    <span class="dash-badge dash-badge-gold">Refund Pending</span>
                @elseif($case->status === 'refunded')
                    <span class="dash-badge dash-badge-success">Refund Disbursed</span>
                @elseif($case->status === 'rejected')
                    <span class="dash-badge" style="background: rgba(239, 68, 68, 0.1); color: #DC2626;">Rejected</span>
                @elseif($case->status === 'disputed_by_vendor' || $case->vendor_disputed)
                    <span class="dash-badge" style="background: rgba(220, 38, 38, 0.15); color: #DC2626; font-weight: 700;">⚠️ Disputed by Vendor</span>
                @elseif($case->status === 'in_transit_back_to_vendor')
                    <span class="dash-badge dash-badge-info">Rider Returning to Shop</span>
                @elseif($case->status === 'returned_to_vendor')
                    <span class="dash-badge dash-badge-purple">Returned to Vendor Shop</span>
                @elseif($case->status === 'resolved')
                    <span class="dash-badge dash-badge-success">Arbitration Resolved</span>
                @endif
            </div>
            <p class="page-header-subtitle">
                Item-level custody verification and manual refund audit. Child-order safe &bull; Zero automatic payout reversals.
            </p>
        </div>

        <!-- Action Controls -->
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            @if(($case->vendor_disputed || $case->status === 'disputed_by_vendor') && $case->status !== 'resolved')
                <button onclick="document.getElementById('arbitrateModal').style.display='flex'" class="btn-dash btn-dash-primary" style="font-size: 12px; padding: 7px 14px; background: #7C3AED; border-color: #7C3AED;">
                    ⚖️ Arbitrate Vendor Dispute
                </button>
            @endif

            @if(in_array($case->status, ['inspected', 'received_at_hub', 'requested']))
                <button onclick="document.getElementById('approveModal').style.display='flex'" class="btn-dash btn-dash-primary" style="font-size: 12px; padding: 7px 14px;">
                    ✓ Approve for Refund
                </button>
            @endif

            @if($case->status === 'refund_pending')
                <button onclick="document.getElementById('disburseModal').style.display='flex'" class="btn-dash btn-dash-primary" style="font-size: 12px; padding: 7px 14px; background: #059669; border-color: #059669;">
                    💳 Disburse Manual Refund
                </button>
            @endif

            @if(!in_array($case->status, ['refunded', 'rejected', 'resolved']))
                <button onclick="document.getElementById('rejectModal').style.display='flex'" class="btn-dash" style="font-size: 12px; padding: 7px 14px; background: rgba(239, 68, 68, 0.1); color: #DC2626; border: 1px solid rgba(239, 68, 68, 0.3);">
                    ✕ Reject Return Case
                </button>
            @endif
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

    <!-- Two-column audit layout -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
        <!-- Left: Case Items, Evidence & Inspection -->
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <!-- Items Card -->
            <div class="dash-card">
                <h3 style="margin: 0 0 14px; font-size: 15px; font-weight: 700; color: var(--adm-text);">
                    Claimed Return Items (Item-Level Granularity)
                </h3>
                <div class="table-responsive">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Unit Price</th>
                                <th>Return Qty</th>
                                <th>Claim Subtotal</th>
                                <th>Claim Reason</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($case->items as $item)
                                <tr>
                                    <td>
                                        <strong style="color: var(--adm-text); font-size: 13px;">
                                            {{ $item->product->name ?? 'Product #' . $item->product_id }}
                                        </strong>
                                        @if($item->product && $item->product->sku)
                                            <div style="font-size: 11px; color: var(--adm-text-muted);">SKU: {{ $item->product->sku }}</div>
                                        @endif
                                    </td>
                                    <td style="font-size: 12.5px;">&#8358;{{ number_format((float)$item->unit_price, 2) }}</td>
                                    <td style="font-size: 12.5px; font-weight: 600;">{{ $item->quantity_returned }}</td>
                                    <td style="font-size: 12.5px; font-weight: 700; color: var(--adm-text);">
                                        &#8358;{{ number_format((float)$item->subtotal_amount, 2) }}
                                    </td>
                                    <td style="font-size: 12px; color: var(--adm-text-muted);">
                                        {{ ucfirst(str_replace('_', ' ', $item->condition_claimed)) }}
                                    </td>
                                    <td>
                                        <span class="dash-badge dash-badge-neutral" style="font-size: 10.5px;">
                                            {{ ucfirst($item->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 14px; padding: 12px; background: rgba(94, 23, 235, 0.04); border-radius: 8px; border: 1px solid rgba(94, 23, 235, 0.1);">
                    <div style="font-size: 12.5px; font-weight: 600; color: var(--adm-text); margin-bottom: 2px;">Customer's Stated Reason:</div>
                    <div style="font-size: 12.5px; color: var(--adm-text-muted);">{{ $case->customer_reason ?? 'None provided' }}</div>
                </div>
            </div>

            @if($case->source === 'doorstep_refusal' || $case->vendor_disputed || $case->doorstep_photo_path)
            <!-- Triangular Evidence Dossier: Doorstep Rejection, Counter Handshake & Dispute -->
            <div class="dash-card" style="border: 2px solid {{ $case->vendor_disputed ? '#DC2626' : 'var(--adm-primary)' }};">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--adm-border); padding-bottom: 12px;">
                    <div>
                        <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--adm-text); display: flex; align-items: center; gap: 8px;">
                            <span>📐</span>
                            <span>Triangular Evidence Dossier &amp; Handshake 3 Audit</span>
                        </h3>
                        <div style="font-size: 12px; color: var(--adm-text-muted); margin-top: 3px;">
                            Doorstep Rejection &bull; Counter OTP Handshake &bull; Vendor Shop Counter Dispute
                        </div>
                    </div>
                    @if($case->admin_ruling)
                        <span class="dash-badge dash-badge-success" style="font-size: 12px; padding: 4px 10px;">
                            ✓ Binding Ruling Enforced
                        </span>
                    @elseif($case->vendor_disputed)
                        <span class="dash-badge" style="background: rgba(220, 38, 38, 0.15); color: #DC2626; font-size: 12px; padding: 4px 10px; font-weight: 700;">
                            ⚠️ Super Admin Arbitration Required
                        </span>
                    @else
                        <span class="dash-badge dash-badge-info" style="font-size: 12px; padding: 4px 10px;">
                            Doorstep Refusal Tracked
                        </span>
                    @endif
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">
                    <!-- Leg 1: Doorstep Rejection Record -->
                    <div style="background: #fafafa; border: 1px solid var(--adm-border); border-radius: 8px; padding: 14px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                            <strong style="font-size: 13px; color: var(--adm-text);">🚪 Doorstep Rejection</strong>
                            <span class="dash-badge dash-badge-neutral" style="font-size: 10.5px;">Leg 1</span>
                        </div>

                        @if($case->doorstep_photo_path)
                            <div style="margin-bottom: 10px; border-radius: 6px; overflow: hidden; border: 1px solid var(--adm-border);">
                                <img src="{{ asset($case->doorstep_photo_path) }}" alt="Doorstep Evidence" style="width: 100%; height: 140px; object-fit: cover; display: block;">
                                <div style="padding: 4px 8px; font-size: 11px; background: #fff; text-align: right;">
                                    <a href="{{ asset($case->doorstep_photo_path) }}" target="_blank" style="color: var(--adm-primary); font-weight: 600;">View High-Res &rarr;</a>
                                </div>
                            </div>
                        @endif

                        <div style="font-size: 12px; display: flex; flex-direction: column; gap: 6px;">
                            <div>
                                <span style="color: var(--adm-text-muted);">Rejection Category:</span>
                                @if($case->rejection_category === 'vendor_issue')
                                    <strong style="color: #DC2626;">Vendor Issue (Wrong / Defective)</strong>
                                @elseif($case->rejection_category === 'transit_damage')
                                    <strong style="color: #D97706;">Transit Damage (Courier Mishap)</strong>
                                @elseif($case->rejection_category === 'customer_change_of_mind')
                                    <strong style="color: #2563EB;">Customer Change of Mind (50/50 Split)</strong>
                                @else
                                    <strong>{{ ucfirst(str_replace('_', ' ', $case->rejection_category ?? 'Standard')) }}</strong>
                                @endif
                            </div>
                            <div>
                                <span style="color: var(--adm-text-muted);">Customer Stated Reason:</span>
                                <div>{{ $case->customer_reason ?? 'None provided' }}</div>
                            </div>
                            <div>
                                <span style="color: var(--adm-text-muted);">Courier Rider:</span>
                                <strong>{{ $case->returnedByRider->name ?? 'Assigned Rider' }}</strong>
                            </div>
                            <div>
                                <span style="color: var(--adm-text-muted);">Vendor Return OTP:</span>
                                <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-weight: 700;">{{ $case->vendor_return_otp ?? 'N/A' }}</code>
                            </div>
                        </div>
                    </div>

                    <!-- Leg 2: Handshake 3 & Vendor Counter Dispute -->
                    <div style="background: #fafafa; border: 1px solid {{ $case->vendor_disputed ? '#DC2626' : 'var(--adm-border)' }}; border-radius: 8px; padding: 14px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                            <strong style="font-size: 13px; color: {{ $case->vendor_disputed ? '#DC2626' : 'var(--adm-text)' }};">🏪 Vendor Counter Inspection</strong>
                            <span class="dash-badge {{ $case->vendor_disputed ? 'dash-badge-danger' : 'dash-badge-neutral' }}" style="font-size: 10.5px;">Leg 2</span>
                        </div>

                        @if($case->vendor_dispute_photo_path)
                            <div style="margin-bottom: 10px; border-radius: 6px; overflow: hidden; border: 1px solid var(--adm-border);">
                                <img src="{{ asset($case->vendor_dispute_photo_path) }}" alt="Vendor Dispute Photo" style="width: 100%; height: 140px; object-fit: cover; display: block;">
                                <div style="padding: 4px 8px; font-size: 11px; background: #fff; text-align: right;">
                                    <a href="{{ asset($case->vendor_dispute_photo_path) }}" target="_blank" style="color: var(--adm-primary); font-weight: 600;">View High-Res &rarr;</a>
                                </div>
                            </div>
                        @endif

                        <div style="font-size: 12px; display: flex; flex-direction: column; gap: 6px;">
                            <div>
                                <span style="color: var(--adm-text-muted);">Vendor Shop:</span>
                                <strong>{{ $case->vendor->business_name ?? $case->vendor->name ?? 'Merchant' }}</strong>
                            </div>
                            <div>
                                <span style="color: var(--adm-text-muted);">Shop Status:</span>
                                @if($case->vendor_disputed)
                                    <span style="color: #DC2626; font-weight: 700;">⚠️ Disputed by Merchant at Counter</span>
                                @elseif($case->returned_to_vendor_at)
                                    <span style="color: #059669; font-weight: 600;">✓ Accepted by Vendor ({{ $case->returned_to_vendor_at->format('M d, Y h:i A') }})</span>
                                @else
                                    <span style="color: var(--adm-text-muted);">In transit back to vendor shop</span>
                                @endif
                            </div>
                            @if($case->vendor_disputed)
                                <div>
                                    <span style="color: var(--adm-text-muted);">Vendor Dispute Stated Reason:</span>
                                    <div style="background: rgba(220, 38, 38, 0.06); padding: 8px; border-radius: 6px; border: 1px solid rgba(220, 38, 38, 0.2); color: #B91C1C; font-weight: 500;">
                                        "{{ $case->vendor_dispute_reason }}"
                                    </div>
                                </div>
                                <div>
                                    <span style="color: var(--adm-text-muted);">Disputed At:</span>
                                    <span>{{ $case->vendor_disputed_at ? $case->vendor_disputed_at->format('M d, Y · h:i A') : 'N/A' }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Leg 3: Super Admin Binding Ruling -->
                    <div style="background: #fafafa; border: 1px solid {{ $case->admin_ruling ? '#059669' : 'var(--adm-border)' }}; border-radius: 8px; padding: 14px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                            <strong style="font-size: 13px; color: {{ $case->admin_ruling ? '#059669' : 'var(--adm-text)' }};">⚖️ Super Admin Ruling</strong>
                            <span class="dash-badge {{ $case->admin_ruling ? 'dash-badge-success' : 'dash-badge-neutral' }}" style="font-size: 10.5px;">Leg 3</span>
                        </div>

                        @if($case->admin_ruling)
                            <div style="padding: 10px; border-radius: 6px; background: rgba(16, 185, 129, 0.08); border: 1px solid #10B981; margin-bottom: 10px;">
                                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #047857; font-weight: 700;">Binding Resolution:</div>
                                <div style="font-size: 13px; font-weight: 700; color: #047857; margin-top: 2px;">
                                    @if($case->admin_ruling === 'uphold_vendor_dispute')
                                        🛡️ Uphold Vendor Dispute (Merchant Protected)
                                    @elseif($case->admin_ruling === 'uphold_customer_claim')
                                        🛑 Uphold Customer Claim (Vendor Liability)
                                    @elseif($case->admin_ruling === 'platform_transit_protection')
                                        🛡️ Platform Transit Protection (VMarket Shield)
                                    @else
                                        {{ ucfirst(str_replace('_', ' ', $case->admin_ruling)) }}
                                    @endif
                                </div>
                            </div>
                            <div style="font-size: 12px; display: flex; flex-direction: column; gap: 6px;">
                                <div>
                                    <span style="color: var(--adm-text-muted);">Cost Allocation:</span>
                                    <strong style="font-family: monospace;">{{ $case->cost_allocation }}</strong>
                                </div>
                                <div>
                                    <span style="color: var(--adm-text-muted);">Arbitrator Notes:</span>
                                    <div>{{ $case->admin_notes ?? 'Binding decision recorded in audit log.' }}</div>
                                </div>
                                <div>
                                    <span style="color: var(--adm-text-muted);">Ruled At:</span>
                                    <span>{{ $case->admin_ruled_at ? $case->admin_ruled_at->format('M d, Y · h:i A') : 'N/A' }}</span>
                                </div>
                            </div>
                        @else
                            <div style="padding: 16px; background: rgba(245, 158, 11, 0.08); border: 1px dashed #F59E0B; border-radius: 6px; font-size: 12px; color: #92400E; margin-bottom: 12px;">
                                ⏳ <strong>Pending Admin Arbitration:</strong> Review the Doorstep Photo (Leg 1) and Vendor Counter Photo (Leg 2) above to issue a binding ruling.
                            </div>
                            @if($case->vendor_disputed || $case->status === 'disputed_by_vendor')
                                <button type="button" onclick="document.getElementById('arbitrateModal').style.display='flex'" class="btn-dash btn-dash-primary" style="width: 100%; font-size: 12.5px; justify-content: center; background: #7C3AED; border-color: #7C3AED;">
                                    ⚖️ Issue Binding Arbitration Ruling
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
            @endif

            <!-- Evidence / Media Card -->
            <div class="dash-card">
                <h3 style="margin: 0 0 14px; font-size: 15px; font-weight: 700; color: var(--adm-text);">
                    Customer Photographic / Video Evidence
                </h3>
                @if($case->evidence->count() > 0)
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px;">
                        @foreach($case->evidence as $ev)
                            <div style="border: 1px solid var(--adm-border); border-radius: 8px; overflow: hidden; background: #fafafa;">
                                @if(Str::startsWith($ev->mime_type ?? '', 'image/'))
                                    <img src="{{ asset($ev->file_path) }}" alt="Evidence" style="width: 100%; height: 130px; object-fit: cover; display: block;">
                                @else
                                    <div style="height: 130px; display: flex; align-items: center; justify-content: center; font-size: 32px;">
                                        📄
                                    </div>
                                @endif
                                <div style="padding: 8px; font-size: 11px; color: var(--adm-text-muted);">
                                    <div>{{ $ev->caption ?? 'Attachment' }}</div>
                                    <a href="{{ asset($ev->file_path) }}" target="_blank" style="color: var(--adm-primary); font-weight: 600;">View full file &rarr;</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div style="padding: 24px; text-align: center; color: var(--adm-text-muted); font-size: 13px;">
                        No digital attachments provided by customer. Physical inspection at collection point is required.
                    </div>
                @endif
            </div>

            <!-- Physical Hub Inspection Card -->
            <div class="dash-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: var(--adm-text);">
                        Collection Point Physical QA Audit
                    </h3>
                    @if($case->inspections->count() > 0)
                        <span class="dash-badge dash-badge-success" style="font-size: 11px;">✓ Inspected at Hub</span>
                    @else
                        <span class="dash-badge dash-badge-gold" style="font-size: 11px;">Pending Physical Drop-off</span>
                    @endif
                </div>

                @forelse($case->inspections as $insp)
                    <div style="border: 1px solid var(--adm-border); border-radius: 8px; padding: 14px; margin-bottom: 10px; background: #FFFFFF;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <div>
                                <strong style="color: var(--adm-text); font-size: 13px;">
                                    Result: {{ ucfirst($insp->inspection_result) }}
                                </strong>
                                <span class="dash-badge dash-badge-info" style="font-size: 10.5px; margin-left: 6px;">
                                    Hub: {{ $insp->collectionPoint->name ?? 'Collection Point' }}
                                </span>
                            </div>
                            <span style="font-size: 11.5px; color: var(--adm-text-muted);">
                                {{ $insp->inspected_at ? $insp->inspected_at->format('M d, Y · h:i A') : '' }}
                            </span>
                        </div>
                        <div style="font-size: 12.5px; color: var(--adm-text-muted); margin-bottom: 4px;">
                            <strong>Physical Condition:</strong> {{ ucfirst(str_replace('_', ' ', $insp->physical_condition)) }}
                        </div>
                        @if($insp->inspector_notes)
                            <div style="font-size: 12.5px; color: var(--adm-text);">
                                <strong>Inspector Notes:</strong> {{ $insp->inspector_notes }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div style="padding: 16px; background: rgba(245, 158, 11, 0.08); border: 1px dashed #F59E0B; border-radius: 8px; font-size: 12.5px; color: #92400E;">
                        ⚠️ <strong>Physical Custody Required:</strong> Customer must drop off item(s) at designated collection point <strong>{{ $case->collectionPoint->name ?? 'Hub' }}</strong>. Hub personnel or admin must record physical inspection before manual refund disbursement.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Right: Case Overview, Window Verification, Financial Breakdown & Proof -->
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <!-- Order & Customer Summary -->
            <div class="dash-card">
                <h3 style="margin: 0 0 12px; font-size: 14px; font-weight: 700; color: var(--adm-text);">
                    Order &amp; Customer Overview
                </h3>
                <div style="display: flex; flex-direction: column; gap: 10px; font-size: 12.5px;">
                    <div>
                        <div style="color: var(--adm-text-muted); font-size: 11px;">PARENT / CHILD ORDER</div>
                        <strong>{{ $case->order->order_number ?? 'Order #' . $case->order_id }}</strong>
                    </div>
                    <div>
                        <div style="color: var(--adm-text-muted); font-size: 11px;">VENDOR / MERCHANT</div>
                        <strong>{{ $case->vendor->business_name ?? $case->vendor->name ?? 'Vendor' }}</strong>
                    </div>
                    <div>
                        <div style="color: var(--adm-text-muted); font-size: 11px;">CUSTOMER</div>
                        <strong>{{ $case->customer->name ?? 'Customer' }}</strong>
                        <div style="color: var(--adm-text-muted);">{{ $case->customer->phone ?? '' }}</div>
                    </div>
                    <div>
                        <div style="color: var(--adm-text-muted); font-size: 11px;">DESIGNATED DROP-OFF HUB</div>
                        @if($case->collectionPoint)
                            <strong>📍 {{ $case->collectionPoint->name }}</strong>
                            <div style="color: var(--adm-text-muted); font-size: 11.5px;">{{ $case->collectionPoint->address }}</div>
                        @else
                            <span style="color: var(--adm-text-muted);">&mdash;</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- 24-Hour Return Window Verification -->
            <div class="dash-card" style="border-left: 4px solid var(--adm-primary);">
                <h3 style="margin: 0 0 10px; font-size: 14px; font-weight: 700; color: var(--adm-text);">
                    ⏱️ 24h Window Audit
                </h3>
                <div style="font-size: 12px; display: flex; flex-direction: column; gap: 6px;">
                    <div>
                        <span style="color: var(--adm-text-muted);">Delivered At:</span>
                        <strong>{{ $case->order->delivered_at ? \Carbon\Carbon::parse($case->order->delivered_at)->format('M d, Y h:i A') : 'N/A' }}</strong>
                    </div>
                    <div>
                        <span style="color: var(--adm-text-muted);">Claim Filed At:</span>
                        <strong>{{ $case->created_at->format('M d, Y h:i A') }}</strong>
                    </div>
                    @if($case->order->delivered_at)
                        @php
                            $diffHours = \Carbon\Carbon::parse($case->order->delivered_at)->diffInHours($case->created_at);
                        @endphp
                        <div style="margin-top: 4px;">
                            <span class="dash-badge dash-badge-success" style="font-size: 11px;">
                                ✓ Filed within {{ $diffHours }}h (Max: 24h)
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Financial Refund Breakdown -->
            <div class="dash-card">
                <h3 style="margin: 0 0 12px; font-size: 14px; font-weight: 700; color: var(--adm-text);">
                    💰 90/5/5 Reversal Math Breakdown
                </h3>
                <div style="display: flex; flex-direction: column; gap: 8px; font-size: 12.5px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--adm-text-muted);">Item Total:</span>
                        <strong>&#8358;{{ number_format((float)$case->total_return_amount, 2) }}</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 11.5px; color: #DC2626;">
                        <span>&bull; Vendor 90% Reversal:</span>
                        <span>-&#8358;{{ number_format((float)$case->total_return_amount * 0.90, 2) }}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 11.5px; color: #4B5563;">
                        <span>&bull; VMarket 5% Cut Reversal:</span>
                        <span>-&#8358;{{ number_format((float)$case->total_return_amount * 0.05, 2) }}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 11.5px; color: #B45309;">
                        <span>&bull; Customer 5% Cashback Cancel:</span>
                        <span>-&#8358;{{ number_format((float)$case->total_return_amount * 0.05, 2) }}</span>
                    </div>
                    <hr style="border: none; border-top: 1px solid var(--adm-border); margin: 4px 0;">
                    <div style="display: flex; justify-content: space-between; font-size: 13.5px; font-weight: 700; color: var(--adm-text);">
                        <span>Shopper Refund Payable:</span>
                        <span style="color: var(--adm-success);">&#8358;{{ number_format((float)$case->total_return_amount, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Manual Refund Disbursement Proof (If Processed) -->
            @if($case->status === 'refunded')
                <div class="dash-card" style="border: 1.5px solid #10B981; background: rgba(16, 185, 129, 0.04);">
                    <h3 style="margin: 0 0 10px; font-size: 14px; font-weight: 700; color: #047857;">
                        ✓ Manual Refund Executed
                    </h3>
                    <div style="font-size: 12.5px; display: flex; flex-direction: column; gap: 6px;">
                        <div>
                            <span style="color: var(--adm-text-muted);">Amount Refunded:</span>
                            <strong style="color: #047857;">&#8358;{{ number_format((float)$case->refunded_amount, 2) }}</strong>
                        </div>
                        <div>
                            <span style="color: var(--adm-text-muted);">Bank Reference:</span>
                            <strong style="font-family: monospace;">{{ $case->refund_reference ?? 'N/A' }}</strong>
                        </div>
                        <div>
                            <span style="color: var(--adm-text-muted);">Disbursed At:</span>
                            <span>{{ $case->refunded_at ? $case->refunded_at->format('M d, Y · h:i A') : 'N/A' }}</span>
                        </div>
                        @if($case->refund_receipt_path)
                            <div style="margin-top: 6px;">
                                <a href="{{ asset($case->refund_receipt_path) }}" target="_blank" class="btn-dash btn-dash-outline" style="font-size: 11px; padding: 4px 8px;">
                                    📄 View Bank Transfer Proof
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: var(--adm-card-bg, #FFFFFF); border-radius: 12px; max-width: 480px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.2); border: 1px solid var(--adm-border); overflow: hidden;">
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--adm-border); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: var(--adm-text);">Approve Return for Refund</h3>
                <button type="button" onclick="document.getElementById('approveModal').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
            </div>
            <form action="{{ route('admin.returns.approve', $case->id) }}" method="POST" style="padding: 20px;">
                @csrf
                <p style="font-size: 13px; color: var(--adm-text-muted); margin-bottom: 14px;">
                    Approving this return case will set its status to <strong>Refund Pending</strong>. No funds will be disbursed automatically. You will manually disburse the refund on the next step.
                </p>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Admin Audit Notes (Optional)</label>
                    <textarea name="admin_notes" rows="3" placeholder="e.g. Hub physical condition verified. Approved for manual refund." style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="document.getElementById('approveModal').style.display='none'" class="btn-dash btn-dash-outline" style="font-size: 13px;">Cancel</button>
                    <button type="submit" class="btn-dash btn-dash-primary" style="font-size: 13px;">Confirm Approval</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Manual Refund Disbursement Modal -->
    <div id="disburseModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: var(--adm-card-bg, #FFFFFF); border-radius: 12px; max-width: 520px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.2); border: 1px solid var(--adm-border); overflow: hidden;">
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--adm-border); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: var(--adm-text);">Execute Manual Refund Disbursement</h3>
                <button type="button" onclick="document.getElementById('disburseModal').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
            </div>
            <form action="{{ route('admin.returns.disburse', $case->id) }}" method="POST" enctype="multipart/form-data" style="padding: 20px;">
                @csrf
                <div style="padding: 12px; background: rgba(16, 185, 129, 0.08); border: 1px solid #10B981; border-radius: 8px; margin-bottom: 16px; font-size: 13px; color: #047857;">
                    <strong>Refund Amount: &#8358;{{ number_format((float)$case->total_return_amount, 2) }}</strong>
                    <div style="font-size: 11.5px; margin-top: 2px;">
                        Customer: {{ $case->customer->name ?? 'Shopper' }} &bull; Bank transfer or gateway refund.
                    </div>
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Bank / Paystack Reference *</label>
                    <input type="text" name="bank_reference" placeholder="e.g. NIP-REF-20261016982 or Paystack Ref" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;">
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Transfer Receipt / Evidence (PDF, PNG, JPG)</label>
                    <input type="file" name="transfer_receipt" accept="image/*,application/pdf" style="width: 100%; padding: 6px; font-size: 12px;">
                </div>

                <div style="margin-bottom: 18px; display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="refund_delivery_fee" id="chkDelivFee" value="1" style="cursor: pointer;">
                    <label for="chkDelivFee" style="font-size: 12.5px; color: var(--adm-text); cursor: pointer;">
                        Also refund child delivery fee (&#8358;{{ number_format((float)($case->order->delivery_fee ?? 0), 2) }})
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="document.getElementById('disburseModal').style.display='none'" class="btn-dash btn-dash-outline" style="font-size: 13px;">Cancel</button>
                    <button type="submit" class="btn-dash btn-dash-primary" style="font-size: 13px; background: #059669; border-color: #059669;">
                        Post &amp; Balance Double-Entry Ledger
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: var(--adm-card-bg, #FFFFFF); border-radius: 12px; max-width: 480px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.2); border: 1px solid var(--adm-border); overflow: hidden;">
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--adm-border); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #DC2626;">Reject Return Case</h3>
                <button type="button" onclick="document.getElementById('rejectModal').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
            </div>
            <form action="{{ route('admin.returns.reject', $case->id) }}" method="POST" style="padding: 20px;">
                @csrf
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Reason for Rejection *</label>
                    <textarea name="rejection_reason" required rows="3" placeholder="e.g. Item broken by customer after delivery; missing original packaging." style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="document.getElementById('rejectModal').style.display='none'" class="btn-dash btn-dash-outline" style="font-size: 13px;">Cancel</button>
                    <button type="submit" class="btn-dash" style="font-size: 13px; background: #DC2626; color: #fff; border: 1px solid #DC2626;">
                        Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- Super Admin Arbitration Modal -->
    <div id="arbitrateModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: var(--adm-card-bg, #FFFFFF); border-radius: 12px; max-width: 560px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.25); border: 1px solid var(--adm-border); overflow: hidden;">
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--adm-border); display: flex; justify-content: space-between; align-items: center; background: #7C3AED; color: #fff;">
                <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px;">
                    <span>⚖️</span>
                    <span>Arbitrate Vendor Return Dispute</span>
                </h3>
                <button type="button" onclick="document.getElementById('arbitrateModal').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #fff;">&times;</button>
            </div>
            <form action="{{ route('admin.returns.arbitrate', $case->id) }}" method="POST" style="padding: 20px;">
                @csrf
                <div style="margin-bottom: 16px; font-size: 13px; color: var(--adm-text-muted);">
                    Select the binding ruling after comparing the Doorstep rejection photo against the Vendor shop counter dispute evidence.
                </div>

                <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px;">
                    <label style="display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1.5px solid var(--adm-border); border-radius: 8px; cursor: pointer; background: #fafafa;">
                        <input type="radio" name="ruling" value="uphold_vendor_dispute" required style="margin-top: 3px;">
                        <div>
                            <strong style="font-size: 13px; color: var(--adm-text);">1. Uphold Vendor Dispute (Merchant Protected)</strong>
                            <div style="font-size: 11.5px; color: var(--adm-text-muted); margin-top: 2px;">
                                Vendor packaged goods correctly. Condition altered after delivery or by transit mishandling. Return delivery fee split 50/50.
                            </div>
                        </div>
                    </label>

                    <label style="display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1.5px solid var(--adm-border); border-radius: 8px; cursor: pointer; background: #fafafa;">
                        <input type="radio" name="ruling" value="uphold_customer_claim" required style="margin-top: 3px;">
                        <div>
                            <strong style="font-size: 13px; color: var(--adm-text);">2. Uphold Customer Claim (Vendor Liability)</strong>
                            <div style="font-size: 11.5px; color: var(--adm-text-muted); margin-top: 2px;">
                                Vendor packed defective/wrong/broken item. Customer refund confirmed; delivery fee penalty debited against vendor recovery ledger.
                            </div>
                        </div>
                    </label>

                    <label style="display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1.5px solid var(--adm-border); border-radius: 8px; cursor: pointer; background: #fafafa;">
                        <input type="radio" name="ruling" value="platform_transit_protection" required style="margin-top: 3px;">
                        <div>
                            <strong style="font-size: 13px; color: var(--adm-text);">3. Platform Transit Protection (VMarket Shield)</strong>
                            <div style="font-size: 11.5px; color: var(--adm-text-muted); margin-top: 2px;">
                                Mishap occurred in-transit between vendor and customer. Vendor receives net merchandise payout; platform absorbs the transit loss.
                            </div>
                        </div>
                    </label>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Binding Arbitrator Notes *</label>
                    <textarea name="admin_notes" required rows="3" placeholder="Enter findings from the photographic evidence and reasoning for this binding decision..." style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="document.getElementById('arbitrateModal').style.display='none'" class="btn-dash btn-dash-outline" style="font-size: 13px;">Cancel</button>
                    <button type="submit" class="btn-dash btn-dash-primary" style="font-size: 13px; background: #7C3AED; border-color: #7C3AED;">
                        Confirm Binding Ruling
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
