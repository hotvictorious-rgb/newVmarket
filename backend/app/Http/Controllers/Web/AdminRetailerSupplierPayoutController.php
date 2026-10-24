<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\LogisticsPayoutRequest;
use App\Models\ManualBankTransferPayment;
use App\Models\ManualBankTransferRefundCase;
use App\Models\RetailerSupplierPayoutRequest;
use App\Services\RetailerSupplierPayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminRetailerSupplierPayoutController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeSuperAdmin($request);
        $payouts = RetailerSupplierPayoutRequest::with(['supplier', 'allocations', 'treasuryBankCreditBatch'])
            ->latest('id')
            ->paginate(25);

        return view('admin.retailer-supplier-payouts.index', compact('payouts'));
    }

    public function disburse(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);

        if (now()->isSunday()) {
            return back()->with('error', 'Disbursements occur Monday through Saturday only. Outbound transfers are blocked on Sundays.');
        }

        $validated = $request->validate([
            'bank_transaction_reference' => ['required', 'string', 'min:3', 'max:150', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'settlement_proof' => ['required', 'file', 'mimes:jpeg,jpg,png,pdf,webp', 'max:10240'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payout = RetailerSupplierPayoutRequest::whereKey($id)->firstOrFail();
        if ($payout->status !== 'pending') {
            return back()->with('error', 'Only a pending supplier payout can be disbursed.');
        }

        $bankRef = trim($validated['bank_transaction_reference']);

        // Prevent duplicate bank transaction references across all system rails
        if (RetailerSupplierPayoutRequest::where('bank_transaction_reference', $bankRef)->where('id', '!=', $payout->id)->exists()
            || LogisticsPayoutRequest::where('bank_transaction_reference', $bankRef)->exists()
            || ManualBankTransferPayment::where('bank_transaction_reference', $bankRef)->exists()
            || ManualBankTransferRefundCase::where('manual_dispatch_reference', $bankRef)->exists()) {
            throw ValidationException::withMessages([
                'bank_transaction_reference' => 'This bank transaction reference has already been recorded in the system and cannot be reused.',
            ]);
        }

        $treasuryReservation = app(RetailerSupplierPayoutService::class)->reserveTreasuryBankCreditBatch($payout->id);
        if (!($treasuryReservation['success'] ?? false)) {
            $this->audit($request, 'retailer_supplier_payout_blocked_missing_treasury_bank_credit', $payout, [
                'status' => $payout->status,
                'amount' => (string) $payout->amount,
            ], [
                'reason' => $treasuryReservation['message'] ?? 'No reconciled bank-credit batch available.',
                'action' => 'transfer_blocked_before_disbursement',
            ]);
            return back()->with('error', $treasuryReservation['message']
                ?? 'Supplier payout blocked until treasury bank-credit evidence is reconciled.');
        }

        $file = $request->file('settlement_proof');
        $ext = strtolower((string) $file->extension());
        $proofPath = $file->storeAs(
            'settlement-proofs/supplier',
            $payout->id . '_' . Str::uuid() . '.' . $ext,
            'local'
        );

        if (!$proofPath) {
            return back()->with('error', 'Failed to securely store outgoing settlement proof.');
        }

        $transferRef = $payout->transfer_reference ?: ('SUPPAYOUT-' . Str::upper(Str::random(24)));

        $processing = app(RetailerSupplierPayoutService::class)->markProcessing(
            $payout->id,
            (int) $request->user()->id,
            'MANUAL_BANK_TRANSFER',
            $transferRef
        );

        if (!$processing['success']) {
            return back()->with('error', $processing['message'] ?? 'Supplier payout could not enter processing.');
        }

        $adminNoteSuffix = !empty($validated['admin_notes']) ? ' | ' . $validated['admin_notes'] : '';
        $notes = trim(($payout->admin_notes ? $payout->admin_notes . "\n" : '') . 'Disbursed via manual bank transfer. Ref: ' . $bankRef . $adminNoteSuffix);

        RetailerSupplierPayoutRequest::whereKey($payout->id)->update([
            'bank_transaction_reference' => $bankRef,
            'settlement_proof_path' => $proofPath,
            'gateway_status' => 'manual_transfer_dispatched',
            'disbursed_at' => now(),
            'admin_notes' => $notes,
        ]);

        $this->audit($request, 'retailer_supplier_payout_manual_disbursed', $payout->fresh(), [
            'status' => 'pending',
            'amount' => (string) $payout->amount,
        ], [
            'status' => 'processing',
            'gateway_status' => 'manual_transfer_dispatched',
            'bank_transaction_reference' => $bankRef,
            'settlement_proof_path' => $proofPath,
        ]);

        return redirect()->route('admin.retailer-supplier-payouts.index')
            ->with('status', 'Manual bank transfer recorded successfully. Verify corporate bank debit before reconciling to paid.');
    }

    public function reconcile(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);
        $payout = RetailerSupplierPayoutRequest::whereKey($id)->firstOrFail();

        $this->audit($request, 'retailer_supplier_payout_reconciliation_attempted', $payout, [
            'status' => $payout->status,
            'gateway_status' => $payout->gateway_status,
            'bank_transaction_reference' => $payout->bank_transaction_reference,
            'transfer_reference' => $payout->transfer_reference,
        ], ['action' => 'reconciliation_attempted']);

        if (!in_array($payout->status, ['processing', 'uncertain'], true)) {
            return back()->with('error', 'Only a processing or uncertain supplier payout can be reconciled.');
        }

        if (empty($payout->bank_transaction_reference) || in_array($payout->gateway_status, ['not_submitted', 'submission_outcome_unknown'], true)) {
            app(RetailerSupplierPayoutService::class)->markUncertain(
                $payout->id,
                'Supplier payout submission outcome is unknown; payout remains held.',
                'submission_outcome_unknown'
            );
            return back()->with('error', 'Cannot reconcile payout: manual bank transaction reference is missing or submission outcome is unknown.');
        }

        $finalized = app(RetailerSupplierPayoutService::class)->finalizeSuccess(
            $payout->id,
            (string) $payout->transfer_reference,
            null,
            (string) $payout->amount
        );

        if (!$finalized['success']) {
            return back()->with('error', $finalized['message'] ?? 'Supplier payout reconciliation could not be finalized.');
        }

        RetailerSupplierPayoutRequest::whereKey($payout->id)->update([
            'gateway_status' => 'reconciled_cleared',
        ]);

        $this->audit($request, 'retailer_supplier_payout_manual_reconciled', $payout->fresh(), [
            'status' => $payout->status,
        ], [
            'status' => 'paid',
            'gateway_status' => 'reconciled_cleared',
            'reconciled_by_admin_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.retailer-supplier-payouts.index')
            ->with('status', 'Supplier settlement confirmed, marked paid, and double-entry balanced in the financial journal.');
    }

    public function reject(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);
        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $payout = RetailerSupplierPayoutRequest::whereKey($id)->firstOrFail();

        $this->audit($request, 'retailer_supplier_payout_rejection_attempted', $payout, [
            'status' => $payout->status,
            'gateway_status' => $payout->gateway_status,
            'transfer_reference' => $payout->transfer_reference,
        ], ['action' => 'rejection_attempted', 'reason' => $validated['rejection_reason']]);

        if ($payout->status !== 'pending' || $payout->processing_token || !in_array($payout->gateway_status, ['not_submitted', null], true)) {
            return back()->with('error', 'This supplier payout has already been dispatched. Reconcile or mark it failed instead of rejecting.');
        }

        $result = app(RetailerSupplierPayoutService::class)->reverse($payout->id, 'not_submitted', $validated['rejection_reason']);
        return back()->with($result['success'] ? 'status' : 'error', $result['message'] ?? 'Supplier payout rejection was processed.');
    }

    public function markFailed(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);
        $validated = $request->validate(['failure_reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $payout = RetailerSupplierPayoutRequest::whereKey($id)->firstOrFail();

        if (!in_array($payout->status, ['processing', 'uncertain'], true)) {
            return back()->with('error', 'Only a processing or uncertain payout can be marked failed.');
        }

        $result = app(RetailerSupplierPayoutService::class)->reverse($payout->id, 'failed', $validated['failure_reason']);
        return back()->with($result['success'] ? 'status' : 'error', $result['message'] ?? 'Supplier payout marked failed and allocations released.');
    }

    public function proof(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);
        $payout = RetailerSupplierPayoutRequest::whereKey($id)->firstOrFail();

        if (empty($payout->settlement_proof_path)) {
            abort(404, 'No settlement proof attached.');
        }

        $disk = Storage::disk('local');
        abort_unless($disk->exists($payout->settlement_proof_path), 404);

        $mime = $disk->mimeType($payout->settlement_proof_path) ?: 'application/octet-stream';
        $path = $disk->path($payout->settlement_proof_path);

        return response()->file($path, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Frame-Options' => 'DENY',
        ]);
    }

    private function audit(Request $request, string $action, RetailerSupplierPayoutRequest $payout, array $oldValues, array $newValues): void
    {
        AdminAuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'target_type' => RetailerSupplierPayoutRequest::class,
            'target_id' => $payout->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => substr((string) ($request->userAgent() ?? 'Admin'), 0, 1000),
        ]);
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->isSuperAdmin() || $user->status !== 'active') {
            abort(403, 'An active Super Admin account is required for supplier payout operations.');
        }
    }
}
