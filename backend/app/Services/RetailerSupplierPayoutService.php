<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\FinancialJournalEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RetailerSupplierPayoutAllocation;
use App\Models\RetailerSupplierPayoutRequest;
use App\Models\VendorProfile;
use App\Models\VendorRecoveryLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RetailerSupplierPayoutService
{
    private const MINIMUM_AMOUNT = '5000.00';
    private const MAXIMUM_AMOUNT = '500000.00';

    /**
     * Reserve whole, reconciled supplier item payables in FIFO order. This method
     * never uses legacy order-level vendor earnings for retailer-model orders.
     */
    public function requestPayout(VendorProfile $supplier): array
    {
        return DB::transaction(function () use ($supplier) {
            $lockedSupplier = VendorProfile::whereKey($supplier->id)->lockForUpdate()->first();
            if (!$lockedSupplier || $lockedSupplier->marketplace_status !== 'active'
                || !$lockedSupplier->user()->where('status', 'active')->exists()) {
                return ['success' => false, 'message' => 'Only an active supplier with an active owner account can request a supplier-payable payout.'];
            }
            if (!$lockedSupplier->isKycVerified()) {
                return ['success' => false, 'protected' => true, 'message' => 'Supplier payout is held until the supplier KYC status is verified.'];
            }
            if ($this->hasOpenSupplierRecovery((int) $lockedSupplier->id)) {
                return ['success' => false, 'protected' => true, 'message' => 'Supplier payout is held because an unresolved supplier recovery debt requires finance review.'];
            }

            if (!$this->hasExactlyOneActiveUyoShop($lockedSupplier)) {
                return ['success' => false, 'protected' => true, 'message' => 'Supplier payout requires exactly one active shop located in Uyo, Akwa Ibom State.'];
            }

            foreach (['bank_name', 'bank_code', 'account_number', 'account_name'] as $field) {
                if (trim((string) $lockedSupplier->{$field}) === '') {
                    return ['success' => false, 'message' => 'Complete and verify the supplier bank details before requesting a payout.'];
                }
            }
            if (!$lockedSupplier->bank_updated_at || $lockedSupplier->bank_updated_at->gt(now()->subHours(48))) {
                return [
                    'success' => false,
                    'protected' => true,
                    'message' => 'Supplier bank details are missing a verified update timestamp or remain within the 48-hour security cooldown. No payout reservation was created.',
                ];
            }

            $activeRequest = RetailerSupplierPayoutRequest::where('supplier_vendor_id', $lockedSupplier->id)
                ->whereIn('status', ['pending', 'processing', 'uncertain'])
                ->lockForUpdate()
                ->exists();
            if ($activeRequest) {
                return ['success' => false, 'message' => 'A supplier payout is already pending or requires reconciliation.'];
            }

            $candidateItems = OrderItem::with('order')
                ->where('fulfillment_supplier_vendor_id', $lockedSupplier->id)
                ->whereHas('order', function ($query) {
                    $query->where('retailer_model_order', true)
                        ->where('payment_status', 'paid')
                        ->where('order_status', 'delivered')
                        ->whereNotNull('refund_window_expires_at')
                        ->where('refund_window_expires_at', '<=', now())
                        ->whereDoesntHave('dispute', function ($dispute) {
                            $dispute->whereNotIn('status', ['resolved', 'closed', 'rejected', 'cancelled']);
                        })
                        ->whereDoesntHave('returnCases', function ($returnCase) {
                            $returnCase->whereNotIn('status', ['rejected', 'closed', 'refunded', 'resolved', 'replacement_completed']);
                        });
                })
                ->orderBy('order_id')
                ->orderBy('id')
                ->get();

            if ($candidateItems->isEmpty()) {
                return ['success' => false, 'message' => 'No delivered, reconciled supplier item payables are currently eligible for payout.'];
            }

            $orderIds = $candidateItems->pluck('order_id')->unique()->sort()->values()->all();
            $lockedOrders = Order::whereIn('id', $orderIds)->orderBy('id')->lockForUpdate()->get();
            foreach ($lockedOrders as $lockedOrder) {
                if (!$lockedOrder->retailer_model_order
                    || $lockedOrder->payment_status !== 'paid'
                    || $lockedOrder->order_status !== 'delivered'
                    || !$lockedOrder->refund_window_expires_at
                    || $lockedOrder->refund_window_expires_at->gt(now())
                    || $lockedOrder->dispute()->whereNotIn('status', ['resolved', 'closed', 'rejected', 'cancelled'])->exists()
                    || $lockedOrder->returnCases()->whereNotIn('status', ['rejected', 'closed', 'refunded', 'resolved', 'replacement_completed'])->exists()) {
                    return ['success' => false, 'protected' => true, 'message' => "Order #{$lockedOrder->id} changed eligibility while supplier payout was being prepared."];
                }
            }
            $items = OrderItem::whereIn('order_id', $orderIds)
                ->where('fulfillment_supplier_vendor_id', $lockedSupplier->id)
                ->orderBy('order_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $audit = app(FinancialReconciliationEngine::class);
            foreach ($orderIds as $orderId) {
                $check = $audit->reconcileRetailerSupplierPayables((int) $orderId);
                if ($check['mismatches'] > 0) {
                    return [
                        'success' => false,
                        'protected' => true,
                        'message' => "Supplier payout is held because order #{$orderId} has a supplier-payable reconciliation mismatch.",
                    ];
                }
            }

            $itemIds = $items->pluck('id')->all();
            $reservedItemIds = RetailerSupplierPayoutAllocation::whereIn('order_item_id', $itemIds)
                ->whereIn('status', ['allocated', 'settled'])
                ->pluck('order_item_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $reservedLookup = array_fill_keys($reservedItemIds, true);

            $journalEntries = FinancialJournalEntry::whereIn('order_item_id', $itemIds)
                ->where('account_name', 'supplier_payable')
                ->where('beneficiary_type', VendorProfile::class)
                ->where('beneficiary_id', $lockedSupplier->id)
                ->get(['order_item_id', 'entry_type', 'amount'])
                ->groupBy('order_item_id');

            $eligible = [];
            foreach ($items as $item) {
                if (isset($reservedLookup[(int) $item->id])) {
                    continue;
                }

                $net = '0.00';
                foreach ($journalEntries->get($item->id, collect()) as $entry) {
                    $net = $entry->entry_type === 'credit'
                        ? BcMathService::add($net, (string) $entry->amount)
                        : BcMathService::sub($net, (string) $entry->amount);
                }

                if (BcMathService::comp($net, '0.00') <= 0) {
                    continue;
                }

                $eligible[] = [
                    'item' => $item,
                    'order_id' => (int) $item->order_id,
                    'amount' => $net,
                ];
            }

            usort($eligible, function ($a, $b) {
                $orderCompare = $a['order_id'] <=> $b['order_id'];
                return $orderCompare !== 0 ? $orderCompare : ($a['item']->id <=> $b['item']->id);
            });

            if (!$eligible) {
                return ['success' => false, 'message' => 'No unreserved supplier-payable journal balances are eligible for payout.'];
            }

            $allocations = [];
            $total = '0.00';
            foreach ($eligible as $candidate) {
                if (BcMathService::comp($candidate['amount'], self::MAXIMUM_AMOUNT) > 0 && !$allocations) {
                    return [
                        'success' => false,
                        'protected' => true,
                        'message' => "Supplier item #{$candidate['item']->id} exceeds the ₦500,000 launch payout cap and requires finance review.",
                    ];
                }

                $nextTotal = BcMathService::add($total, $candidate['amount']);
                if (BcMathService::comp($nextTotal, self::MAXIMUM_AMOUNT) > 0) {
                    break;
                }
                $allocations[] = $candidate;
                $total = $nextTotal;
            }

            if (BcMathService::comp($total, self::MINIMUM_AMOUNT) < 0) {
                return ['success' => false, 'message' => 'Eligible supplier payables are below the ₦5,000 minimum payout.'];
            }

            $payout = RetailerSupplierPayoutRequest::create([
                'supplier_vendor_id' => $lockedSupplier->id,
                'amount' => $total,
                'status' => 'pending',
                'gateway_status' => 'not_submitted',
                'transfer_reference' => 'SUPR-' . $lockedSupplier->id . '-' . Str::upper(Str::random(20)),
                'bank_name' => $lockedSupplier->bank_name,
                'bank_code' => $lockedSupplier->bank_code,
                'account_number' => $lockedSupplier->account_number,
                'account_name' => $lockedSupplier->account_name,
            ]);

            $allocatedItemIds = [];
            foreach ($allocations as $allocation) {
                /** @var OrderItem $item */
                $item = $allocation['item'];
                $allocatedItemIds[] = (int) $item->id;
                RetailerSupplierPayoutAllocation::create([
                    'retailer_supplier_payout_request_id' => $payout->id,
                    'supplier_vendor_id' => $lockedSupplier->id,
                    'order_id' => $allocation['order_id'],
                    'order_item_id' => $item->id,
                    'amount' => $allocation['amount'],
                    'status' => 'allocated',
                ]);

                $eventId = 'EVT-SUPPLIER-PAYOUT-REQ-' . $payout->id . '-ITEM-' . $item->id;
                $this->postEntry($item, $lockedSupplier->id, $payout->transfer_reference, $eventId,
                    'SUPPAYOUT-REQ-' . $payout->id . '-ITEM-' . $item->id . '-DR',
                    'supplier_payable', 'debit', $allocation['amount'], 'Reserve supplier payable for payout request.');
                $this->postEntry($item, $lockedSupplier->id, $payout->transfer_reference, $eventId,
                    'SUPPAYOUT-REQ-' . $payout->id . '-ITEM-' . $item->id . '-CR',
                    'supplier_payout_payable', 'credit', $allocation['amount'], 'Supplier payout request payable reservation.');
            }

            AdminAuditLog::create([
                'user_id' => $lockedSupplier->user_id,
                'action' => 'retailer_supplier_payout_requested',
                'target_type' => RetailerSupplierPayoutRequest::class,
                'target_id' => $payout->id,
                'old_values' => [],
                'new_values' => [
                    'supplier_vendor_id' => $lockedSupplier->id,
                    'amount' => $total,
                    'transfer_reference' => $payout->transfer_reference,
                    'order_item_ids' => $allocatedItemIds,
                    'bank_name' => $payout->bank_name,
                    'account_number_last4' => substr((string) $payout->account_number, -4),
                ],
            ]);

            return ['success' => true, 'payout' => $payout->fresh('allocations'), 'message' => 'Supplier payout request reserved for admin processing.'];
        }, 3);
    }

    public function markProcessing(int $payoutId, int $adminId, string $recipientCode, string $processingToken): array
    {
        // Fail closed even when this service is called outside the current admin
        // controller. These values identify the authorized transfer attempt.
        if ($adminId < 1 || trim($recipientCode) === '' || mb_strlen($recipientCode) > 200
            || !preg_match('/^[A-Za-z0-9._:-]{12,100}$/', trim($processingToken))) {
            return ['success' => false, 'protected' => true, 'message' => 'A valid administrator, verified transfer recipient, and unique processing token are required.'];
        }

        $recipientCode = trim($recipientCode);
        $processingToken = trim($processingToken);

        return DB::transaction(function () use ($payoutId, $adminId, $recipientCode, $processingToken) {
            // Follow supplier -> payout -> order -> item lock order, matching payout
            // reservation and return/recovery flows as closely as possible.
            $preview = RetailerSupplierPayoutRequest::whereKey($payoutId)->first();
            if (!$preview) {
                return ['success' => false, 'message' => 'Supplier payout request was not found.'];
            }
            $supplier = VendorProfile::whereKey($preview->supplier_vendor_id)->lockForUpdate()->first();
            $payout = RetailerSupplierPayoutRequest::whereKey($payoutId)->lockForUpdate()->first();
            if (!$payout || !$supplier || (int) $payout->supplier_vendor_id !== (int) $supplier->id) {
                return ['success' => false, 'protected' => true, 'message' => 'Supplier payout beneficiary changed or is unavailable.'];
            }
            if ($payout->status !== 'pending' || $payout->processing_token) {
                return ['success' => false, 'protected' => true, 'message' => 'Supplier payout is not available for a new transfer attempt.'];
            }
            if ($supplier->marketplace_status !== 'active'
                || !$supplier->user()->where('status', 'active')->exists()
                || !$supplier->isKycVerified()) {
                return ['success' => false, 'protected' => true, 'message' => 'Supplier or owner account is inactive, or KYC is no longer verified; payout remains reserved for review.'];
            }
            if ($this->hasOpenSupplierRecovery((int) $supplier->id)) {
                return ['success' => false, 'protected' => true, 'message' => 'An unresolved supplier recovery debt appeared after request creation; payout remains reserved for finance review.'];
            }
            if (!$supplier->bank_updated_at || $supplier->bank_updated_at->gt(now()->subHours(48))) {
                return ['success' => false, 'protected' => true, 'message' => 'Supplier bank-detail cooldown is active; payout remains reserved.'];
            }
            foreach (['bank_name', 'bank_code', 'account_number', 'account_name'] as $field) {
                if (trim((string) $supplier->{$field}) === '' || (string) $supplier->{$field} !== (string) $payout->{$field}) {
                    return ['success' => false, 'protected' => true, 'message' => 'Supplier bank details changed after this payout request; reject this request and create a fresh one after the cooldown.'];
                }
            }
            if (!$this->hasExactlyOneActiveUyoShop($supplier)) {
                return ['success' => false, 'protected' => true, 'message' => 'Supplier must have exactly one active shop located in Uyo, Akwa Ibom State before payout.'];
            }
            if (trim($recipientCode) === '') {
                return ['success' => false, 'message' => 'A verified Paystack transfer recipient is required.'];
            }
            if (!$this->requestJournalComplete($payout)) {
                return ['success' => false, 'protected' => true, 'message' => 'Supplier payout request journal is incomplete; finance reconciliation is required.'];
            }

            $allocations = $payout->allocations()->orderBy('order_id')->orderBy('order_item_id')->lockForUpdate()->get();
            if ($allocations->isEmpty()) {
                return ['success' => false, 'protected' => true, 'message' => 'Supplier payout has no item allocations.'];
            }
            foreach ($allocations as $allocation) {
                if ((int) $allocation->supplier_vendor_id !== (int) $supplier->id || $allocation->status !== 'allocated') {
                    return ['success' => false, 'protected' => true, 'message' => 'Supplier payout allocation beneficiary or status changed; finance review is required.'];
                }
            }

            $orderIds = $allocations->pluck('order_id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            $orders = Order::whereIn('id', $orderIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $itemIds = $allocations->pluck('order_item_id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            $items = OrderItem::whereIn('id', $itemIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($allocations as $allocation) {
                $order = $orders->get((int) $allocation->order_id);
                $item = $items->get((int) $allocation->order_item_id);
                if (!$order || !$item || (int) $item->order_id !== (int) $order->id
                    || (int) $item->fulfillment_supplier_vendor_id !== (int) $supplier->id
                    || !$order->retailer_model_order
                    || $order->payment_status !== 'paid'
                    || $order->order_status !== 'delivered'
                    || !$order->refund_window_expires_at
                    || $order->refund_window_expires_at->gt(now())
                    || $order->dispute()->whereNotIn('status', ['resolved', 'closed', 'rejected', 'cancelled'])->exists()
                    || $order->returnCases()->whereNotIn('status', ['rejected', 'closed', 'refunded', 'resolved', 'replacement_completed'])->exists()) {
                    return ['success' => false, 'protected' => true, 'message' => "Order/item eligibility changed for supplier payout allocation #{$allocation->id}; payout remains reserved."];
                }
            }

            $audit = app(FinancialReconciliationEngine::class);
            foreach ($orderIds as $orderId) {
                $check = $audit->reconcileRetailerSupplierPayables((int) $orderId);
                if ($check['mismatches'] > 0) {
                    return ['success' => false, 'protected' => true, 'message' => "Order #{$orderId} no longer reconciles; supplier payout remains reserved."];
                }
            }

            $payout->status = 'processing';
            $payout->gateway_status = 'processing';
            $payout->recipient_code = $recipientCode;
            $payout->processing_token = $processingToken;
            $payout->processing_started_at = now();
            $payout->approved_by_admin_id = $adminId;
            $payout->save();

            return ['success' => true, 'payout' => $payout->fresh()];
        }, 3);
    }

    public function markUncertain(int $payoutId, string $reason, string $gatewayStatus = 'uncertain'): array
    {
        $reason = trim($reason);
        $gatewayStatus = strtolower(trim($gatewayStatus));
        if ($reason === '' || mb_strlen($reason) > 2000
            || !preg_match('/^[a-z0-9_:-]{1,80}$/', $gatewayStatus)) {
            return ['success' => false, 'protected' => true, 'message' => 'A bounded reason and valid gateway status are required to place a payout on hold.'];
        }

        return DB::transaction(function () use ($payoutId, $reason, $gatewayStatus) {
            $payout = RetailerSupplierPayoutRequest::whereKey($payoutId)->lockForUpdate()->first();
            if (!$payout) {
                return ['success' => false, 'message' => 'Supplier payout request was not found.'];
            }
            if (in_array($payout->status, ['paid', 'reversed', 'failed', 'rejected'], true)) {
                return ['success' => true, 'idempotent' => true, 'payout' => $payout];
            }
            if (!in_array($payout->status, ['processing', 'uncertain'], true)) {
                return ['success' => false, 'protected' => true, 'message' => 'Only an initiated or already uncertain transfer can be placed on a gateway-reconciliation hold.'];
            }

            $payout->status = 'uncertain';
            $payout->gateway_status = strtolower($gatewayStatus) ?: 'uncertain';
            $payout->processing_token = 'HOLD_SUPPLIER_PAYOUT_RECONCILIATION';
            $payout->admin_notes = trim(($payout->admin_notes ? $payout->admin_notes . "\n" : '') . 'Finance hold: ' . $reason);
            $payout->save();

            Log::critical('Supplier payout outcome is uncertain; item payables remain reserved.', [
                'payout_id' => $payout->id,
                'supplier_vendor_id' => $payout->supplier_vendor_id,
                'reason' => $reason,
            ]);

            return ['success' => true, 'protected' => true, 'payout' => $payout->fresh()];
        }, 3);
    }

    public function finalizeSuccess(int $payoutId, string $transferReference, ?string $transferCode, string $verifiedAmount): array
    {
        return DB::transaction(function () use ($payoutId, $transferReference, $transferCode, $verifiedAmount) {
            $payout = RetailerSupplierPayoutRequest::whereKey($payoutId)->lockForUpdate()->first();
            if (!$payout) {
                return ['success' => false, 'message' => 'Supplier payout request was not found.'];
            }
            if ($payout->status === 'paid' && $this->payoutJournalComplete($payout)) {
                return ['success' => true, 'idempotent' => true, 'payout' => $payout];
            }
            if (!in_array($payout->status, ['processing', 'uncertain'], true)
                || trim((string) $payout->recipient_code) === ''
                || $payout->transfer_reference !== $transferReference
                || BcMathService::comp((string) $payout->amount, $verifiedAmount) !== 0) {
                return ['success' => false, 'protected' => true, 'message' => 'Supplier payout finalization does not match the active request, reference, and amount.'];
            }

            $allocations = $payout->allocations()->lockForUpdate()->orderBy('id')->get();
            if ($allocations->isEmpty() || !$this->requestJournalComplete($payout)) {
                return ['success' => false, 'protected' => true, 'message' => 'Supplier payout allocations or request journal are incomplete.'];
            }
            $orderIds = $allocations->pluck('order_id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            $lockedOrders = Order::whereIn('id', $orderIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $itemIds = $allocations->pluck('order_item_id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            $lockedItems = OrderItem::whereIn('id', $itemIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($allocations as $allocation) {
                if ((int) $allocation->supplier_vendor_id !== (int) $payout->supplier_vendor_id
                    || $allocation->status !== 'allocated') {
                    return ['success' => false, 'protected' => true, 'message' => 'Supplier payout allocation state or beneficiary changed; do not finalize automatically.'];
                }
                $itemIdentity = $lockedItems->get((int) $allocation->order_item_id);
                $orderIdentity = $lockedOrders->get((int) $allocation->order_id);
                if (!$itemIdentity || !$orderIdentity
                    || (int) $itemIdentity->order_id !== (int) $allocation->order_id
                    || (int) $itemIdentity->fulfillment_supplier_vendor_id !== (int) $payout->supplier_vendor_id
                    || !$orderIdentity->retailer_model_order
                    || $orderIdentity->payment_status !== 'paid'
                    || $orderIdentity->order_status !== 'delivered'
                    || !$orderIdentity->refund_window_expires_at
                    || $orderIdentity->refund_window_expires_at->gt(now())
                    || $orderIdentity->dispute()->whereNotIn('status', ['resolved', 'closed', 'rejected', 'cancelled'])->exists()
                    || $orderIdentity->returnCases()->whereNotIn('status', ['rejected', 'closed', 'refunded', 'resolved', 'replacement_completed'])->exists()) {
                    return ['success' => false, 'protected' => true, 'message' => 'Supplier payout no longer matches its delivered, eligible order item or a financial hold is active; finance reconciliation is required.'];
                }
                $eventId = 'EVT-SUPPLIER-PAYOUT-' . $payout->id . '-ITEM-' . $allocation->order_item_id;
                $entries = FinancialJournalEntry::where('event_id', $eventId)->count();
                if ($entries !== 0 && !$this->hasExactPair($eventId, $allocation, 'supplier_payout_payable', 'debit', 'cash_in_escrow', 'credit')) {
                    return ['success' => false, 'protected' => true, 'message' => 'Partial supplier payout journal detected; finance reconciliation is required.'];
                }
            }

            foreach ($allocations as $allocation) {
                $eventId = 'EVT-SUPPLIER-PAYOUT-' . $payout->id . '-ITEM-' . $allocation->order_item_id;
                if (!$this->hasExactPair($eventId, $allocation, 'supplier_payout_payable', 'debit', 'cash_in_escrow', 'credit')) {
                    $item = OrderItem::whereKey($allocation->order_item_id)->firstOrFail();
                    $this->postEntry($item, $payout->supplier_vendor_id, $transferReference, $eventId,
                        'SUPPAYOUT-PAID-' . $payout->id . '-ITEM-' . $item->id . '-DR',
                        'supplier_payout_payable', 'debit', (string) $allocation->amount, 'Confirmed supplier payout liability settlement.');
                    $this->postEntry($item, $payout->supplier_vendor_id, $transferReference, $eventId,
                        'SUPPAYOUT-PAID-' . $payout->id . '-ITEM-' . $item->id . '-CR',
                        'cash_in_escrow', 'credit', (string) $allocation->amount, 'Confirmed supplier payout cash transfer.');
                }
                $allocation->status = 'settled';
                $allocation->save();
            }

            $payout->status = 'paid';
            $payout->gateway_status = 'success';
            $payout->paystack_transfer_code = $transferCode;
            $payout->disbursed_at = now();
            $payout->processing_token = null;
            $payout->save();

            Log::notice('Retailer supplier payout finalized after verified gateway success.', [
                'payout_id' => $payout->id,
                'supplier_vendor_id' => $payout->supplier_vendor_id,
                'amount' => (string) $payout->amount,
                'transfer_reference' => $payout->transfer_reference,
                'transfer_code_present' => trim((string) $transferCode) !== '',
            ]);

            return ['success' => true, 'payout' => $payout->fresh('allocations')];
        }, 3);
    }

    /**
     * Release an unsubmitted/definitively failed request or reverse an authoritative
     * gateway reversal. An uncertain gateway outcome must never call this method.
     */
    public function reverse(int $payoutId, string $outcome, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 2000) {
            return ['success' => false, 'protected' => true, 'message' => 'A reversal or reservation-release reason of 1–2,000 characters is required.'];
        }
        if (!in_array($outcome, ['not_submitted', 'failed', 'reversed'], true)) {
            return ['success' => false, 'protected' => true, 'message' => 'Only an authoritative non-submission, failure, or reversal can release supplier payout reservations.'];
        }

        return DB::transaction(function () use ($payoutId, $outcome, $reason) {
            $payout = RetailerSupplierPayoutRequest::whereKey($payoutId)->lockForUpdate()->first();
            if (!$payout) {
                return ['success' => false, 'message' => 'Supplier payout request was not found.'];
            }
            if (in_array($payout->status, ['rejected', 'failed', 'reversed'], true)
                && $payout->allocations()->where('status', '!=', 'reversed')->doesntExist()) {
                return ['success' => true, 'idempotent' => true, 'payout' => $payout];
            }
            if ($outcome === 'not_submitted'
                && ($payout->status !== 'pending' || $payout->processing_token
                    || !in_array($payout->gateway_status, ['not_submitted', null], true))) {
                return ['success' => false, 'protected' => true, 'message' => 'A payout that may have reached the gateway cannot be manually rejected as unsubmitted.'];
            }
            if ($payout->status === 'paid' && $outcome !== 'reversed') {
                return ['success' => false, 'protected' => true, 'message' => 'A paid supplier payout can only be reversed after the gateway confirms a reversal.'];
            }

            $allocations = $payout->allocations()->lockForUpdate()->orderBy('id')->get();
            if ($allocations->isEmpty() || !$this->requestJournalComplete($payout)) {
                return ['success' => false, 'protected' => true, 'message' => 'Supplier payout reservation evidence is incomplete; no money or status was changed.'];
            }
            foreach ($allocations as $allocation) {
                if ((int) $allocation->supplier_vendor_id !== (int) $payout->supplier_vendor_id
                    || !in_array($allocation->status, ['allocated', 'settled'], true)
                    || ($allocation->status === 'settled' && $outcome !== 'reversed')) {
                    return ['success' => false, 'protected' => true, 'message' => 'Supplier payout allocation state conflicts with the requested reversal; finance reconciliation is required.'];
                }
            }

            $payoutJournalCount = 0;
            $completePayoutJournalCount = 0;
            foreach ($allocations as $allocation) {
                $eventId = 'EVT-SUPPLIER-PAYOUT-' . $payout->id . '-ITEM-' . $allocation->order_item_id;
                $count = FinancialJournalEntry::where('event_id', $eventId)->count();
                $payoutJournalCount += $count;
                if ($this->hasExactPair($eventId, $allocation, 'supplier_payout_payable', 'debit', 'cash_in_escrow', 'credit')) {
                    $completePayoutJournalCount += 2;
                }
            }
            if ($payoutJournalCount !== 0 && $completePayoutJournalCount !== $payoutJournalCount) {
                return ['success' => false, 'protected' => true, 'message' => 'Partial or conflicting supplier payout journal detected; no reversal was posted.'];
            }
            if ($payoutJournalCount > 0 && $outcome !== 'reversed') {
                return ['success' => false, 'protected' => true, 'message' => 'Gateway failure conflicts with a posted supplier payout journal; finance reconciliation is required.'];
            }

            // Preflight every reversal/release event before writing any leg. A partial
            // historical event must hold the entire request, not commit a partial repair.
            foreach ($allocations as $allocation) {
                $itemId = (int) $allocation->order_item_id;
                $reverseEvent = 'EVT-SUPPLIER-PAYOUT-REVERSAL-' . $payout->id . '-ITEM-' . $itemId;
                $releaseEvent = 'EVT-SUPPLIER-PAYOUT-RELEASE-' . $payout->id . '-ITEM-' . $itemId;
                $reverseCount = FinancialJournalEntry::where('event_id', $reverseEvent)->count();
                $releaseCount = FinancialJournalEntry::where('event_id', $releaseEvent)->count();
                if (($reverseCount > 0 && !$this->hasExactPair($reverseEvent, $allocation, 'cash_in_escrow', 'debit', 'supplier_payout_payable', 'credit'))
                    || ($releaseCount > 0 && !$this->hasExactPair($releaseEvent, $allocation, 'supplier_payout_payable', 'debit', 'supplier_payable', 'credit'))) {
                    return ['success' => false, 'protected' => true, 'message' => 'Partial supplier payout reversal evidence detected; no journal or allocation was changed.'];
                }
            }

            foreach ($allocations as $allocation) {
                $item = OrderItem::whereKey($allocation->order_item_id)->lockForUpdate()->firstOrFail();
                $paidEvent = 'EVT-SUPPLIER-PAYOUT-' . $payout->id . '-ITEM-' . $item->id;
                if ($this->hasExactPair($paidEvent, $allocation, 'supplier_payout_payable', 'debit', 'cash_in_escrow', 'credit')) {
                    $reverseEvent = 'EVT-SUPPLIER-PAYOUT-REVERSAL-' . $payout->id . '-ITEM-' . $item->id;
                    if (FinancialJournalEntry::where('event_id', $reverseEvent)->count() === 0) {
                        $this->postEntry($item, $payout->supplier_vendor_id, $payout->transfer_reference, $reverseEvent,
                            'SUPPAYOUT-REV-' . $payout->id . '-ITEM-' . $item->id . '-DR',
                            'cash_in_escrow', 'debit', (string) $allocation->amount, 'Reverse confirmed supplier payout cash transfer.');
                        $this->postEntry($item, $payout->supplier_vendor_id, $payout->transfer_reference, $reverseEvent,
                            'SUPPAYOUT-REV-' . $payout->id . '-ITEM-' . $item->id . '-CR',
                            'supplier_payout_payable', 'credit', (string) $allocation->amount, 'Restore supplier payout payable after gateway reversal.');
                    }
                }

                $releaseEvent = 'EVT-SUPPLIER-PAYOUT-RELEASE-' . $payout->id . '-ITEM-' . $item->id;
                if (FinancialJournalEntry::where('event_id', $releaseEvent)->count() === 0) {
                    $this->postEntry($item, $payout->supplier_vendor_id, $payout->transfer_reference, $releaseEvent,
                        'SUPPAYOUT-RELEASE-' . $payout->id . '-ITEM-' . $item->id . '-DR',
                        'supplier_payout_payable', 'debit', (string) $allocation->amount, 'Release supplier payout reservation.');
                    $this->postEntry($item, $payout->supplier_vendor_id, $payout->transfer_reference, $releaseEvent,
                        'SUPPAYOUT-RELEASE-' . $payout->id . '-ITEM-' . $item->id . '-CR',
                        'supplier_payable', 'credit', (string) $allocation->amount, 'Return reserved balance to supplier payable.');
                }
                $allocation->status = 'reversed';
                $allocation->reversed_at = now();
                $allocation->save();
            }

            $payout->status = $outcome === 'reversed' ? 'reversed' : ($outcome === 'failed' ? 'failed' : 'rejected');
            $payout->gateway_status = $outcome;
            $payout->processing_token = null;
            $payout->admin_notes = trim(($payout->admin_notes ? $payout->admin_notes . "\n" : '') . 'Reservation released: ' . $reason);
            $payout->save();

            Log::warning('Retailer supplier payout reservation was released or reversed.', [
                'payout_id' => $payout->id,
                'supplier_vendor_id' => $payout->supplier_vendor_id,
                'amount' => (string) $payout->amount,
                'transfer_reference' => $payout->transfer_reference,
                'outcome' => $outcome,
                'reason' => $reason,
            ]);

            return ['success' => true, 'payout' => $payout->fresh('allocations')];
        }, 3);
    }

    private function hasExactlyOneActiveUyoShop(VendorProfile $supplier): bool
    {
        $shops = $supplier->shops()
            ->where('is_active', true)
            ->with('lga.state')
            ->get();

        if ($shops->count() !== 1) {
            return false;
        }

        $shop = $shops->first();
        return strtolower(trim((string) ($shop->lga->name ?? ''))) === 'uyo'
            && strtolower(trim((string) ($shop->lga->state->name ?? ''))) === 'akwa ibom';
    }

    private function hasOpenSupplierRecovery(int $supplierId): bool
    {
        return VendorRecoveryLedger::where('vendor_id', $supplierId)
            ->where('status', 'pending_recovery')
            ->where('amount', '>', 0)
            ->exists();
    }

    public function requestJournalComplete(RetailerSupplierPayoutRequest $payout): bool
    {
        $allocations = $payout->allocations()->get();
        if ($allocations->isEmpty()) {
            return false;
        }
        $total = '0.00';
        foreach ($allocations as $allocation) {
            if ((int) $allocation->supplier_vendor_id !== (int) $payout->supplier_vendor_id) {
                return false;
            }
            $total = BcMathService::add($total, (string) $allocation->amount);
            $eventId = 'EVT-SUPPLIER-PAYOUT-REQ-' . $payout->id . '-ITEM-' . $allocation->order_item_id;
            if (!$this->hasExactPair($eventId, $allocation, 'supplier_payable', 'debit', 'supplier_payout_payable', 'credit')) {
                return false;
            }
        }
        return BcMathService::comp($total, (string) $payout->amount) === 0;
    }

    private function payoutJournalComplete(RetailerSupplierPayoutRequest $payout): bool
    {
        $allocations = $payout->allocations()->get();
        if ($allocations->isEmpty()) {
            return false;
        }
        foreach ($allocations as $allocation) {
            $eventId = 'EVT-SUPPLIER-PAYOUT-' . $payout->id . '-ITEM-' . $allocation->order_item_id;
            if (!$this->hasExactPair($eventId, $allocation, 'supplier_payout_payable', 'debit', 'cash_in_escrow', 'credit')) {
                return false;
            }
        }
        return true;
    }

    private function hasExactPair(
        string $eventId,
        RetailerSupplierPayoutAllocation $allocation,
        string $debitAccount,
        string $debitType,
        string $creditAccount,
        string $creditType
    ): bool {
        $reference = $allocation->payoutRequest?->transfer_reference;
        if (!$reference) {
            return false;
        }

        $event = FinancialJournalEntry::where('event_id', $eventId)
            ->where('transaction_reference', $reference)
            ->where('order_id', $allocation->order_id)
            ->where('order_item_id', $allocation->order_item_id)
            ->where('beneficiary_type', VendorProfile::class)
            ->where('beneficiary_id', $allocation->supplier_vendor_id)
            ->where('amount', (string) $allocation->amount)
            ->where('currency', 'NGN');

        return (clone $event)->count() === 2
            && (clone $event)->distinct()->count('transaction_reference') === 1
            && (clone $event)->where('account_name', $debitAccount)->where('entry_type', $debitType)->exists()
            && (clone $event)->where('account_name', $creditAccount)->where('entry_type', $creditType)->exists();
    }

    private function postEntry(
        OrderItem $item,
        int $supplierId,
        string $transactionReference,
        string $eventId,
        string $idempotencyKey,
        string $accountName,
        string $entryType,
        string $amount,
        string $description
    ): void {
        FinancialJournalEntry::create([
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'transaction_reference' => $transactionReference,
            'event_id' => $eventId,
            'idempotency_key' => $idempotencyKey,
            'beneficiary_type' => VendorProfile::class,
            'beneficiary_id' => $supplierId,
            'account_name' => $accountName,
            'entry_type' => $entryType,
            'amount' => BcMathService::add($amount, '0.00'),
            'currency' => 'NGN',
            'description' => $description,
        ]);
    }
}
