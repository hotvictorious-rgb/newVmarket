@extends('layouts.dashboard')

@section('title', 'Retailer Supplier Payables — Treasury')
@section('role_title', 'Super Admin Command Tower')
@section('page_title', 'Retailer Supplier Payables')
@section('role_badge', 'TREASURY')
@section('role_badge_class', 'role-badge-super-admin')

@section('sidebar_menu')
    @include('admin.partials._sidebar')
@endsection

@section('content')
    <div class="page-header-row mb-4">
        <div>
            <h1 class="page-header-title">Retailer Supplier Payables</h1>
            <p class="page-header-subtitle">Item-level supplier settlements. Outbound settlements are manual bank transfers executed through the corporate bank, evidenced with proof of transfer, backed by reconciled treasury funds, and reconciled to paid without automated gateway outflow.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <div class="table-responsive bg-white rounded shadow-sm p-3">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Supplier</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Bank Destination</th>
                    <th>Allocations</th>
                    <th>Treasury Funding</th>
                    <th>Transfer Evidence</th>
                    <th style="min-width: 280px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payouts as $payout)
                    <tr>
                        <td><strong>#{{ $payout->id }}</strong></td>
                        <td>
                            <strong>{{ $payout->supplier->business_name ?? ('Supplier #' . $payout->supplier_vendor_id) }}</strong>
                            <div class="text-muted small">Vendor ID: {{ $payout->supplier_vendor_id }}</div>
                        </td>
                        <td><strong class="text-success">₦{{ number_format((float) $payout->amount, 2) }}</strong></td>
                        <td>
                            @if ($payout->status === 'paid')
                                <span class="badge bg-success">PAID</span>
                            @elseif ($payout->status === 'processing')
                                <span class="badge bg-warning text-dark">DISPATCHED / PENDING RECONCILIATION</span>
                            @elseif ($payout->status === 'uncertain')
                                <span class="badge bg-danger">HELD (UNCERTAIN)</span>
                            @elseif ($payout->status === 'rejected')
                                <span class="badge bg-secondary">REJECTED</span>
                            @else
                                <span class="badge bg-info text-dark">PENDING DISBURSEMENT</span>
                            @endif
                            <div class="small text-muted">{{ $payout->gateway_status ?? 'not_submitted' }}</div>
                        </td>
                        <td>
                            <div><strong>{{ $payout->account_name }}</strong></div>
                            <div class="small">{{ $payout->bank_name }} ({{ $payout->bank_code ?: 'N/A' }})</div>
                            <div class="small text-muted font-monospace">{{ $payout->account_number }}</div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ $payout->allocations->count() }} items</span>
                        </td>
                        <td>
                            @if ($payout->treasuryBankCreditBatch)
                                <span class="badge bg-success-subtle text-success border">Batch #{{ $payout->treasury_bank_credit_batch_id }}</span>
                                <div class="small text-muted">{{ $payout->treasuryBankCreditBatch->bank_credit_reference }}</div>
                            @else
                                <span class="badge bg-secondary-subtle text-muted">Unreserved</span>
                            @endif
                        </td>
                        <td>
                            @if ($payout->bank_transaction_reference)
                                <div class="small"><strong>Ref:</strong> <code class="text-dark">{{ $payout->bank_transaction_reference }}</code></div>
                            @endif
                            @if ($payout->settlement_proof_path)
                                <div class="mt-1">
                                    <a href="{{ route('admin.retailer-supplier-payouts.proof', $payout->id) }}" target="_blank" class="btn btn-xs btn-outline-info">
                                        View Proof File
                                    </a>
                                </div>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($payout->status === 'pending')
                                <div class="border rounded p-2 bg-light mb-2">
                                    <div class="small fw-bold text-primary mb-1">Execute Manual Settlement:</div>
                                    <form method="POST" action="{{ route('admin.retailer-supplier-payouts.disburse', $payout->id) }}" enctype="multipart/form-data" class="d-flex flex-column gap-2">
                                        @csrf
                                        <input type="text" name="bank_transaction_reference" required placeholder="Bank Transaction Reference" class="form-control form-control-sm font-monospace" pattern="[A-Za-z0-9._\/-]+" autocomplete="off">
                                        <input type="file" name="settlement_proof" required accept="image/*,.pdf" class="form-control form-control-sm">
                                        <input type="text" name="admin_notes" placeholder="Notes (optional)" class="form-control form-control-sm">
                                        <button class="btn btn-sm btn-primary w-100" type="submit">Record Wire Transfer</button>
                                    </form>
                                </div>
                                <form method="POST" action="{{ route('admin.retailer-supplier-payouts.reject', $payout->id) }}" class="d-flex gap-1">
                                    @csrf
                                    <input name="rejection_reason" required minlength="5" maxlength="1000" placeholder="Rejection reason" class="form-control form-control-sm">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Reject</button>
                                </form>
                            @elseif (in_array($payout->status, ['processing', 'uncertain'], true))
                                <div class="d-flex flex-column gap-2">
                                    <form method="POST" action="{{ route('admin.retailer-supplier-payouts.reconcile', $payout->id) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-success w-100" type="submit">Confirm Debit & Reconcile to Paid</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.retailer-supplier-payouts.mark-failed', $payout->id) }}" class="d-flex gap-1">
                                        @csrf
                                        <input name="failure_reason" required minlength="5" maxlength="1000" placeholder="Bank failure/reversal reason" class="form-control form-control-sm">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Mark Failed</button>
                                    </form>
                                </div>
                            @else
                                <span class="text-muted small">Terminal / Finalized</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center py-4 text-muted">No retailer supplier payout requests found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $payouts->links() }}
    </div>
@endsection
