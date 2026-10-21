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
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">Retailer Supplier Payables</h1>
            <p class="page-header-subtitle">Item-level supplier payouts. Uncertain gateway outcomes stay held until verified with Paystack.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Supplier</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Gateway</th>
                    <th>Bank snapshot</th>
                    <th>Items</th>
                    <th>Reference</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payouts as $payout)
                    <tr>
                        <td>#{{ $payout->id }}</td>
                        <td>{{ $payout->supplier->business_name ?? ('Supplier #' . $payout->supplier_vendor_id) }}</td>
                        <td>₦{{ number_format((float) $payout->amount, 2) }}</td>
                        <td>{{ strtoupper($payout->status) }}</td>
                        <td>{{ $payout->gateway_status ?? '—' }}</td>
                        <td>
                            {{ $payout->account_name }}<br>
                            {{ $payout->bank_name }} / {{ $payout->account_number }}
                        </td>
                        <td>{{ $payout->allocations->count() }}</td>
                        <td><small>{{ $payout->transfer_reference }}</small></td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                @if ($payout->status === 'pending')
                                    <form method="POST" action="{{ route('admin.retailer-supplier-payouts.disburse', $payout->id) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-primary" type="submit">Submit transfer</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.retailer-supplier-payouts.reject', $payout->id) }}" class="d-flex gap-1">
                                        @csrf
                                        <input name="rejection_reason" required minlength="5" maxlength="1000" placeholder="Reason" aria-label="Rejection reason">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Reject</button>
                                    </form>
                                @elseif (in_array($payout->status, ['processing', 'uncertain'], true))
                                    <form method="POST" action="{{ route('admin.retailer-supplier-payouts.reconcile', $payout->id) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-warning" type="submit">Verify with Paystack</button>
                                    </form>
                                @else
                                    <span>—</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center">No retailer supplier payout requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $payouts->links() }}
@endsection
