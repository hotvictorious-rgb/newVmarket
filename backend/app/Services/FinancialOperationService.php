<?php

namespace App\Services;

use App\Models\FinancialOperation;
use App\Models\GatewayAttempt;
use App\Models\FinancialReservation;
use App\Models\FinancialJournalEntry;
use App\Models\Order;
use App\Models\ReturnCase;
use App\Models\VendorPayoutRequest;
use App\Models\VendorProfile;
use App\Models\CustomerWalletCredit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FinancialOperationService
{
    protected PaystackService $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /**
     * Atomically create an approved Customer Refund Operation with strict reservation bounds.
     * Invariant: Sum(confirmed refunds) + Sum(active reservations) + requested <= original refundable payment
     */
    public function createRefundOperation(
        Order $order,
        string $amountNaira,
        string $reason,
        ?ReturnCase $returnCase = null,
        ?string $idempotencyKey = null,
        ?array $authorization = null
    ): FinancialOperation {
        // Money entering this service must already be expressed in whole cents.
        // BCMath's requested scale silently truncates excess fractional digits,
        // which could otherwise turn a request for 10.999 into a refund of 10.99.
        if (!preg_match('/^\\d+(?:\\.\\d{1,2})?$/', trim((string) $amountNaira))) {
            throw new \InvalidArgumentException(
                "Refund amount must be a positive decimal with no more than two fractional digits."
            );
        }

        $amountNormalized = bcadd(trim((string) $amountNaira), '0', 2);
        if (bccomp($amountNormalized, '0.00', 2) <= 0) {
            throw new \InvalidArgumentException("Refund amount must be strictly greater than 0. Received: '{$amountNaira}'");
        }

        $amountKobo = (int) bcmul($amountNormalized, '100', 0);
        $key = $idempotencyKey ?: ('REFUND-' . $order->id . '-' . ($returnCase ? $returnCase->id : 'FULL') . '-' . md5($reason . $amountNormalized));

        return DB::transaction(function () use ($order, $amountNormalized, $amountKobo, $reason, $returnCase, $key, $authorization) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            // A caller must not attach a return case from another order to this
            // refund operation. Lock it in the same order used by finalization.
            if ($returnCase) {
                $lockedReturnCase = ReturnCase::whereKey($returnCase->id)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedReturnCase || (int) $lockedReturnCase->order_id !== (int) $lockedOrder->id) {
                    throw new \DomainException(
                        'Cannot create refund: return case does not belong to the specified order.'
                    );
                }

                $returnCase = $lockedReturnCase;
            }

            // 1. Idempotency Guard: Check if exact idempotency key exists first so retries do not fail against their own reservation
            $existingOp = FinancialOperation::where('idempotency_key', $key)->first();
            if ($existingOp) {
                // An idempotency key identifies one exact financial instruction, not
                // merely any prior refund. Reject reuse across orders, amounts, or
                // return cases instead of silently returning another customer's op.
                $existingMetadata = $existingOp->metadata ?? [];
                $expectedOwnerType = $returnCase ? ReturnCase::class : Order::class;
                $expectedOwnerId = $returnCase ? (int) $returnCase->id : (int) $lockedOrder->id;
                $sameInstruction =
                    $existingOp->operation_type === 'customer_refund'
                    && strtoupper((string) $existingOp->currency) === 'NGN'
                    && (int) $existingOp->amount_kobo === $amountKobo
                    && $existingOp->owner_type === $expectedOwnerType
                    && (int) $existingOp->owner_id === $expectedOwnerId
                    && (int) ($existingMetadata['order_id'] ?? 0) === (int) $lockedOrder->id
                    && (int) ($existingMetadata['return_case_id'] ?? 0) === (int) ($returnCase?->id ?? 0)
                    && (string) ($existingMetadata['reason'] ?? '') === $reason;

                if (!$sameInstruction) {
                    throw new \DomainException(
                        'Idempotency key has already been used for a different refund instruction.'
                    );
                }

                return $existingOp;
            }

            // Refunds are sent through Paystack, so the order must have a confirmed
            // captured payment and its original gateway reference. A positive order
            // total alone is not evidence that cash was received.
            if ($lockedOrder->payment_status !== 'paid') {
                throw new \DomainException(
                    'Cannot create a gateway refund for an order whose payment is not confirmed as paid.'
                );
            }

            if (trim((string) $lockedOrder->payment_reference) === '') {
                throw new \DomainException(
                    'Cannot create a gateway refund without the original payment reference.'
                );
            }

            // A return case has its own approved refund ceiling in addition to the
            // order-wide ceiling. Locking the case above serializes refund creation
            // for that case, while counting both active holds and committed refunds.
            if ($returnCase) {
                $caseCeilingNaira = bcadd((string) ($returnCase->total_refundable_amount ?? '0.00'), '0', 2);

                // Delivery is a distinct refund component. Add its ceiling only
                // when this operation explicitly authorizes it and the decision
                // was persisted on the return case before gateway dispatch.
                if (($authorization['refund_delivery_fee'] ?? false) === true) {
                    if (!(bool) $returnCase->refund_delivery_fee
                        || bccomp((string) $lockedOrder->delivery_fee, '0.00', 2) <= 0) {
                        throw new \DomainException(
                            'Delivery-fee refund is not separately authorized for this return case.'
                        );
                    }
                    $caseCeilingNaira = bcadd($caseCeilingNaira, (string) $lockedOrder->delivery_fee, 2);
                }

                $caseCeilingKobo = (int) bcmul($caseCeilingNaira, '100', 0);

                $caseReservationsKobo = (int) FinancialReservation::query()
                    ->where('source_type', Order::class)
                    ->where('source_id', $lockedOrder->id)
                    ->where('purpose', 'customer_refund_hold')
                    ->whereIn('status', ['active', 'committed'])
                    ->whereHas('operation', function ($q) use ($returnCase) {
                        $q->where('operation_type', 'customer_refund')
                            ->where('metadata->return_case_id', $returnCase->id);
                    })
                    ->sum('reserved_amount_kobo');

                $caseConfirmedWithoutReservationKobo = (int) FinancialOperation::query()
                    ->where('operation_type', 'customer_refund')
                    ->where('status', 'confirmed')
                    ->where('currency', 'NGN')
                    ->where('metadata->order_id', $lockedOrder->id)
                    ->where('metadata->return_case_id', $returnCase->id)
                    ->whereDoesntHave('reservations', function ($q) use ($lockedOrder) {
                        $q->where('purpose', 'customer_refund_hold')
                            ->whereIn('status', ['active', 'committed'])
                            ->where('source_type', Order::class)
                            ->where('source_id', $lockedOrder->id);
                    })
                    ->sum('amount_kobo');

                $caseRefundedOrReservedKobo = $caseReservationsKobo + $caseConfirmedWithoutReservationKobo;

                if ($caseCeilingKobo <= 0 || $caseRefundedOrReservedKobo + $amountKobo > $caseCeilingKobo) {
                    $caseRemainingKobo = max(0, $caseCeilingKobo - $caseRefundedOrReservedKobo);
                    $caseRemainingNaira = bcdiv((string) $caseRemainingKobo, '100', 2);
                    throw new \DomainException(
                        "Refund invariant violation: requested amount exceeds the approved refundable balance of ₦{$caseRemainingNaira} for Return Case #{$returnCase->case_number}."
                    );
                }
            }

            // 2. Reservation Ceiling Check: Total refundable original payment (excluding unpaid or uncaptured)
            $totalOriginalPaymentNaira = bcadd((string)$lockedOrder->grand_total, '0', 2);
            $totalOriginalPaymentKobo = (int) bcmul($totalOriginalPaymentNaira, '100', 0);

            // Check for existing unattached provisional hold on this order (e.g. from doorstep rejection)
            $provisionalHold = FinancialReservation::where('source_type', Order::class)
                ->where('source_id', $lockedOrder->id)
                ->where('purpose', 'customer_refund_hold')
                ->where('status', 'active')
                ->whereNull('financial_operation_id')
                ->first();

            // Calculate currently confirmed refunds + active refund reservations for this order (excluding claimed provisional hold)
            $existingReservationsKobo = (int) FinancialReservation::where('source_type', Order::class)
                ->where('source_id', $lockedOrder->id)
                ->where('purpose', 'customer_refund_hold')
                ->whereIn('status', ['active', 'committed'])
                ->when($provisionalHold, function ($q) use ($provisionalHold) {
                    $q->where('id', '!=', $provisionalHold->id);
                })
                ->sum('reserved_amount_kobo');

            // Legacy or repaired confirmed refund operations may predate reservation
            // rows. Count those only when no matching active/committed reservation
            // already accounts for the same operation, preventing both undercounting
            // and double-counting the refundable ceiling.
            $confirmedRefundsWithoutReservationKobo = (int) FinancialOperation::query()
                ->where('operation_type', 'customer_refund')
                ->where('status', 'confirmed')
                ->where('currency', 'NGN')
                ->where('metadata->order_id', $lockedOrder->id)
                ->whereDoesntHave('reservations', function ($q) use ($lockedOrder) {
                    $q->where('purpose', 'customer_refund_hold')
                        ->whereIn('status', ['active', 'committed'])
                        ->where('source_type', Order::class)
                        ->where('source_id', $lockedOrder->id);
                })
                ->sum('amount_kobo');

            $existingReservationsKobo += $confirmedRefundsWithoutReservationKobo;

            if ($existingReservationsKobo + $amountKobo > $totalOriginalPaymentKobo) {
                $maxAllowableKobo = max(0, $totalOriginalPaymentKobo - $existingReservationsKobo);
                $maxAllowableNaira = bcdiv((string)$maxAllowableKobo, '100', 2);
                throw new \DomainException(
                    "Refund invariant violation: Requested ₦{$amountNormalized} exceeds remaining refundable balance of ₦{$maxAllowableNaira} on Order #{$lockedOrder->order_number}."
                );
            }

            $opRef = 'OP-RFD-' . $lockedOrder->id . '-' . strtoupper(Str::random(10));

            $operation = FinancialOperation::create([
                'operation_reference' => $opRef,
                'idempotency_key' => $key,
                'operation_type' => 'customer_refund',
                'owner_type' => $returnCase ? ReturnCase::class : Order::class,
                'owner_id' => $returnCase ? $returnCase->id : $lockedOrder->id,
                'amount_kobo' => $amountKobo,
                'currency' => 'NGN',
                'status' => 'holding_reservation',
                'gateway_provider' => 'paystack',
                'metadata' => [
                    'order_id' => $lockedOrder->id,
                    'order_number' => $lockedOrder->order_number,
                    'payment_reference' => $lockedOrder->payment_reference,
                    'return_case_id' => $returnCase?->id,
                    'return_case_number' => $returnCase?->case_number,
                    'reason' => $reason,
                    'refund_authorization' => $authorization,
                ],
            ]);

            // Link existing provisional hold or create new active reservation
            if ($provisionalHold && $provisionalHold->reserved_amount_kobo == $amountKobo) {
                $provisionalHold->update([
                    'financial_operation_id' => $operation->id,
                ]);
            } else {
                if ($provisionalHold) {
                    $provisionalHold->update(['status' => 'released', 'released_at' => Carbon::now()]);
                }
                FinancialReservation::create([
                    'financial_operation_id' => $operation->id,
                    'source_type' => Order::class,
                    'source_id' => $lockedOrder->id,
                    'reserved_amount_kobo' => $amountKobo,
                    'purpose' => 'customer_refund_hold',
                    'status' => 'active',
                ]);
            }

            return $operation;
        });
    }

    /**
     * Validate persisted authorization at the last service boundary before gateway dispatch.
     */
    protected function hasValidRefundAuthorization(FinancialOperation $operation, ?array $authorization): bool
    {
        if (!is_array($authorization)) return false;
        $meta = $operation->metadata ?? [];
        $order = Order::find($meta['order_id'] ?? null);
        if (!$order || $order->payment_status !== 'paid' || empty($order->payment_reference)) return false;

        if (($authorization['type'] ?? null) === 'admin_approved_return') {
            $caseId = $meta['return_case_id'] ?? null;
            $case = $caseId ? ReturnCase::find($caseId) : null;
            $approverId = (int) ($authorization['approved_by_user_id'] ?? 0);
            $deliveryFeeAuthorized = ($authorization['refund_delivery_fee'] ?? false) === true;

            return $case
                && (int) $case->order_id === (int) $order->id
                && in_array($case->status, ['refund_pending', 'refund_approved'], true)
                && !empty($case->reviewed_by_admin_id)
                && (int) $case->reviewed_by_admin_id === $approverId
                && (!$deliveryFeeAuthorized || (bool) $case->refund_delivery_fee);
        }

        if (($authorization['type'] ?? null) === 'auto_late_payment_cancelled_before_vendor_action') {
            return $order->order_status === 'paid_stock_unavailable'
                && $order->vendor_settlement_status === 'refund_pending'
                && $order->payment_status === 'paid';
        }

        return false;
    }

    /**
     * Dispatch refund to payment gateway (Paystack).
     * Rule: DB transaction is NEVER held open while awaiting Paystack HTTP response.
     */
    public function dispatchRefund(FinancialOperation $operation): array
    {
        // 1. Prepare attempt inside quick atomic transaction
        $attemptData = DB::transaction(function () use ($operation) {
            $lockedOp = FinancialOperation::where('id', $operation->id)->lockForUpdate()->firstOrFail();
            if ($lockedOp->isTerminal()) {
                return [
                    'terminal' => true,
                    'operation' => $lockedOp,
                    'message' => "Operation is already in terminal state: {$lockedOp->status}",
                ];
            }

            // An initiated operation may have reached Paystack even if our HTTP call timed out
            // or the worker crashed. Never submit another refund blindly; reconcile the existing
            // attempt first to avoid issuing a duplicate customer refund.
            if ($lockedOp->status === 'initiated') {
                return [
                    'terminal' => true,
                    'operation' => $lockedOp,
                    'message' => 'Refund dispatch already attempted; duplicate dispatch suppressed pending gateway reconciliation.',
                ];
            }

            // Every refund dispatch needs persisted authorization. A return-case authorization
            // must match the case's recorded human reviewer; the only automatic exception is a
            // verified payment that arrived after cancellation and depleted inventory.
            $meta = $lockedOp->metadata ?? [];
            $authorization = $meta['refund_authorization'] ?? null;
            if (!$this->hasValidRefundAuthorization($lockedOp, $authorization)) {
                return [
                    'terminal' => true,
                    'operation' => $lockedOp,
                    'message' => 'Refund dispatch blocked: persisted human approval or an approved automatic-refund exception is required.',
                ];
            }

            $attemptRef = 'ATT-RFD-' . $lockedOp->id . '-' . date('YmdHis') . '-' . Str::random(6);
            $meta = $lockedOp->metadata ?? [];
            $transRef = $meta['payment_reference'] ?? null;

            if (empty($transRef)) {
                $orderId = $meta['order_id'] ?? null;
                $ord = $orderId ? Order::find($orderId) : null;
                $transRef = $ord?->payment_reference;
            }

            if (empty($transRef)) {
                throw new \DomainException("Cannot dispatch refund: Missing original payment_reference for Operation #{$lockedOp->id}");
            }

            $attempt = GatewayAttempt::create([
                'financial_operation_id' => $lockedOp->id,
                'gateway_provider' => 'paystack',
                'endpoint' => '/refund',
                'attempt_reference' => $attemptRef,
                'request_payload' => [
                    'transaction' => $transRef,
                    'amount_kobo' => $lockedOp->amount_kobo,
                    'amount_naira' => $lockedOp->amount_naira,
                    'note' => $meta['reason'] ?? "Refund for Operation #{$lockedOp->operation_reference}",
                ],
                'outcome' => 'pending',
                'attempted_at' => Carbon::now(),
            ]);

            $lockedOp->update([
                'status' => 'initiated',
            ]);

            return [
                'terminal' => false,
                'attempt' => $attempt,
                'trans_ref' => $transRef,
                'amount_naira' => $lockedOp->amount_naira,
                'note' => $meta['reason'] ?? "Refund for Operation #{$lockedOp->operation_reference}",
            ];
        });

        if ($attemptData['terminal']) {
            return [
                'success' => $attemptData['operation']->status === 'confirmed',
                'status' => $attemptData['operation']->status,
                'message' => $attemptData['message'] ?? "Operation is already in terminal state: {$attemptData['operation']->status}",
            ];
        }

        /** @var GatewayAttempt $attempt */
        $attempt = $attemptData['attempt'];

        // 2. Call Paystack Gateway OUTSIDE DB Transaction
        $gatewayResult = $this->paystackService->initiateRefund(
            $attemptData['trans_ref'],
            $attemptData['amount_naira'],
            $attemptData['note']
        );

        // 3. Record outcome in separate quick transaction
        $dispatchResult = DB::transaction(function () use ($operation, $attempt, $gatewayResult) {
            $lockedOp = FinancialOperation::where('id', $operation->id)->lockForUpdate()->firstOrFail();
            $lockedAttempt = GatewayAttempt::where('id', $attempt->id)->lockForUpdate()->firstOrFail();
            // Re-read the status independently after the gateway call. This is an
            // explicit compare point for the webhook/HTTP-response race.
            $currentOperationStatus = FinancialOperation::whereKey($operation->id)->value('status');
            if (in_array($currentOperationStatus, ['confirmed', 'failed', 'reversed'], true)) {
                return [
                    'success' => $currentOperationStatus === 'confirmed',
                    'status' => $currentOperationStatus,
                    'message' => "Refund operation reached terminal state '{$currentOperationStatus}' while the gateway request was in flight; preserving the terminal result.",
                ];
            }

            // A webhook may win the race while the gateway HTTP request is in flight.
            // Re-check terminal state under the operation lock before writing the late
            // HTTP response, otherwise a pending response can downgrade a completed refund.
            if ($lockedOp->isTerminal()) {
                return [
                    'success' => $lockedOp->status === 'confirmed',
                    'status' => $lockedOp->status,
                    'message' => "Refund operation reached terminal state '{$lockedOp->status}' while the gateway request was in flight; preserving the terminal result.",
                ];
            }

            if (!empty($gatewayResult['success'])) {
                // Gateway ACCEPTED the refund request (starts in pending/processing)
                $gwRef = $gatewayResult['refund_reference'] ?? ($gatewayResult['data']['reference'] ?? null);
                $gwStatus = strtolower($gatewayResult['status'] ?? 'pending');

                $lockedAttempt->update([
                    'gateway_reference' => $gwRef,
                    'response_payload' => $gatewayResult,
                    'outcome' => 'success',
                ]);

                // Update Order refund reference for trace
                $orderId = $lockedOp->metadata['order_id'] ?? null;
                if ($orderId) {
                    Order::where('id', $orderId)
                        ->where(function ($query) {
                            $query->whereNull('paystack_refund_status')
                                ->orWhere('paystack_refund_status', '!=', 'completed');
                        })
                        ->update([
                            'paystack_refund_reference' => $gwRef,
                            'paystack_refund_status' => 'pending', // Remains pending until confirmed!
                        ]);
                }

                // If gateway already confirmed it synchronously in test mode or instant settlement:
                if (in_array($gwStatus, ['processed', 'success', 'completed'], true)) {
                    $this->finalizeRefundConfirmation($lockedOp, $gatewayResult);
                    return [
                        'success' => true,
                        'status' => 'confirmed',
                        'refund_reference' => $gwRef,
                        'message' => 'Refund confirmed and settled successfully.',
                    ];
                }

                return [
                    'success' => true,
                    'status' => 'initiated',
                    'refund_reference' => $gwRef,
                    'message' => 'Refund request accepted by gateway. Awaiting settlement confirmation.',
                ];
            }

            // Definite rejection vs uncertain outcome
            $isDefinitive = !empty($gatewayResult['is_definitive']) || ($gatewayResult['code'] ?? '') === 'DEFINITIVE_REJECTION';

            $lockedAttempt->update([
                'outcome' => $isDefinitive ? 'failed' : 'uncertain_timeout',
                'error_message' => $gatewayResult['message'] ?? 'Gateway rejected or timed out.',
                'response_payload' => $gatewayResult,
            ]);

            if ($isDefinitive) {
                // Gateway definitively rejected the request (e.g. card invalid, duplicate, limit exceeded)
                $lockedOp->update([
                    'status' => 'failed',
                    'failed_at' => Carbon::now(),
                ]);

                // Release active reservations
                FinancialReservation::where('financial_operation_id', $lockedOp->id)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'released',
                        'released_at' => Carbon::now(),
                    ]);

                // Revert return case claim token if applicable
                if ($lockedOp->owner_type === ReturnCase::class && $lockedOp->owner_id) {
                    ReturnCase::where('id', $lockedOp->owner_id)->update([
                        'refund_claim_token' => null,
                        'refund_claimed_at' => null,
                    ]);
                }

                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'Refund rejected by gateway: ' . ($gatewayResult['message'] ?? 'Unknown error'),
                ];
            }

            // Uncertain timeout: Keep reservation active, keep status initiated
            return [
                'success' => false,
                'status' => 'initiated',
                'code' => 'UNCERTAIN_TIMEOUT',
                'message' => 'Gateway request timed out or experienced connection dip. Kept in initiated state awaiting verification.',
            ];
        });

        // Final fence outside the response-write transaction: if a webhook confirmed
        // the operation while the HTTP response was in flight, report that durable
        // state rather than the stale response handler's provisional result.
        $latestStatus = FinancialOperation::whereKey($operation->id)->value('status');
        if (in_array($latestStatus, ['confirmed', 'failed', 'reversed'], true)) {
            return [
                'success' => $latestStatus === 'confirmed',
                'status' => $latestStatus,
                'message' => "Refund operation is already terminal ({$latestStatus}); preserving the recorded result.",
            ];
        }

        return $dispatchResult;
    }

    /**
     * Confirm verified refund execution (called from Webhook or Scheduled Reconciler with terminal evidence).
     * Enforces double-entry ledger symmetry and updates return cases/orders.
     */
    public function finalizeRefundConfirmation(
        FinancialOperation $operation,
        array $gatewayEvidence,
        ?string $expectedRefundClaimToken = null,
        ?int $webhookEventId = null,
        ?int $webhookAttempt = null
    ): array {
        return DB::transaction(function () use ($operation, $gatewayEvidence, $expectedRefundClaimToken, $webhookEventId, $webhookAttempt) {
            // When invoked by a webhook, fence the financial side effect in the SAME
            // transaction as the inbox ownership check. A preflight check alone races
            // with lease expiry and a newer worker reclaiming the event.
            if ($webhookEventId !== null || $webhookAttempt !== null) {
                if ($webhookEventId === null || $webhookAttempt === null) {
                    throw new \\InvalidArgumentException('Webhook fencing requires both event ID and attempt generation.');
                }

                $claimedEvent = \\App\\Models\\ProcessedWebhookEvent::where('id', $webhookEventId)
                    ->lockForUpdate()
                    ->first();

                if (!$claimedEvent
                    || $claimedEvent->status !== 'processing'
                    || (int) $claimedEvent->attempts !== $webhookAttempt) {
                    return [
                        'success' => false,
                        'protected' => true,
                        'idempotent' => true,
                        'stale_webhook_claim' => true,
                        'operation' => $operation->fresh(),
                        'message' => 'Webhook claim generation is no longer current; financial side effect blocked.',
                        'code' => 'STALE_WEBHOOK_CLAIM',
                    ];
                }
            }

            $lockedOp = FinancialOperation::where('id', $operation->id)->lockForUpdate()->firstOrFail();

            if ($lockedOp->status === 'confirmed') {
                return [
                    'success' => true,
                    'message' => 'Operation is already confirmed.',
                    'operation' => $lockedOp,
                ];
            }

            if (in_array($lockedOp->status, ['failed', 'reversed'], true)) {
                return [
                    'success' => false,
                    'protected' => true,
                    'idempotent' => true,
                    'operation' => $lockedOp,
                    'message' => "Refund operation is already terminal ({$lockedOp->status}); late success requires manual reconciliation.",
                ];
            }

            // Verify gateway evidence has terminal status
            $gwStatus = strtolower($gatewayEvidence['status'] ?? $gatewayEvidence['data']['status'] ?? '');
            if (!in_array($gwStatus, ['processed', 'success', 'completed'], true)) {
                throw new \InvalidArgumentException("Cannot confirm refund without verified terminal gateway status. Received: '{$gwStatus}'");
            }

            // Central financial-evidence fence: every caller (webhook, reconciler,
            // or synchronous gateway response) must reject conflicting details before
            // committing reservations or writing journal entries. Some gateway responses
            // omit these fields, so validate them when present rather than inventing them.
            $evidenceData = is_array($gatewayEvidence['data'] ?? null) ? $gatewayEvidence['data'] : [];
            $evidenceAmount = $gatewayEvidence['amount'] ?? ($evidenceData['amount'] ?? null);
            $evidenceCurrency = $gatewayEvidence['currency'] ?? ($evidenceData['currency'] ?? null);

            $evidenceAmountIsWholeKobo = is_int($evidenceAmount)
                || (is_string($evidenceAmount) && preg_match('/^\\d+$/D', $evidenceAmount) === 1);

            if ($evidenceAmount !== null
                && (!$evidenceAmountIsWholeKobo || (int) $evidenceAmount !== (int) $lockedOp->amount_kobo)) {
                return [
                    'success' => false,
                    'protected' => true,
                    'idempotent' => true,
                    'operation' => $lockedOp,
                    'message' => 'Refund amount evidence conflicts with the financial operation; manual reconciliation required.',
                    'code' => 'REFUND_AMOUNT_MISMATCH',
                ];
            }

            if ($evidenceCurrency !== null
                && strtoupper(trim((string) $evidenceCurrency)) !== strtoupper((string) $lockedOp->currency)) {
                return [
                    'success' => false,
                    'protected' => true,
                    'idempotent' => true,
                    'operation' => $lockedOp,
                    'message' => 'Refund currency evidence conflicts with the financial operation; manual reconciliation required.',
                    'code' => 'REFUND_CURRENCY_MISMATCH',
                ];
            }

            $orderId = $lockedOp->metadata['order_id'] ?? null;
            $order = $orderId ? Order::where('id', $orderId)->lockForUpdate()->first() : null;

            // A scheduled reconciler may only finalize while it still owns the exact
            // order claim. Webhook-driven finalization omits this optional worker token.
            if ($expectedRefundClaimToken !== null
                && (!$order || $order->refund_reconciliation_token !== $expectedRefundClaimToken)) {
                return [
                    'success' => false,
                    'protected' => true,
                    'idempotent' => true,
                    'operation' => $lockedOp,
                    'message' => 'Refund reconciliation lease was lost before finalization.',
                ];
            }

            // Commit reservation only after the claim fence has been checked under lock.
            FinancialReservation::where('financial_operation_id', $lockedOp->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'committed',
                    'committed_at' => Carbon::now(),
                ]);

            $lockedOp->update([
                'status' => 'confirmed',
                'confirmed_at' => Carbon::now(),
            ]);

            if ($order) {
                // Terminal webhook confirmation revokes any stale reconciler lease too.
                // For scheduled workers, the ownership check above ensures only the
                // current lease holder can finalize and clear its claim.
                $order->refund_reconciliation_token = null;
                $order->refund_reconciliation_claimed_at = null;
                $order->save();
            }

            $returnCaseId = $lockedOp->metadata['return_case_id'] ?? null;
            $returnCase = $returnCaseId ? ReturnCase::where('id', $returnCaseId)->lockForUpdate()->first() : null;

            $disburseRef = 'REFUND-CONF-' . $lockedOp->operation_reference;
            $gwRef = $gatewayEvidence['refund_reference'] ?? ($gatewayEvidence['data']['reference'] ?? ($gatewayEvidence['data']['id'] ?? $disburseRef));

            // Write Balanced Double-Entry Journal Entries
            // Debit: Escrow Holding / Vendor Payable / Platform Margin
            // Credit: Cash in Escrow (Customer Outflow)
            $refundAmountNaira = $lockedOp->amount_naira;

            // Reversal of instant wallet credit if return case had wallet credit
            if ($returnCase) {
                $walletCredit = CustomerWalletCredit::where('return_case_id', $returnCase->id)
                    ->where('status', 'available')
                    ->first();
                if ($walletCredit) {
                    $walletCredit->update([
                        'status' => 'reversed_for_cash_refund',
                        'notes' => ($walletCredit->notes ? $walletCredit->notes . ' | ' : '') . 'Reversed upon verified gateway cash refund disbursement.',
                    ]);
                }

                $merchandiseRefund = $returnCase->total_refundable_amount;
                $deliveryFeeRefund = $returnCase->refund_delivery_fee && $order ? (string)$order->delivery_fee : '0.00';

                // 90/5/5 Reversal Splits
                $vendorNetDebit = bcmul($merchandiseRefund, '0.9000', 2);
                $customerCashbackDebit = bcmul($merchandiseRefund, '0.0500', 2);
                $platformMarginDebit = bcmul($merchandiseRefund, '0.0500', 2);

                $calcSum = bcadd(bcadd($vendorNetDebit, $customerCashbackDebit, 2), $platformMarginDebit, 2);
                if (bccomp($calcSum, $merchandiseRefund, 2) !== 0) {
                    $diff = bcsub($merchandiseRefund, $calcSum, 2);
                    $vendorNetDebit = bcadd($vendorNetDebit, $diff, 2);
                }

                // Debit Vendor Payable
                FinancialJournalEntry::create([
                    'order_id' => $order?->id,
                    'financial_operation_id' => $lockedOp->id,
                    'transaction_reference' => $disburseRef,
                    'event_id' => 'EVT-RFD-' . $lockedOp->id . '-VND',
                    'idempotency_key' => 'REFUND-' . $lockedOp->id . '-VENDOR-DEBIT',
                    'beneficiary_type' => VendorProfile::class,
                    'beneficiary_id' => $order?->vendor_id,
                    'account_name' => 'vendor_payable',
                    'entry_type' => 'debit',
                    'amount' => $vendorNetDebit,
                    'currency' => 'NGN',
                    'description' => "Merchant payable reversed for approved return item: Case #{$returnCase->case_number}",
                ]);

                // Debit Cashback Liability
                FinancialJournalEntry::create([
                    'order_id' => $order?->id,
                    'financial_operation_id' => $lockedOp->id,
                    'transaction_reference' => $disburseRef,
                    'event_id' => 'EVT-RFD-' . $lockedOp->id . '-CBK',
                    'idempotency_key' => 'REFUND-' . $lockedOp->id . '-CASHBACK-DEBIT',
                    'beneficiary_type' => null,
                    'beneficiary_id' => null,
                    'account_name' => 'cashback_liability',
                    'entry_type' => 'debit',
                    'amount' => $customerCashbackDebit,
                    'currency' => 'NGN',
                    'description' => "Customer 5% cashback liability clawed back for returned item: Case #{$returnCase->case_number}",
                ]);

                // Debit Platform Margin
                FinancialJournalEntry::create([
                    'order_id' => $order?->id,
                    'financial_operation_id' => $lockedOp->id,
                    'transaction_reference' => $disburseRef,
                    'event_id' => 'EVT-RFD-' . $lockedOp->id . '-REV',
                    'idempotency_key' => 'REFUND-' . $lockedOp->id . '-PLATFORM-DEBIT',
                    'beneficiary_type' => null,
                    'beneficiary_id' => null,
                    'account_name' => 'platform_revenue',
                    'entry_type' => 'debit',
                    'amount' => $platformMarginDebit,
                    'currency' => 'NGN',
                    'description' => "Platform 5% margin reversed for return Case #{$returnCase->case_number}",
                ]);

                // Credit Cash Escrow Outflow
                FinancialJournalEntry::create([
                    'order_id' => $order?->id,
                    'financial_operation_id' => $lockedOp->id,
                    'transaction_reference' => $disburseRef,
                    'event_id' => 'EVT-RFD-' . $lockedOp->id . '-OUT',
                    'idempotency_key' => 'REFUND-' . $lockedOp->id . '-CASH-OUTFLOW',
                    'beneficiary_type' => null,
                    'beneficiary_id' => null,
                    'account_name' => 'cash_in_escrow',
                    'entry_type' => 'credit',
                    'amount' => $refundAmountNaira,
                    'currency' => 'NGN',
                    'description' => "Cash refund disbursed to customer for Return Case #{$returnCase->case_number} via Paystack ({$gwRef})",
                ]);

                $returnCase->update([
                    'status' => 'refunded',
                    'refunded_amount' => $refundAmountNaira,
                    'paystack_refund_id' => $gwRef,
                    'refunded_at' => Carbon::now(),
                ]);
            } else {
                // Order cancellation / out of stock full refund
                FinancialJournalEntry::create([
                    'order_id' => $order?->id,
                    'financial_operation_id' => $lockedOp->id,
                    'transaction_reference' => $disburseRef,
                    'event_id' => 'EVT-RFD-' . $lockedOp->id . '-ESC',
                    'idempotency_key' => 'REFUND-' . $lockedOp->id . '-CASH-OUTFLOW',
                    'beneficiary_type' => null,
                    'beneficiary_id' => null,
                    'account_name' => 'cash_in_escrow',
                    'entry_type' => 'credit',
                    'amount' => $refundAmountNaira,
                    'currency' => 'NGN',
                    'description' => "Customer refund settled for cancelled Order #{$order?->order_number} via Paystack ({$gwRef})",
                ]);
            }

            if ($order) {
                // The order-level flag summarizes all partial refunds, not just this
                // operation. One successful partial refund must not hide another
                // outstanding refund or an approved return awaiting refund.
                $hasOutstandingRefund = FinancialOperation::where('operation_type', 'customer_refund')
                    ->whereJsonContains('metadata->order_id', $order->id)
                    ->whereNotIn('status', ['confirmed', 'failed', 'reversed'])
                    ->exists();
                $hasPendingReturnCase = ReturnCase::where('order_id', $order->id)
                    ->whereIn('status', ['refund_pending', 'refund_approved'])
                    ->exists();

                $order->update([
                    'paystack_refund_reference' => $gwRef,
                    'paystack_refund_status' => ($hasOutstandingRefund || $hasPendingReturnCase)
                        ? 'pending'
                        : 'completed',
                ]);
            }

            return [
                'success' => true,
                'status' => 'confirmed',
                'message' => "Refund confirmed and settled successfully.",
            ];
        });
    }

    /**
     * Acquire a lease token on a financial operation for distributed worker safety.
     * Guarantees fencing against stale workers.
     */
    public function acquireLease(FinancialOperation $operation, int $durationSeconds = 300): ?string
    {
        $token = 'LEASE-' . Str::random(24);
        $updated = FinancialOperation::where('id', $operation->id)
            ->where(function ($q) {
                $q->whereNull('lease_token')
                  ->orWhereNull('lease_expires_at')
                  ->orWhere('lease_expires_at', '<', Carbon::now());
            })
            ->update([
                'lease_token' => $token,
                'lease_expires_at' => Carbon::now()->addSeconds($durationSeconds),
            ]);

        return $updated > 0 ? $token : null;
    }

    /**
     * Release lease token upon clean worker completion.
     */
    public function releaseLease(FinancialOperation $operation, string $token): bool
    {
        return FinancialOperation::where('id', $operation->id)
            ->where('lease_token', $token)
            ->update([
                'lease_token' => null,
                'lease_expires_at' => null,
            ]) > 0;
    }
}