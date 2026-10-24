@extends('layouts.dashboard')

@section('title', 'Manual Bank Transfer Review — Victorious MARKET')
@section('role_title', 'Super Admin Command Tower')
@section('page_title', 'Manual Bank Transfer Review')
@section('role_badge', 'SUPER ADMIN')
@section('role_badge_class', 'role-badge-super-admin')

@section('sidebar_menu')
    @include('admin.partials._sidebar')
@endsection

@section('content')
<div class="page-header-row mb-4">
    <div>
        <h1 class="page-header-title">Manual Bank Transfer Review</h1>
        <p class="page-header-subtitle">Inspect customer private receipts, independently verify actual receiving-account credit, approve payments, manage refund workflows, and disburse customer refunds with outgoing transfer proof.</p>
    </div>
    <a href="{{ route('admin.dashboard') }}" class="btn-dash btn-dash-outline">Back to Command Tower</a>
</div>

@if(session('status'))
    <div role="status" style="padding:14px;margin-bottom:18px;border:1px solid #059669;border-radius:10px;background:#ecfdf5;color:#065f46;font-weight:700">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div role="alert" style="padding:14px;margin-bottom:18px;border:1px solid #dc2626;border-radius:10px;background:#fef2f2;color:#991b1b">
        <strong>Review not completed.</strong>
        <ul style="margin:6px 0 0 20px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="dash-card mb-4" style="margin-bottom:24px">
    <div class="dash-card-header">
        <div class="dash-card-title">Payment Method &amp; Receiving Account Configuration</div>
        <strong>Status: 
            @if(!empty($paystackEnabled) && !empty($manualBankTransferEnabled))
                <span style="color:#059669">COEXISTENCE (BOTH ACTIVE)</span>
            @elseif(!empty($paystackEnabled))
                <span style="color:#2563eb">PAYSTACK ONLY</span>
            @elseif(!empty($manualBankTransferEnabled))
                <span style="color:#d97706">MANUAL BANK TRANSFER ONLY</span>
            @else
                <span style="color:#dc2626">DISABLED (CHECKOUT BLOCKED)</span>
            @endif
        </strong>
    </div>
    <form method="POST" action="{{ route('admin.manual-bank-transfers.settings') }}" style="padding:18px">
        @csrf
        <p style="margin-top:0">Paystack and direct Manual Bank Transfer can both be active simultaneously, or either can be enabled individually. If both are active, customers choose their preferred method at checkout. If both are disabled, checkout is paused. Each order remains permanently locked to its selected rail.</p>
        
        <div style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:18px;background:#f8fafc;padding:14px;border-radius:8px;border:1px solid #e2e8f0">
            <label style="display:inline-flex;align-items:center;gap:8px;font-weight:700;cursor:pointer">
                <input type="checkbox" name="paystack_enabled" value="1" @checked(!empty($paystackEnabled)) style="width:18px;height:18px;accent-color:#2563eb" />
                <span>Enable Paystack Checkout (Debit Cards, Virtual Accounts, USSD)</span>
            </label>
            <label style="display:inline-flex;align-items:center;gap:8px;font-weight:700;cursor:pointer">
                <input type="checkbox" name="manual_bank_transfer_enabled" id="manual_bank_transfer_enabled" value="1" @checked(!empty($manualBankTransferEnabled)) style="width:18px;height:18px;accent-color:#d97706" />
                <span>Enable Direct Manual Bank Transfer (Direct Bank Transfer &amp; Receipt Upload)</span>
            </label>
        </div>

        <h4 style="margin:16px 0 10px;font-size:14px;font-weight:700;color:#1e293b">Victorious MARKET Receiving Account (Required when Manual Bank Transfer is enabled)</h4>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
            <div>
                <label for="bank_name" style="display:block;font-weight:700;margin-bottom:6px">Receiving bank</label>
                <input id="bank_name" name="bank_name" maxlength="100" value="{{ old('bank_name', $bankAccount['bank_name'] ?? '') }}" style="width:100%;padding:9px" placeholder="e.g. First Bank of Nigeria" />
            </div>
            <div>
                <label for="account_name" style="display:block;font-weight:700;margin-bottom:6px">Account name</label>
                <input id="account_name" name="account_name" maxlength="150" value="{{ old('account_name', $bankAccount['account_name'] ?? '') }}" style="width:100%;padding:9px" placeholder="e.g. Victorious MARKET Ltd" />
            </div>
            <div>
                <label for="account_number" style="display:block;font-weight:700;margin-bottom:6px">10-digit account number</label>
                <input id="account_number" name="account_number" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" value="{{ old('account_number', $bankAccount['account_number'] ?? '') }}" style="width:100%;padding:9px" placeholder="e.g. 0123456789" />
            </div>
            <div>
                <label for="customer_instructions" style="display:block;font-weight:700;margin-bottom:6px">Customer transfer instructions</label>
                <textarea id="customer_instructions" name="customer_instructions" maxlength="2000" rows="3" style="width:100%;padding:9px" placeholder="Instructions shown on checkout and order tracking page...">{{ old('customer_instructions', $bankAccount['customer_instructions'] ?? '') }}</textarea>
            </div>
        </div>
        <p style="font-size:12px;color:#64748b;margin-top:12px">Bank details are public checkout instructions, not credentials. Never enter PINs, passwords, OTPs or API secrets here. Manual transfer requires an uploaded receipt plus independent actual-credit verification by an authorized admin.</p>
        <button type="submit" class="btn-dash btn-dash-primary" onclick="return confirm('Save this payment configuration? Both methods can be enabled simultaneously.')">Save payment configuration</button>
    </form>
</div>

<!-- =========================================================================
     MANUAL BANK TRANSFER REFUND RESOLUTION SECTION
     ========================================================================= -->
<div class="dash-card mb-4" style="margin-bottom:24px;border-left:4px solid #d97706">
    <div class="dash-card-header">
        <div class="dash-card-title">Manual Transfer Refund Cases &amp; Wire Resolutions</div>
        <strong>{{ $refundCases->total() }} cases recorded</strong>
    </div>
    <div style="padding:0 18px 18px">
        <p style="margin-top:0;color:#92400e">These cases represent customer refunds for orders paid via direct bank transfer. Under the Zero-Automated-Outflow Law, a manual transfer refund must NEVER be sent through Paystack API. An authorized Super Admin reviews the case, executes the refund wire transfer externally through the business bank, records outgoing transfer proof, and reconciles the debit to completion.</p>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Created</th>
                        <th>Order</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Reason / Notes</th>
                        <th>Dispatch Evidence</th>
                        <th style="min-width: 280px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($refundCases as $refundCase)
                    <tr>
                        <td>{{ $refundCase->created_at?->format('d M Y H:i:s') ?? '—' }}</td>
                        <td>
                            <strong>{{ $refundCase->order?->order_number ?? ('Order #' . $refundCase->order_id) }}</strong>
                            <div class="small text-muted">{{ $refundCase->order?->customer?->name }}</div>
                        </td>
                        <td><strong class="text-danger">₦{{ number_format($refundCase->amount_kobo / 100, 2) }}</strong> <small>{{ $refundCase->currency }}</small></td>
                        <td>
                            @if($refundCase->status === 'completed')
                                <span class="badge bg-success">COMPLETED</span>
                            @elseif($refundCase->status === 'dispatched_awaiting_reconciliation')
                                <span class="badge bg-warning text-dark">DISPATCHED (PENDING RECON)</span>
                            @elseif($refundCase->status === 'approved_for_dispatch')
                                <span class="badge bg-info text-dark">APPROVED FOR DISPATCH</span>
                            @elseif($refundCase->status === 'rejected')
                                <span class="badge bg-secondary">REJECTED</span>
                            @else
                                <span class="badge bg-warning text-dark">PENDING ADMIN REVIEW</span>
                            @endif
                        </td>
                        <td>
                            <div>{{ $refundCase->reason }}</div>
                            @if($refundCase->decision_notes)
                                <div class="small text-muted mt-1"><em>Note: {{ $refundCase->decision_notes }}</em></div>
                            @endif
                            @if($refundCase->reviewed_at)
                                <div class="small text-muted">Reviewed: {{ $refundCase->reviewed_at->format('d M Y H:i') }} ({{ $refundCase->reviewer?->name ?? 'Admin' }})</div>
                            @endif
                        </td>
                        <td>
                            @if($refundCase->manual_dispatch_reference)
                                <div><small><strong>Ref:</strong> <code>{{ $refundCase->manual_dispatch_reference }}</code></small></div>
                            @endif
                            @if($refundCase->manual_dispatch_proof_path)
                                <div class="mt-1">
                                    <a href="{{ route('admin.manual-bank-transfers.refunds.proof', $refundCase->id) }}" target="_blank" class="btn btn-xs btn-outline-info">View Proof File</a>
                                </div>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            @if($refundCase->status === 'pending_admin_review')
                                <div class="d-flex flex-column gap-2">
                                    <form method="POST" action="{{ route('admin.manual-bank-transfers.refunds.approve', $refundCase->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-primary w-100">Approve for Wire Dispatch</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.manual-bank-transfers.refunds.reject', $refundCase->id) }}" class="d-flex gap-1">
                                        @csrf
                                        <input name="rejection_reason" required minlength="5" maxlength="1000" placeholder="Rejection reason" class="form-control form-control-sm">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                    </form>
                                </div>
                            @elseif($refundCase->status === 'approved_for_dispatch')
                                <div class="border rounded p-2 bg-light">
                                    <div class="small fw-bold text-primary mb-1">Execute External Wire Transfer:</div>
                                    <form method="POST" action="{{ route('admin.manual-bank-transfers.refunds.dispatch', $refundCase->id) }}" enctype="multipart/form-data" class="d-flex flex-column gap-2">
                                        @csrf
                                        <input type="text" name="manual_dispatch_reference" required placeholder="Bank Transaction Reference" class="form-control form-control-sm font-monospace" pattern="[A-Za-z0-9._\/-]+" autocomplete="off">
                                        <input type="file" name="dispatch_proof" required accept="image/*,.pdf" class="form-control form-control-sm">
                                        <input type="text" name="decision_notes" placeholder="Notes (optional)" class="form-control form-control-sm">
                                        <button class="btn btn-sm btn-warning w-100" type="submit">Record Wire Dispatched</button>
                                    </form>
                                </div>
                            @elseif($refundCase->status === 'dispatched_awaiting_reconciliation')
                                <form method="POST" action="{{ route('admin.manual-bank-transfers.refunds.reconcile', $refundCase->id) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-success w-100" type="submit">Confirm Debit &amp; Reconcile to Completed</button>
                                </form>
                            @else
                                <span class="text-muted small">Terminal</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-3">No manual-transfer refund cases recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $refundCases->links() }}
        </div>
    </div>
</div>

<!-- =========================================================================
     RECEIPT SUBMISSIONS SECTION
     ========================================================================= -->
<div class="dash-card">
    <div class="dash-card-header">
        <div class="dash-card-title">Receipt Submissions &amp; Incoming Verifications</div>
        <span class="dash-badge dash-badge-gold">{{ $payments->where('status', 'pending_review')->count() }} pending review</span>
    </div>
    <div class="table-responsive" style="padding:0 18px 18px">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>Submitted</th><th>Order / Customer</th><th>Expected</th><th>Receipt</th><th>Status</th><th>Decision</th>
                </tr>
            </thead>
            <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td>{{ $payment->submitted_at?->format('d M Y H:i:s') ?? '—' }}</td>
                    <td>
                        <strong>{{ $payment->order?->order_number ?? 'Order unavailable' }}</strong>
                        <div>{{ $payment->customer?->name ?? 'Customer' }}</div>
                        <small>{{ $payment->customer?->email ?? '' }}</small>
                    </td>
                    <td>
                        <strong>₦{{ number_format((float) ($payment->order?->grand_total ?? 0), 2) }}</strong>
                        <div><small>Order payment status: {{ strtoupper((string) ($payment->order?->payment_status ?? 'unknown')) }}</small></div>
                    </td>
                    <td>
                        <a href="{{ route('admin.manual-bank-transfers.evidence', $payment->id) }}" target="_blank" rel="noopener noreferrer" class="btn btn-xs btn-outline-primary">Open private receipt</a>
                        <div><small>{{ strtoupper(str_replace('image/', '', $payment->evidence_mime)) }} · {{ number_format($payment->evidence_size_bytes / 1024, 1) }} KB</small></div>
                        <div><small class="text-muted">SHA-256: {{ substr($payment->evidence_sha256, 0, 12) }}...</small></div>
                        @if(is_array($payment->evidence_history) && count($payment->evidence_history) > 1)
                            <div><span class="badge bg-info-subtle text-info border">{{ count($payment->evidence_history) }} versions</span></div>
                        @endif
                    </td>
                    <td>
                        @if($payment->status === 'evidence_requested')
                            <span class="badge bg-warning text-dark">REPLACEMENT REQUESTED</span>
                        @else
                            <strong>{{ strtoupper(str_replace('_', ' ', $payment->status)) }}</strong>
                        @endif
                        @if($payment->reviewed_at)
                            <div><small>Reviewed {{ $payment->reviewed_at->format('d M Y H:i') }}</small></div>
                            <div><small>By {{ $payment->reviewer?->name ?? 'Former admin' }}</small></div>
                        @endif
                        @if($payment->bank_transaction_reference)
                            <div><small>Bank ref: {{ $payment->bank_transaction_reference }}</small></div>
                        @endif
                        @if($payment->decision_reason)
                            <div><small class="text-muted">{{ $payment->decision_reason }}</small></div>
                        @endif
                    </td>
                    <td style="min-width:300px">
                    @if(in_array($payment->status, ['pending_review', 'evidence_requested'], true))
                        <form method="POST" action="{{ route('admin.manual-bank-transfers.approve', $payment->id) }}" style="padding:12px;border:1px solid #d1d5db;border-radius:8px;margin-bottom:12px">
                            @csrf
                            <strong>Approve only after checking actual bank credit</strong>
                            <label for="bankref-{{ $payment->id }}" style="display:block;margin-top:8px">Bank transaction reference</label>
                            <input id="bankref-{{ $payment->id }}" name="bank_transaction_reference" required maxlength="150" pattern="[A-Za-z0-9._/-]+" autocomplete="off" style="width:100%;padding:8px" />
                            <label for="amount-{{ $payment->id }}" style="display:block;margin-top:8px">Actual credited amount (₦)</label>
                            <input id="amount-{{ $payment->id }}" name="credited_amount" required inputmode="decimal" pattern="\d{1,12}(\.\d{1,2})?" value="{{ number_format((float) ($payment->order?->grand_total ?? 0), 2, '.', '') }}" style="width:100%;padding:8px" />
                            <label for="reason-{{ $payment->id }}" style="display:block;margin-top:8px">Review note (optional)</label>
                            <textarea id="reason-{{ $payment->id }}" name="decision_reason" maxlength="2000" rows="2" style="width:100%;padding:8px"></textarea>
                            <label style="display:flex;gap:8px;align-items:flex-start;margin:10px 0">
                                <input type="checkbox" name="confirm_bank_credit" value="1" required style="margin-top:4px" />
                                <span>I independently verified that this exact amount is credited and available in the official receiving account, and that this bank transaction has not paid another order.</span>
                            </label>
                            <button type="submit" class="btn-dash btn-dash-primary" onclick="return confirm('Confirm that you independently verified the actual bank credit? This approves payment and starts post-payment sourcing.')">Verify &amp; approve</button>
                        </form>
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('admin.manual-bank-transfers.request-replacement', $payment->id) }}" style="padding:10px;border:1px solid #fed7aa;border-radius:8px;flex:1">
                                @csrf
                                <strong>Request Clearer Receipt</strong>
                                <input name="reason" required minlength="3" maxlength="500" placeholder="e.g. Blurry or incomplete receipt" class="form-control form-control-sm mt-1">
                                <button type="submit" class="btn btn-sm btn-outline-warning mt-1 w-100">Request Replacement</button>
                            </form>
                            <form method="POST" action="{{ route('admin.manual-bank-transfers.reject', $payment->id) }}" style="padding:10px;border:1px solid #fecaca;border-radius:8px;flex:1">
                                @csrf
                                <strong>Reject Terminal</strong>
                                <input name="decision_reason" required minlength="5" maxlength="2000" placeholder="Rejection reason" class="form-control form-control-sm mt-1">
                                <button type="submit" class="btn btn-sm btn-outline-danger mt-1 w-100" onclick="return confirm('Reject this payment submission? The order will remain unpaid and cannot accept another payment submission.')">Reject</button>
                            </form>
                        </div>
                    @else
                        <span>Final decision recorded. This submission cannot be approved or rejected again.</span>
                    @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">No manual bank transfer receipts have been submitted.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="padding-top:12px">{{ $payments->links() }}</div>
    </div>
</div>
@endsection
