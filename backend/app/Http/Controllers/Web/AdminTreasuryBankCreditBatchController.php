<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\TreasuryBankCreditBatchAllocation;
use App\Models\TreasuryBankCreditBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminTreasuryBankCreditBatchController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeSuperAdmin($request);
        $batches = TreasuryBankCreditBatch::with(['preparedByAdmin', 'confirmedByAdmin', 'allocations.order'])
            ->withCount('supplierPayouts')
            ->latest('id')
            ->paginate(25);

        return view('admin.treasury-bank-credit-batches.index', compact('batches'));
    }

    public function prepare(Request $request)
    {
        $this->authorizeSuperAdmin($request);
        $data = $request->validate([
            'gateway_provider' => ['required', 'in:paystack'],
            'gateway_settlement_reference' => ['required', 'string', 'min:4', 'max:120'],
            'bank_credit_reference' => ['required', 'string', 'min:4', 'max:120'],
            'corporate_account_last4' => ['required', 'regex:/^\d{4}$/'],
            'gross_collection_amount_kobo' => ['required', 'integer', 'min:1'],
            'provider_fee_amount_kobo' => ['required', 'integer', 'min:0'],
            'net_credit_amount_kobo' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'in:NGN'],
            'statement_value_date' => ['required', 'date', 'before_or_equal:today'],
            'statement_evidence_reference' => ['required', 'string', 'min:4', 'max:255'],
            'reconciliation_notes' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        if ((int) $data['gross_collection_amount_kobo'] - (int) $data['provider_fee_amount_kobo'] !== (int) $data['net_credit_amount_kobo']) {
            throw ValidationException::withMessages([
                'net_credit_amount_kobo' => 'Net bank credit must equal gross collection amount minus provider fees.',
            ]);
        }

        $batch = DB::transaction(function () use ($request, $data) {
            $existing = TreasuryBankCreditBatch::where('gateway_settlement_reference', trim($data['gateway_settlement_reference']))
                ->orWhere('bank_credit_reference', trim($data['bank_credit_reference']))
                ->lockForUpdate()
                ->first();
            if ($existing) {
                throw ValidationException::withMessages([
                    'gateway_settlement_reference' => 'A bank credit or gateway settlement reference already exists. Reconcile the existing batch instead of duplicating it.',
                ]);
            }

            $batch = TreasuryBankCreditBatch::create([
                'gateway_provider' => $data['gateway_provider'],
                'gateway_settlement_reference' => trim($data['gateway_settlement_reference']),
                'bank_credit_reference' => trim($data['bank_credit_reference']),
                'corporate_account_last4' => $data['corporate_account_last4'],
                'net_credit_amount_kobo' => (int) $data['net_credit_amount_kobo'],
                'gross_collection_amount_kobo' => (int) $data['gross_collection_amount_kobo'],
                'provider_fee_amount_kobo' => (int) $data['provider_fee_amount_kobo'],
                'currency' => $data['currency'],
                'statement_value_date' => $data['statement_value_date'],
                'statement_evidence_reference' => trim($data['statement_evidence_reference']),
                'reconciliation_notes' => trim($data['reconciliation_notes']),
                'status' => 'pending_second_review',
                'prepared_by_admin_id' => $request->user()->id,
                'prepared_at' => now(),
                'idempotency_key' => 'TREASURY-BANK-CREDIT-' . hash('sha256',
                    trim($data['gateway_settlement_reference']) . '|' . trim($data['bank_credit_reference']) . '|' . $data['net_credit_amount_kobo']
                ),
            ]);

            AdminAuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'treasury_bank_credit_batch_prepared',
                'target_type' => TreasuryBankCreditBatch::class,
                'target_id' => $batch->id,
                'old_values' => [],
                'new_values' => [
                    'gateway_settlement_reference' => $batch->gateway_settlement_reference,
                    'bank_credit_reference' => $batch->bank_credit_reference,
                    'net_credit_amount_kobo' => $batch->net_credit_amount_kobo,
                    'gross_collection_amount_kobo' => $batch->gross_collection_amount_kobo,
                    'provider_fee_amount_kobo' => $batch->provider_fee_amount_kobo,
                    'currency' => $batch->currency,
                    'corporate_account_last4' => $batch->corporate_account_last4,
                    'prepared_by_admin_id' => $batch->prepared_by_admin_id,
                    'status' => $batch->status,
                ],
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => 'Treasury bank credit reconciliation desk',
            ]);

            return $batch;
        }, 3);

        return back()->with('success', 'Bank-credit evidence prepared. It cannot fund payouts until a different Super Admin verifies the statement and confirms the batch.');
    }

    public function addAllocation(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'min:1'],
            'gateway_transaction_id' => ['required', 'string', 'min:1', 'max:100'],
            'payment_reference' => ['required', 'string', 'min:1', 'max:100'],
            'amount_kobo' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($request, $id, $data) {
            $batch = TreasuryBankCreditBatch::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($batch->status !== 'pending_second_review') {
                throw ValidationException::withMessages([
                    'order_id' => 'Allocations can only be added before the bank-credit batch is confirmed.',
                ]);
            }

            $order = Order::whereKey($data['order_id'])->lockForUpdate()->first();
            $expectedKobo = $order ? (int) bcmul((string) $order->grand_total, '100', 0) : 0;
            if (!$order || $order->payment_status !== 'paid'
                || trim((string) $order->payment_reference) === ''
                || !hash_equals((string) $order->payment_reference, trim($data['payment_reference']))
                || trim((string) $order->paystack_transaction_id) === ''
                || !hash_equals((string) $order->paystack_transaction_id, trim($data['gateway_transaction_id']))
                || strtoupper((string) $batch->currency) !== 'NGN'
                || (int) $data['amount_kobo'] !== $expectedKobo) {
                throw ValidationException::withMessages([
                    'order_id' => 'Allocation must exactly match a paid order, its stored gateway transaction ID, payment reference, NGN currency and full captured amount.',
                ]);
            }

            $allocation = TreasuryBankCreditBatchAllocation::create([
                'treasury_bank_credit_batch_id' => $batch->id,
                'order_id' => $order->id,
                'gateway_transaction_id' => trim($data['gateway_transaction_id']),
                'payment_reference' => trim($data['payment_reference']),
                'amount_kobo' => $expectedKobo,
                'currency' => 'NGN',
                'idempotency_key' => 'TREASURY-ALLOC-' . hash('sha256', $batch->id . '|' . $order->id . '|' . $order->paystack_transaction_id),
                'added_by_admin_id' => $request->user()->id,
                'added_at' => now(),
            ]);

            AdminAuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'treasury_bank_credit_order_capture_allocated',
                'target_type' => TreasuryBankCreditBatchAllocation::class,
                'target_id' => $allocation->id,
                'old_values' => [],
                'new_values' => [
                    'batch_id' => $batch->id,
                    'order_id' => $order->id,
                    'gateway_transaction_id' => $allocation->gateway_transaction_id,
                    'payment_reference' => $allocation->payment_reference,
                    'amount_kobo' => $allocation->amount_kobo,
                ],
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => 'Treasury bank credit reconciliation desk',
            ]);
        }, 3);

        return back()->with('success', 'Verified order capture allocated to the pending treasury batch. The batch still requires independent bank-credit review.');
    }

    public function confirm(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);
        $data = $request->validate([
            'confirmation_notes' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $id, $data) {
            $batch = TreasuryBankCreditBatch::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($batch->status !== 'pending_second_review') {
                throw ValidationException::withMessages([
                    'confirmation_notes' => 'Only a batch awaiting second review can be confirmed.',
                ]);
            }
            if ((int) $batch->prepared_by_admin_id === (int) $request->user()->id) {
                throw ValidationException::withMessages([
                    'confirmation_notes' => 'Dual control required: the preparer cannot confirm their own corporate bank-credit evidence.',
                ]);
            }

            $allocations = TreasuryBankCreditBatchAllocation::where('treasury_bank_credit_batch_id', $batch->id)
                ->orderBy('id')->lockForUpdate()->get();
            if ($allocations->isEmpty()) {
                throw ValidationException::withMessages([
                    'confirmation_notes' => 'Cannot reconcile a collection batch without individual verified order-capture allocations.',
                ]);
            }

            $allocatedGrossKobo = 0;
            foreach ($allocations as $allocation) {
                $order = Order::whereKey($allocation->order_id)->lockForUpdate()->first();
                if (!$order || $order->payment_status !== 'paid'
                    || trim((string) $order->payment_reference) === ''
                    || !hash_equals((string) $order->payment_reference, (string) $allocation->payment_reference)
                    || trim((string) $order->paystack_transaction_id) === ''
                    || !hash_equals((string) $order->paystack_transaction_id, (string) $allocation->gateway_transaction_id)
                    || strtoupper((string) $allocation->currency) !== 'NGN'
                    || (int) $allocation->amount_kobo !== (int) bcmul((string) $order->grand_total, '100', 0)) {
                    throw ValidationException::withMessages([
                        'confirmation_notes' => 'A collection allocation does not exactly match a paid order, gateway transaction ID, payment reference, NGN currency and captured order amount.',
                    ]);
                }
                $allocatedGrossKobo += (int) $allocation->amount_kobo;
            }

            $expectedNetKobo = (int) $batch->gross_collection_amount_kobo - (int) $batch->provider_fee_amount_kobo;
            if ($allocatedGrossKobo !== (int) $batch->gross_collection_amount_kobo
                || $expectedNetKobo !== (int) $batch->net_credit_amount_kobo) {
                throw ValidationException::withMessages([
                    'confirmation_notes' => 'Collection allocations must sum exactly to the declared gross amount, and gross minus provider fees must equal the bank-credit amount.',
                ]);
            }

            $batch->forceFill([
                'status' => 'reconciled',
                'confirmed_by_admin_id' => $request->user()->id,
                'confirmed_at' => now(),
                'reconciliation_notes' => $batch->reconciliation_notes . "\nSecond-review confirmation: " . trim($data['confirmation_notes']),
            ])->save();

            AdminAuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'treasury_bank_credit_batch_reconciled',
                'target_type' => TreasuryBankCreditBatch::class,
                'target_id' => $batch->id,
                'old_values' => ['status' => 'pending_second_review'],
                'new_values' => [
                    'status' => 'reconciled',
                    'gateway_settlement_reference' => $batch->gateway_settlement_reference,
                    'bank_credit_reference' => $batch->bank_credit_reference,
                    'net_credit_amount_kobo' => $batch->net_credit_amount_kobo,
                    'prepared_by_admin_id' => $batch->prepared_by_admin_id,
                    'confirmed_by_admin_id' => $batch->confirmed_by_admin_id,
                    'confirmation_notes' => trim($data['confirmation_notes']),
                ],
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => 'Treasury bank credit reconciliation desk',
            ]);
        }, 3);

        return back()->with('success', 'Corporate bank-credit batch reconciled with dual control. Only its unused net credited balance may now reserve supplier payouts.');
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()
            && $request->user()->user_type === 'super_admin'
            && $request->user()->status === 'active', 403);
    }
}
