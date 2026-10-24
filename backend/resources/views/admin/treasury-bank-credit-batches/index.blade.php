@extends('layouts.dashboard')

@section('title', 'Treasury Bank Credit Reconciliation — Super Admin')
@section('role_title', 'Super Admin Command Tower')
@section('page_title', 'Treasury Bank Credit Reconciliation')
@section('role_badge', 'SUPER ADMIN')
@section('role_badge_class', 'role-badge-super-admin')

@section('sidebar_menu')
    @include('admin.partials._sidebar')
@endsection

@section('content')
<div class="page-header-row">
    <div>
        <h2>Corporate Bank Credit Evidence</h2>
        <p>Record a gateway settlement and its matching corporate bank-statement credit. A second, different Super Admin must verify it before the net credited amount can reserve supplier payouts. Never use screenshots alone as proof without matching bank-statement references.</p>
    </div>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="card mb-4">
    <div class="card-header"><strong>Prepare a settlement / bank-credit match</strong></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.treasury-bank-credit-batches.prepare') }}" class="row g-3">
            @csrf
            <div class="col-md-4"><label class="form-label">Gateway provider</label><select name="gateway_provider" required class="form-control"><option value="paystack">Paystack</option></select></div>
            <div class="col-md-4"><label class="form-label">Gateway settlement reference</label><input name="gateway_settlement_reference" required minlength="4" maxlength="120" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Corporate bank credit reference</label><input name="bank_credit_reference" required minlength="4" maxlength="120" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Corporate account last 4 digits</label><input name="corporate_account_last4" required pattern="[0-9]{4}" maxlength="4" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Net credited amount (kobo)</label><input type="number" name="net_credit_amount_kobo" required min="1" step="1" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Currency</label><select name="currency" required class="form-control"><option value="NGN">NGN</option></select></div>
            <div class="col-md-3"><label class="form-label">Bank statement value date</label><input type="date" name="statement_value_date" required max="{{ now()->toDateString() }}" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Statement evidence reference</label><input name="statement_evidence_reference" required minlength="4" maxlength="255" class="form-control" placeholder="Statement file/page or bank portal reference"></div>
            <div class="col-md-6"><label class="form-label">Reconciliation notes</label><textarea name="reconciliation_notes" required minlength="20" maxlength="2000" rows="2" class="form-control"></textarea></div>
            <div class="col-12"><button type="submit" class="btn btn-primary" onclick="return confirm('Prepare this batch for a second reviewer? It will not be available to payouts yet.')">Prepare for second review</button></div>
        </form>
    </div>
</div>

<div class="table-responsive">
<table class="table table-striped align-middle">
<thead><tr><th>Batch</th><th>Settlement / bank credit</th><th>Net credited</th><th>Evidence</th><th>Status / reviewers</th><th>Action</th></tr></thead>
<tbody>
@forelse($batches as $batch)
<tr>
    <td>#{{ $batch->id }}<br><small>Value date: {{ optional($batch->statement_value_date)->toDateString() }}</small></td>
    <td><div>Gateway: <code>{{ $batch->gateway_settlement_reference }}</code></div><div>Bank: <code>{{ $batch->bank_credit_reference }}</code></div><div>Account ending {{ $batch->corporate_account_last4 }}</div></td>
    <td>₦{{ number_format($batch->net_credit_amount_kobo / 100, 2) }}<br><small>{{ number_format($batch->net_credit_amount_kobo) }} kobo</small></td>
    <td><div>{{ $batch->statement_evidence_reference }}</div><small>{{ $batch->reconciliation_notes }}</small></td>
    <td><strong>{{ $batch->status }}</strong><div>Prepared by #{{ $batch->prepared_by_admin_id }}</div>@if($batch->confirmed_by_admin_id)<div>Confirmed by #{{ $batch->confirmed_by_admin_id }}</div>@endif<div>Linked payout requests: {{ $batch->supplier_payouts_count }}</div></td>
    <td>
        @if($batch->status === 'pending_second_review')
            <form method="POST" action="{{ route('admin.treasury-bank-credit-batches.confirm', $batch->id) }}" style="display:grid;gap:8px;min-width:240px;">
                @csrf
                <textarea name="confirmation_notes" required minlength="20" maxlength="2000" rows="3" class="form-control" placeholder="Record second-review evidence from gateway settlement and bank statement"></textarea>
                <button type="submit" class="btn btn-success" onclick="return confirm('Confirm that the net credit is visible in the correct corporate bank account? A different Super Admin must review this.')">Confirm bank credit</button>
            </form>
        @else
            <span>Batch locked for audit. Confirmed net credit is subject to payout reservations.</span>
        @endif
    </td>
</tr>
@empty
<tr><td colspan="6">No treasury bank-credit batches recorded.</td></tr>
@endforelse
</tbody>
</table>
</div>
{{ $batches->links() }}
@endsection
