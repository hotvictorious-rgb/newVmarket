@extends('layouts.dashboard')

@section('title', 'Payment Capture Exceptions — Super Admin')
@section('role_title', 'Super Admin Command Tower')
@section('page_title', 'Payment Capture Exceptions')
@section('role_badge', 'SUPER ADMIN')
@section('role_badge_class', 'role-badge-super-admin')

@section('sidebar_menu')
    @include('admin.partials._sidebar')
@endsection

@section('content')
<div class="page-header-row">
    <div>
        <h2>Duplicate / Unproven Payment Captures</h2>
        <p>Review each gateway capture independently. This desk never sends a refund; refunds are completed manually in the gateway and then evidenced here by a different Super Admin.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="table-responsive">
<table class="table table-striped align-middle">
<thead>
<tr>
    <th>ID / Order</th><th>Capture identity</th><th>Amount</th><th>Exception</th><th>Status / Existing resolution</th><th>Audited action</th>
</tr>
</thead>
<tbody>
@forelse($exceptions as $exception)
<tr>
    <td>
        <strong>#{{ $exception->id }}</strong><br>
        Order: {{ $exception->order?->order_number ?? ('#' . ($exception->order_id ?? 'unknown')) }}<br>
        <small>{{ optional($exception->detected_at)->toDateTimeString() }}</small>
    </td>
    <td>
        <div>Provider: {{ $exception->gateway_provider }}</div>
        <div>Transaction: <code>{{ $exception->gateway_transaction_id ?: 'UNPROVEN / MISSING' }}</code></div>
        <div>Payment ref: <code>{{ $exception->payment_reference ?: 'missing' }}</code></div>
        <div>Currency: {{ $exception->currency ?: 'unknown' }}</div>
    </td>
    <td>
        @if($exception->amount_kobo !== null)
            ₦{{ number_format($exception->amount_kobo / 100, 2) }}
            <small>({{ number_format($exception->amount_kobo) }} kobo)</small>
        @else
            Unknown
        @endif
    </td>
    <td>
        <strong>{{ $exception->exception_type }}</strong>
        <details><summary>Evidence</summary><pre style="white-space:pre-wrap;max-width:300px">{{ json_encode($exception->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
    </td>
    <td>
        <strong>{{ $exception->status }}</strong>
        @if($exception->resolution)
            <div>Resolution #{{ $exception->resolution->id }} — {{ $exception->resolution->status }}</div>
            <div>Prepared by admin #{{ $exception->resolution->prepared_by_admin_id }}</div>
            @if($exception->resolution->confirmed_by_admin_id)
                <div>Confirmed by admin #{{ $exception->resolution->confirmed_by_admin_id }}</div>
            @endif
            <div>{{ $exception->resolution->reason }}</div>
        @endif
    </td>
    <td style="min-width:300px">
        @if($exception->status === 'pending_admin_review' && !$exception->resolution)
            <form method="POST" action="{{ route('admin.payment-capture-exceptions.prepare', $exception->id) }}" style="display:grid;gap:8px;">
                @csrf
                <label>Decision
                    <select name="resolution_type" required class="form-control">
                        <option value="">Select decision</option>
                        <option value="manual_refund_required">Distinct capture proven — prepare manual refund instruction</option>
                        <option value="reviewed_no_refund">Reviewed — no refund warranted / identity unproven</option>
                    </select>
                </label>
                <label>Evidence-based reason
                    <textarea name="reason" required minlength="10" maxlength="1500" class="form-control" rows="3"></textarea>
                </label>
                <button type="submit" class="btn btn-primary" onclick="return confirm('Record this decision? This will not send any refund.')">Record audited decision</button>
            </form>
        @elseif($exception->resolution && $exception->resolution->status === 'pending_manual_refund')
            <div class="alert alert-warning">Manual action required: open the gateway dashboard and refund this exact transaction ID only. Verify amount and currency before confirming below. This application will not initiate the refund.</div>
            <form method="POST" action="{{ route('admin.payment-capture-exceptions.confirm-manual-refund', $exception->resolution->id) }}" style="display:grid;gap:8px;">
                @csrf
                <label>Gateway refund reference
                    <input name="refund_reference" required minlength="4" maxlength="120" class="form-control">
                </label>
                <label>Refunded amount (kobo; must exactly match the extra capture)
                    <input name="refunded_amount_kobo" type="number" min="1" step="1" required class="form-control">
                </label>
                <label>Confirmation evidence / reconciliation notes
                    <textarea name="confirmation_notes" required minlength="10" maxlength="1500" class="form-control" rows="3"></textarea>
                </label>
                <button type="submit" class="btn btn-success" onclick="return confirm('Confirm a refund already completed manually in the gateway? A different Super Admin must perform this confirmation.')">Confirm completed manual refund</button>
            </form>
        @else
            <span>Review action recorded. No automatic financial action is available.</span>
        @endif
    </td>
</tr>
@empty
<tr><td colspan="6">No payment capture exceptions recorded.</td></tr>
@endforelse
</tbody>
</table>
</div>
{{ $exceptions->links() }}
@endsection
