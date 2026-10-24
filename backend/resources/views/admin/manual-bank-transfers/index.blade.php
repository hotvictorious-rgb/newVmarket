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
<div class="page-header-row">
    <div>
        <h1 class="page-header-title">Manual Bank Transfer Review</h1>
        <p class="page-header-subtitle">Inspect the customer's private receipt, independently verify actual funds in the Victorious MARKET receiving account, then approve or reject. A screenshot alone is never proof of payment.</p>
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

<div class="dash-card" style="margin-bottom:24px">
    <div class="dash-card-header">
        <div class="dash-card-title">Payment Method &amp; Receiving Account</div>
        <strong>Currently active: {{ strtoupper(str_replace('_', ' ', $activePaymentMethod ?? 'paystack')) }}</strong>
    </div>
    <form method="POST" action="{{ route('admin.manual-bank-transfers.settings') }}" style="padding:18px">
        @csrf
        <p style="margin-top:0">Only one global payment method can be active. Selecting one replaces the active method; selecting Disabled closes checkout. Existing orders remain bound to their recorded method.</p>
        <label for="active_payment_method" style="display:block;font-weight:700;margin-bottom:6px">Active checkout payment method</label>
        <select id="active_payment_method" name="active_payment_method" required style="width:100%;max-width:460px;padding:10px;margin-bottom:14px">
            <option value="paystack" @selected(($activePaymentMethod ?? 'paystack') === 'paystack')>Paystack</option>
            <option value="manual_bank_transfer" @selected(($activePaymentMethod ?? 'paystack') === 'manual_bank_transfer')>Manual Bank Transfer</option>
            <option value="disabled" @selected(($activePaymentMethod ?? 'paystack') === 'disabled')>Disabled — no new checkout payments</option>
        </select>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
            <div>
                <label for="bank_name" style="display:block;font-weight:700;margin-bottom:6px">Receiving bank</label>
                <input id="bank_name" name="bank_name" maxlength="100" value="{{ old('bank_name', $bankAccount['bank_name'] ?? '') }}" style="width:100%;padding:9px" />
            </div>
            <div>
                <label for="account_name" style="display:block;font-weight:700;margin-bottom:6px">Account name</label>
                <input id="account_name" name="account_name" maxlength="150" value="{{ old('account_name', $bankAccount['account_name'] ?? '') }}" style="width:100%;padding:9px" />
            </div>
            <div>
                <label for="account_number" style="display:block;font-weight:700;margin-bottom:6px">10-digit account number</label>
                <input id="account_number" name="account_number" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" value="{{ old('account_number', $bankAccount['account_number'] ?? '') }}" style="width:100%;padding:9px" />
            </div>
            <div>
                <label for="customer_instructions" style="display:block;font-weight:700;margin-bottom:6px">Customer transfer instructions</label>
                <textarea id="customer_instructions" name="customer_instructions" maxlength="2000" rows="3" style="width:100%;padding:9px">{{ old('customer_instructions', $bankAccount['customer_instructions'] ?? '') }}</textarea>
            </div>
        </div>
        <p style="font-size:12px;color:#64748b">Bank details are public checkout instructions, not credentials. Never enter PINs, passwords, OTPs or API secrets here. Manual transfer requires an uploaded receipt plus independent actual-credit verification.</p>
        <button type="submit" class="btn-dash btn-dash-primary" onclick="return confirm('Save this payment configuration? New checkouts will use only the selected method.')">Save payment configuration</button>
    </form>
</div>

<div class="dash-card" style="margin-bottom:24px;border-left:4px solid #d97706">
    <div class="dash-card-header">
        <div class="dash-card-title">Manual Transfer Refund Holds</div>
        <strong>{{ $refundCases->total() }} pending / recorded</strong>
    </div>
    <div style="padding:0 18px 18px">
        <p style="margin-top:0;color:#92400e">These cases arise when a verified transfer belongs to an expired/cancelled or otherwise unfulfillable order. They are not Paystack refunds. No automatic refund or bank outflow is permitted; keep the case on hold until the manual-refund procedure is separately approved and a Super Admin authorizes the next step.</p>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead><tr><th>Created</th><th>Order</th><th>Amount</th><th>Status</th><th>Reason</th></tr></thead>
                <tbody>
                @forelse($refundCases as $refundCase)
                    <tr>
                        <td>{{ $refundCase->created_at?->format('d M Y H:i:s') ?? '—' }}</td>
                        <td><strong>{{ $refundCase->order?->order_number ?? ('Order #' . $refundCase->order_id) }}</strong></td>
                        <td>₦{{ number_format($refundCase->amount_kobo / 100, 2) }} {{ $refundCase->currency }}</td>
                        <td>{{ strtoupper(str_replace('_', ' ', $refundCase->status)) }}</td>
                        <td>{{ $refundCase->reason }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center">No manual-transfer refund holds recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $refundCases->links() }}
        </div>
    </div>
</div>

<div class="dash-card">
    <div class="dash-card-header">
        <div class="dash-card-title">Receipt submissions</div>
        <span class="dash-badge dash-badge-gold">{{ $payments->where('status', 'pending_review')->count() }} pending on this page</span>
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
                        <a href="{{ route('admin.manual-bank-transfers.evidence', $payment->id) }}" target="_blank" rel="noopener noreferrer">Open private receipt</a>
                        <div><small>{{ strtoupper(str_replace('image/', '', $payment->evidence_mime)) }} · {{ number_format($payment->evidence_size_bytes / 1024, 1) }} KB</small></div>
                        <div><small>SHA-256: {{ $payment->evidence_sha256 }}</small></div>
                    </td>
                    <td>
                        <strong>{{ strtoupper(str_replace('_', ' ', $payment->status)) }}</strong>
                        @if($payment->reviewed_at)
                            <div><small>Reviewed {{ $payment->reviewed_at->format('d M Y H:i') }}</small></div>
                            <div><small>By {{ $payment->reviewer?->name ?? 'Former admin' }}</small></div>
                        @endif
                        @if($payment->bank_transaction_reference)
                            <div><small>Bank ref: {{ $payment->bank_transaction_reference }}</small></div>
                        @endif
                        @if($payment->decision_reason)
                            <div><small>{{ $payment->decision_reason }}</small></div>
                        @endif
                    </td>
                    <td style="min-width:300px">
                    @if($payment->status === 'pending_review')
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
                        <form method="POST" action="{{ route('admin.manual-bank-transfers.reject', $payment->id) }}" style="padding:12px;border:1px solid #fecaca;border-radius:8px">
                            @csrf
                            <strong>Reject payment evidence</strong>
                            <label for="reject-{{ $payment->id }}" style="display:block;margin-top:8px">Reason (required)</label>
                            <textarea id="reject-{{ $payment->id }}" name="decision_reason" required minlength="5" maxlength="2000" rows="2" style="width:100%;padding:8px"></textarea>
                            <button type="submit" class="btn-dash btn-dash-outline" style="color:#b91c1c;border-color:#b91c1c;margin-top:8px" onclick="return confirm('Reject this payment submission? The order will remain unpaid and cannot accept another payment submission.')">Reject</button>
                        </form>
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
