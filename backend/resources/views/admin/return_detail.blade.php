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
                @endif
            </div>
            <p class="page-header-subtitle">
                Item-level custody verification and manual refund audit. Child-order safe &bull; Zero automatic payout reversals.
            </p>
        </div>

        <!-- Action Controls -->
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
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

            @if(!in_array($case->status, ['refunded', 'rejected']))
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
                        <strong>{{ $case->vendor->vendorProfile->business_name ?? $case->vendor->name ?? 'Vendor' }}</strong>
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
@endsection
