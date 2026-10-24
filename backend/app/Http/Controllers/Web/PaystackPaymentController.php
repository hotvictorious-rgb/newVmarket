<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderSettlementService;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaystackPaymentController extends Controller
{
    public function __construct(
        protected PaystackService $paystackService,
        protected OrderSettlementService $settlementService,
        protected \App\Services\PayoutFinalizationService $finalizationService
    ) {}

    /**
     * Paystack Browser Callback (Customer redirected back after entering card/bank details on Paystack checkout).
     */
    public function handleCallback(Request $request): RedirectResponse
    {
        $reference = (string) ($request->query('reference') ?? $request->query('trxref') ?? '');

        if (empty($reference)) {
            return redirect()->route('storefront')->with('error', 'Missing transaction reference from payment gateway.');
        }

        $order = Order::where('payment_reference', $reference)->first();

        if (!$order) {
            Log::warning("Paystack callback: Order not found for reference '{$reference}'.");
            return redirect()->route('storefront')->with('error', 'Order not found for transaction reference.');
        }

        // Customer Identity / IDOR Verification
        $actorUser = Auth::user();
        if ($actorUser && $order->customer_id && !$actorUser->isSuperAdmin() && (int)$order->customer_id !== (int)$actorUser->id) {
            Log::critical("Paystack callback IDOR attempt: User #{$actorUser->id} tried to settle order #{$order->id} belonging to customer #{$order->customer_id}.");
            return redirect()->route('storefront')->with('error', 'Unauthorized: Order does not belong to your account.');
        }

        // Authorize guest tracking token only if session originator or matching authenticated customer (Finding 11)
        $redirectParams = ['order_number' => $order->order_number];
        if (session('checkout_order_number') === $order->order_number || (Auth::check() && (int)Auth::id() === (int)$order->customer_id)) {
            $redirectParams['token'] = $order->guest_access_token;
        }

        // Server-to-server authoritative verification against Paystack
        $verification = $this->paystackService->verifyTransaction($reference, $order);

        if (!$verification['success']) {
            Log::error("Paystack callback verification failed for reference '{$reference}': " . ($verification['message'] ?? ''));
            return redirect()->route('order.status', $redirectParams)
                ->with('error', 'Payment verification failed: ' . ($verification['message'] ?? 'Payment could not be verified.'));
        }

        // Authoritative Settlement Core Execution under Pessimistic Lock
        $settlement = $this->settlementService->settlePaystackOrder(
            $order,
            $verification['data'],
            $actorUser,
            'paystack_callback'
        );

        if (!$settlement['success']) {
            Log::error("Paystack callback settlement failed for order #{$order->id}: " . ($settlement['message'] ?? ''));
            return redirect()->route('order.status', $redirectParams)
                ->with('error', 'Payment settlement error: ' . $settlement['message']);
        }

        if (($settlement['status'] ?? '') === 'REFUND_PENDING' || $order->fresh()->vendor_settlement_status === 'refund_pending') {
            $refundMessage = 'Your payment was verified after the order had been cancelled and inventory was unavailable. An administrative refund request has been created and is pending Super Admin authorization under the Zero-Automated-Outflow Law.';
            if (!empty($settlement['trigger_gateway_refund'])) {
                try {
                    $finOpService = app(\App\Services\FinancialOperationService::class);
                    $existingOp = \App\Models\FinancialOperation::where('operation_type', 'customer_refund')
                        ->whereJsonContains('metadata->order_id', $order->id)
                        ->whereIn('status', ['pending', 'initiated', 'confirmed'])
                        ->first();

                    if (!$existingOp) {
                        $finOpService->createRefundOperation(
                            $order,
                            $settlement['refund_amount'] ?? (string)$order->grand_total,
                            "Refund pending administrative authorization for cancelled/depleted Order #{$order->order_number}",
                            null,
                            null,
                            ['type' => 'pending_admin_review_late_payment']
                        );
                        Log::info("Pending late-payment refund operation created for Order #{$order->id}; awaiting human Super Admin authorization.");
                    }
                } catch (\Throwable $e) {
                    Log::error("Failed to record pending refund operation in callback for Order #{$order->id}: " . $e->getMessage());
                }
            }

            return redirect()->route('order.status', $redirectParams)->with('warning', $refundMessage);
        }

        $successMsg = ($settlement['status'] === 'ALREADY_PAID')
            ? 'Payment previously verified and confirmed.'
            : 'Payment verified successfully! Your order is now active.';

        return redirect()->route('order.status', $redirectParams)
            ->with('status', $successMsg);
    }

    /**
     * Authoritatively resolve and validate payout identity, amounts, and currency for webhook reconciliation.
     * Prevents dual matches, cross-matched references, and under/over-disbursement attacks (Audit Finding P0).
     */
    /**
     * Stale Worker Fencing Guard (Finding P1):
     * Atomically update ProcessedWebhookEvent ONLY IF the attempts counter matches the claimed generation.
     */
    private function updateProcessedEvent(\App\Models\ProcessedWebhookEvent $event, array $attributes): bool
    {
        $updated = \App\Models\ProcessedWebhookEvent::where('id', $event->id)
            ->where('attempts', $event->attempts)
            ->update($attributes);

        if (!$updated) {
            Log::warning("Fencing Guard: Stale worker for webhook event #{$event->id} fenced out (attempts bumped by newer worker).", ['event_id' => $event->event_id, 'claimed_attempts' => $event->attempts]);
        }

        return (bool)$updated;
    }

    private function resolveAndValidatePayout(array $tData, \App\Models\ProcessedWebhookEvent $processedEvent, bool $requireFinancialFields = false): ?array
    {
        $tRef = trim((string)($tData['reference'] ?? ''));
        $tCode = trim((string)($tData['transfer_code'] ?? ''));

        if (empty($tRef) && empty($tCode)) {
            return null;
        }

        $isVendorPrefix = str_starts_with($tRef, 'VND-') || str_contains($tRef, 'VND-PAYOUT');
        $isLogisticsPrefix = str_starts_with($tRef, 'FLEET-') || str_contains($tRef, 'FLEET-PAYOUT');

        $vendorPayout = null;
        $logisticsPayout = null;

        if ($isVendorPrefix) {
            $vendorPayout = \App\Models\VendorPayoutRequest::where(function ($q) use ($tRef, $tCode) {
                if (!empty($tRef)) {
                    $q->where('transfer_reference', $tRef)->orWhere('settlement_proof_path', 'paystack_transfer_ref:' . $tRef);
                }
                if (!empty($tCode)) {
                    $q->orWhere('paystack_transfer_code', $tCode)->orWhere('settlement_proof_path', 'paystack_transfer:' . $tCode);
                }
            })->first();
        } elseif ($isLogisticsPrefix) {
            $logisticsPayout = \App\Models\LogisticsPayoutRequest::where(function ($q) use ($tRef, $tCode) {
                if (!empty($tRef)) {
                    $q->where('transfer_reference', $tRef)->orWhere('settlement_proof_path', 'paystack_transfer_ref:' . $tRef);
                }
                if (!empty($tCode)) {
                    $q->orWhere('paystack_transfer_code', $tCode)->orWhere('settlement_proof_path', 'paystack_transfer:' . $tCode);
                }
            })->first();
        } else {
            // Search both, but strictly forbid ambiguous dual matches
            $vendorPayout = \App\Models\VendorPayoutRequest::where(function ($q) use ($tRef, $tCode) {
                if (!empty($tRef)) {
                    $q->where('transfer_reference', $tRef)->orWhere('settlement_proof_path', 'paystack_transfer_ref:' . $tRef);
                }
                if (!empty($tCode)) {
                    $q->orWhere('paystack_transfer_code', $tCode)->orWhere('settlement_proof_path', 'paystack_transfer:' . $tCode);
                }
            })->first();

            $logisticsPayout = \App\Models\LogisticsPayoutRequest::where(function ($q) use ($tRef, $tCode) {
                if (!empty($tRef)) {
                    $q->where('transfer_reference', $tRef)->orWhere('settlement_proof_path', 'paystack_transfer_ref:' . $tRef);
                }
                if (!empty($tCode)) {
                    $q->orWhere('paystack_transfer_code', $tCode)->orWhere('settlement_proof_path', 'paystack_transfer:' . $tCode);
                }
            })->first();

            if ($vendorPayout && $logisticsPayout) {
                Log::critical("Ambiguous transfer webhook rejected: reference '{$tRef}' or code '{$tCode}' matches both VendorPayoutRequest #{$vendorPayout->id} and LogisticsPayoutRequest #{$logisticsPayout->id}.");
                $this->updateProcessedEvent($processedEvent, [
                    'status' => 'failed',
                    'error_reason' => "AMBIGUOUS_DUAL_MATCH: Matched both vendor #{$vendorPayout->id} and logistics #{$logisticsPayout->id}.",
                    'processed_at' => Carbon::now(),
                ]);
                return ['error' => 'Ambiguous webhook identity matching both vendor and logistics payouts'];
            }
        }

        $targetType = $vendorPayout ? 'vendor' : ($logisticsPayout ? 'logistics' : null);
        $payout = $vendorPayout ?? $logisticsPayout;

        if (!$payout) {
            return null;
        }

        // Identifier Cross-Validation Guard: If payout already has both reference and code, ensure webhook does not conflict
        if (!empty($payout->transfer_reference) && !empty($tRef) && $payout->transfer_reference !== $tRef && $payout->settlement_proof_path !== 'paystack_transfer_ref:' . $tRef) {
            Log::critical("Paystack Webhook Reference Conflict! Payout #{$payout->id} ref '{$payout->transfer_reference}' conflicts with webhook ref '{$tRef}'.");
            $this->updateProcessedEvent($processedEvent, [
                'status' => 'failed',
                'error_reason' => "REFERENCE_CONFLICT: Payout #{$payout->id} expected ref '{$payout->transfer_reference}', got '{$tRef}'.",
                'processed_at' => Carbon::now(),
            ]);
            return ['error' => 'Conflicting transfer reference'];
        }

        // Transfer Code Cross-Validation Guard (Finding P1)
        if (!empty($payout->paystack_transfer_code) && !empty($tCode) && $payout->paystack_transfer_code !== $tCode) {
            Log::critical("Paystack Webhook Transfer Code Conflict! Payout #{$payout->id} code '{$payout->paystack_transfer_code}' conflicts with webhook code '{$tCode}'.");
            $this->updateProcessedEvent($processedEvent, [
                'status' => 'failed',
                'error_reason' => "TRANSFER_CODE_CONFLICT: Payout #{$payout->id} expected code '{$payout->paystack_transfer_code}', got '{$tCode}'.",
                'processed_at' => Carbon::now(),
            ]);
            return ['error' => 'Conflicting transfer code'];
        }

        // Amount Invariant Check: Paystack amounts are integer kobo. Do not
        // cast malformed decimals/floats/strings before validating their representation.
        if (isset($tData['amount'])) {
            $amountIsWholeKobo = is_int($tData['amount'])
                || (is_string($tData['amount']) && preg_match('/^\\d+$/D', $tData['amount']) === 1);
            if (!$amountIsWholeKobo) {
                Log::critical("Paystack Webhook Malformed Transfer Amount! Payout #{$payout->id} amount must be integer kobo.", [
                    'event_id' => $processedEvent->event_id,
                    'received_amount' => $tData['amount'],
                ]);
                $this->updateProcessedEvent($processedEvent, [
                    'status' => 'failed',
                    'error_reason' => "MALFORMED_AMOUNT: Payout #{$payout->id} amount must be a whole integer number of kobo.",
                    'processed_at' => Carbon::now(),
                ]);
                return ['error' => 'Malformed payout amount'];
            }

            $webhookKobo = (int)$tData['amount'];
            $expectedKobo = (int)bcmul((string)$payout->amount, '100', 0);
            if ($webhookKobo !== $expectedKobo) {
                Log::critical("Paystack Webhook Amount Mismatch! Payout #{$payout->id} expected {$expectedKobo} kobo, received {$webhookKobo} kobo.");
                $this->updateProcessedEvent($processedEvent, [
                    'status' => 'failed',
                    'error_reason' => "AMOUNT_MISMATCH: Payout #{$payout->id} expected {$expectedKobo} kobo, received {$webhookKobo} kobo.",
                    'processed_at' => Carbon::now(),
                ]);
                return ['error' => 'Payout amount mismatch'];
            }
        } elseif ($requireFinancialFields) {
            Log::critical("Paystack Webhook Missing Amount! Payout #{$payout->id} webhook payload omitted amount.");
            $this->updateProcessedEvent($processedEvent, [
                'status' => 'failed',
                'error_reason' => "MISSING_AMOUNT: Webhook transfer payload omitted mandatory amount.",
                'processed_at' => Carbon::now(),
            ]);
            return ['error' => 'Missing transfer amount'];
        }

        // Successful transfers must carry explicit amount and currency; never infer either.
        if ($requireFinancialFields && empty($tData['currency'])) {
            Log::critical("Paystack Webhook Missing Currency! Payout #{$payout->id} webhook payload omitted currency.");
            $this->updateProcessedEvent($processedEvent, [
                'status' => 'failed',
                'error_reason' => "MISSING_CURRENCY: Webhook transfer payload omitted mandatory currency.",
                'processed_at' => Carbon::now(),
            ]);
            return ['error' => 'Missing transfer currency'];
        }

        if (isset($tData['currency'])) {
            $currency = strtoupper(trim((string)$tData['currency']));
            if ($currency !== 'NGN') {
                Log::critical("Paystack Webhook Currency Mismatch! Expected NGN, received {$currency}.", [$currency]);
                $this->updateProcessedEvent($processedEvent, [
                    'status' => 'failed',
                    'error_reason' => "CURRENCY_MISMATCH: Expected NGN, received {$currency}.",
                    'processed_at' => Carbon::now(),
                ]);
                return ['error' => 'Payout currency mismatch'];
            }
        }

        return [
            'type' => $targetType,
            'payout' => $payout,
        ];
    }

    /**
     * Paystack Asynchronous Webhook with Strict HMAC-SHA512 Cryptographic Signature Verification.
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $signature = (string) $request->header('x-paystack-signature', '');
        $payload = $request->getContent();

        // 1. Strict Cryptographic HMAC-SHA512 Verification
        if (!$this->paystackService->verifyWebhookSignature($signature, $payload)) {
            Log::warning('Paystack Webhook: Invalid or forged cryptographic signature.', [
                'ip' => $request->ip(),
                'has_signature' => !empty($signature),
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Invalid cryptographic signature.',
            ], 401);
        }

        $event = json_decode($payload, true);
        if (!$event || !isset($event['event'])) {
            return response()->json([
                'status' => false,
                'message' => 'Malformed webhook payload.',
            ], 400);
        }

        // Authoritative Webhook Inbox State Machine (Audit P0 Resolution)
        // Transactional Inbox prevents permanent event skipping on server crash
        $eventHash = hash('sha256', (string)$request->getContent());
        $eventId = (string)($event['id'] ?? ($event['event'] . '_' . ($event['data']['reference'] ?? ($event['data']['transfer_code'] ?? $eventHash))));

        // Claim the inbox row transactionally. If a competing first insert wins the
        // unique event_id constraint, allow this transaction to roll back before recovery.
        try {
            // Authoritative Webhook Inbox State Machine (Audit P0 & P1 Concurrency-Safe Resolution)
            // Transactional Row-Level Lock prevents concurrent race conditions and permanent event skipping
            $claimResult = \Illuminate\Support\Facades\DB::transaction(function () use ($eventId, $event, $payload, $eventHash) {
            $existingEvent = \App\Models\ProcessedWebhookEvent::where('event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if (!$existingEvent && \Illuminate\Support\Facades\Schema::hasTable('archived_webhook_events')) {
                $archivedEvent = \App\Models\ArchivedWebhookEvent::where('event_id', $eventId)->first();
                if ($archivedEvent) {
                    $storedHash = $archivedEvent->payload_hash;
                    if (empty($storedHash) && is_string($archivedEvent->payload)) {
                        $storedHash = hash('sha256', $archivedEvent->payload);
                    }
                    if (!is_string($storedHash) || !hash_equals($storedHash, $eventHash)) {
                        Log::critical('Paystack webhook event ID reused with a different payload (archived record).', [
                            'event_id' => $eventId,
                            'stored_hash' => $storedHash,
                            'incoming_hash' => $eventHash,
                        ]);
                        return ['action' => 'conflict', 'message' => 'Event ID already exists with a different payload.'];
                    }
                    return ['action' => 'completed', 'message' => 'Event already processed (archived).'];
                }
            }

            if ($existingEvent) {
                // An event ID is an immutable inbox identity. Never let a different,
                // correctly signed payload reuse it, regardless of current status.
                // Older rows may lack payload_hash, so derive it from the stored body.
                $storedHash = $existingEvent->payload_hash;
                if (empty($storedHash) && is_string($existingEvent->payload)) {
                    $storedHash = hash('sha256', $existingEvent->payload);
                }
                if (!is_string($storedHash) || !hash_equals($storedHash, $eventHash)) {
                    Log::critical('Paystack webhook event ID reused with a different payload.', [
                        'event_id' => $eventId,
                        'stored_hash' => $storedHash,
                        'incoming_hash' => $eventHash,
                        'stored_status' => $existingEvent->status,
                    ]);
                    return ['action' => 'conflict', 'message' => 'Event ID already exists with a different payload.'];
                }

                if ($existingEvent->status === 'completed') {
                    return ['action' => 'completed', 'message' => 'Event already processed.'];
                }
                if ($existingEvent->status === 'processing') {
                    // If in-flight by a live worker (< 60 seconds ago), signal retry backoff
                    if ($existingEvent->updated_at && $existingEvent->updated_at->diffInSeconds(\Carbon\Carbon::now()) < 60) {
                        return ['action' => 'throttled', 'message' => 'Event is currently being processed.'];
                    }
                    // Worker timed out/crashed (> 60s): atomically claim ownership
                    $existingEvent->increment('attempts');
                    $existingEvent->status = 'processing';
                    $existingEvent->payload = $payload;
                    $existingEvent->payload_hash = $eventHash;
                    $existingEvent->updated_at = \Carbon\Carbon::now();
                    $existingEvent->save();
                    return ['action' => 'process', 'event' => $existingEvent];
                }
                if ($existingEvent->status === 'failed') {
                    // Previous attempt failed, atomically retry the identical payload.
                    $existingEvent->increment('attempts');
                    $existingEvent->status = 'processing';
                    $existingEvent->payload = $payload;
                    $existingEvent->payload_hash = $eventHash;
                    $existingEvent->updated_at = \Carbon\Carbon::now();
                    $existingEvent->save();
                    return ['action' => 'process', 'event' => $existingEvent];
                }
                if ($existingEvent->status === 'unmatched') {
                    // An operator may have repaired the missing/ambiguous business record.
                    // Reprocess only the exact original payload; preserve attempts and inbox identity.
                    $existingEvent->increment('attempts');
                    $existingEvent->status = 'processing';
                    $existingEvent->error_reason = null;
                    $existingEvent->processed_at = null;
                    $existingEvent->payload = $payload;
                    $existingEvent->payload_hash = $eventHash;
                    $existingEvent->updated_at = \Carbon\Carbon::now();
                    $existingEvent->save();
                    return ['action' => 'process', 'event' => $existingEvent];
                }
            }

            // First-time arrival: insert with status = 'processing' and stored payload.
            // A concurrent insert may win the unique event_id constraint; let that exception
            // roll back this transaction before attempting to read the winning row.
            $newEvent = \App\Models\ProcessedWebhookEvent::create([
                'event_id' => $eventId,
                'event_type' => (string)($event['event'] ?? 'unknown'),
                'payload_hash' => $eventHash,
                'payload' => $payload,
                'status' => 'processing',
                'attempts' => 1,
            ]);
            return ['action' => 'process', 'event' => $newEvent];
        });
        } catch (\Illuminate\Database\QueryException $e) {
            // Recover only from a unique-constraint race. Other database errors must
            // propagate instead of being disguised as a duplicate webhook delivery.
            $sqlState = (string) $e->getCode();
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            $isUniqueViolation = $sqlState === '23505'
                || ($sqlState === '23000' && in_array($driverCode, [19, 1062], true));
            if (!$isUniqueViolation) {
                throw $e;
            }

            // PostgreSQL marks a transaction as failed after a constraint violation.
            // This catch is deliberately outside DB::transaction so the lookup runs
            // only after Laravel has rolled back the failed transaction.
            $existing = \App\Models\ProcessedWebhookEvent::where('event_id', $eventId)->first();
            if (!$existing) {
                throw $e;
            }

            $existingHash = $existing->payload_hash;
            if (empty($existingHash) && is_string($existing->payload)) {
                $existingHash = hash('sha256', $existing->payload);
            }
            if (!is_string($existingHash) || !hash_equals($existingHash, $eventHash)) {
                Log::critical('Paystack webhook event ID collision during inbox insert.', [
                    'event_id' => $eventId,
                    'stored_hash' => $existingHash,
                    'incoming_hash' => $eventHash,
                ]);
                $claimResult = ['action' => 'conflict', 'message' => 'Event ID already exists with a different payload.'];
            } elseif ($existing->status === 'completed') {
                $claimResult = ['action' => 'completed', 'message' => 'Event already processed.'];
            } else {
                // The winner owns processing/retry decisions. Return retryable backoff
                // rather than claim or execute financial effects without its row lock.
                $claimResult = ['action' => 'throttled', 'message' => 'Event is currently being processed.'];
            }
        }
        if ($claimResult['action'] === 'completed') {
            return response()->json(['status' => true, 'message' => $claimResult['message']], 200);
        }
        if ($claimResult['action'] === 'throttled') {
            return response()->json(['status' => false, 'message' => $claimResult['message']], 429);
        }
        if ($claimResult['action'] === 'conflict') {
            return response()->json(['status' => false, 'message' => $claimResult['message']], 409);
        }

        $processedEvent = $claimResult['event'];

        // Handle Paystack Transfer Webhooks (Automated Fleet & Vendor Payout Reconciliation)
        if ($event['event'] === 'transfer.success') {
            $tData = $event['data'] ?? [];
            $tRef = (string)($tData['reference'] ?? '');
            $tCode = (string)($tData['transfer_code'] ?? '');
            Log::info("Paystack Webhook: Automated transfer success for reference: {$tRef}, code: {$tCode}");

            $resolved = $this->resolveAndValidatePayout($tData, $processedEvent, true);

            if (isset($resolved['error'])) {
                return response()->json(['status' => false, 'message' => $resolved['error']], 400);
            }

            if (!$resolved) {
                $this->updateProcessedEvent($processedEvent, [
                    'status' => 'unmatched',
                    'error_reason' => "No matching vendor or logistics payout request found for reference '{$tRef}' or transfer_code '{$tCode}'",
                    'processed_at' => Carbon::now(),
                ]);
                Log::warning("Paystack Webhook: Unmatched transfer.success event #{$processedEvent->id} for ref '{$tRef}', code '{$tCode}'");

                return response()->json(['status' => false, 'message' => 'Unmatched transfer.success event recorded for treasury audit.'], 200);
            }

            // Strictly finalize exactly ONE unambiguous payout entity with explicit verified event status.
            // Finalization rechecks the terminal state under a row lock to prevent delayed success
            // events from reopening payouts that were already reversed.
            $tDataVerified = array_merge(['status' => 'success'], $tData);
            if ($resolved['type'] === 'vendor') {
                $finalizationResult = $this->finalizationService->finalizeVendorPayout($resolved['payout'], $tDataVerified, null, null, (int) $processedEvent->id, (int) $processedEvent->attempts);
            } elseif ($resolved['type'] === 'logistics') {
                $finalizationResult = $this->finalizationService->finalizeLogisticsPayout($resolved['payout'], $tDataVerified, null, null, (int) $processedEvent->id, (int) $processedEvent->attempts);
            }

            if (!empty($finalizationResult['stale_webhook_claim'])) {
                return response()->json(['status' => false, 'message' => 'Webhook claim expired before payout finalization; retry/reconciliation required.'], 429);
            }
            if (!empty($finalizationResult['protected'])) {
                $this->updateProcessedEvent($processedEvent, [
                    'status' => 'unmatched',
                    'error_reason' => 'TERMINAL_PAYOUT_PROTECTED: Delayed success event cannot reopen a payout already marked failed/reversed.',
                    'processed_at' => Carbon::now(),
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Delayed success event recorded for audit; terminal failed payout was not reopened.',
                ], 200);
            }

            $this->updateProcessedEvent($processedEvent, [
                'status' => 'completed',
                'processed_at' => Carbon::now(),
            ]);

            return response()->json(['status' => true, 'message' => 'Transfer success confirmed and reconciled.'], 200);
        }

        if (in_array($event['event'], ['transfer.failed', 'transfer.reversed'], true)) {
            $tData = $event['data'] ?? [];
            $tRef = (string)($tData['reference'] ?? '');
            $tCode = (string)($tData['transfer_code'] ?? '');
            $reason = (string)($tData['reason'] ?? 'Bank reversal / recipient rejected');
            Log::warning("Paystack Webhook: Transfer {$event['event']} for ref '{$tRef}', code '{$tCode}'", $tData);

            $resolved = $this->resolveAndValidatePayout(
                $tData,
                $processedEvent,
                $event['event'] === 'transfer.reversed'
            );

            if (isset($resolved['error'])) {
                return response()->json(['status' => false, 'message' => $resolved['error']], 400);
            }

            if (!$resolved) {
                $this->updateProcessedEvent($processedEvent, [
                    'status' => 'unmatched',
                    'error_reason' => "No matching vendor or logistics payout request found for reference '{$tRef}' or transfer_code '{$tCode}'",
                    'processed_at' => Carbon::now(),
                ]);
                Log::warning("Paystack Webhook: Unmatched {$event['event']} event #{$processedEvent->id} for ref '{$tRef}', code '{$tCode}'");

                return response()->json(['status' => false, 'message' => "Unmatched {$event['event']} event recorded for treasury audit."], 200);
            }

            // A late transfer.failed notification must never undo a payout already confirmed successful.
            // A genuine transfer.reversed event remains eligible for the reversal path below.
            $resolvedPayout = $resolved['payout'];
            if (
                $event['event'] === 'transfer.failed'
                && strtolower((string)($resolvedPayout->gateway_status ?? '')) === 'success'
                && strtolower((string)($resolvedPayout->status ?? '')) === 'approved'
            ) {
                $this->updateProcessedEvent($processedEvent, [
                    'status' => 'unmatched',
                    'error_reason' => "LATE_TRANSFER_FAILED_AFTER_SUCCESS: Payout #{$resolvedPayout->id} is already confirmed successful; no reversal applied.",
                    'processed_at' => Carbon::now(),
                ]);
                Log::critical("Ignored late Paystack transfer.failed event for already successful payout #{$resolvedPayout->id}.");
                return response()->json([
                    'status' => true,
                    'message' => 'Late failure event recorded for audit; successful payout was not reversed.',
                ], 200);
            }

            // Only a genuine transfer.reversed event may undo a confirmed success.
            // The service rechecks state under a row lock to close the webhook/reconciler race.
            $allowSuccessfulReversal = $event['event'] === 'transfer.reversed';
            if ($resolved['type'] === 'logistics') {
                $reversalResult = $this->finalizationService->revertLogisticsPayout(
                    $resolved['payout'],
                    "Paystack transfer {$event['event']}: {$reason}",
                    null,
                    $allowSuccessfulReversal,
                    null,
                    (int) $processedEvent->id,
                    (int) $processedEvent->attempts
                );
            } elseif ($resolved['type'] === 'vendor') {
                $reversalResult = $this->finalizationService->revertVendorPayout(
                    $resolved['payout'],
                    "Paystack transfer {$event['event']}: {$reason}",
                    null,
                    $allowSuccessfulReversal,
                    null,
                    (int) $processedEvent->id,
                    (int) $processedEvent->attempts
                );
            }

            if (!empty($reversalResult['stale_webhook_claim'])) {
                return response()->json(['status' => false, 'message' => 'Webhook claim expired before payout reversal; retry/reconciliation required.'], 429);
            }
            if (!empty($reversalResult['protected'])) {
                $this->updateProcessedEvent($processedEvent, [
                    'status' => 'unmatched',
                    'error_reason' => 'PAYOUT_SUCCESS_PROTECTED: A confirmed successful payout was not reversed by a failure signal.',
                    'processed_at' => Carbon::now(),
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Failure signal recorded for audit; confirmed successful payout was protected.',
                ], 200);
            }

            $this->updateProcessedEvent($processedEvent, [
                'status' => 'completed',
                'processed_at' => Carbon::now(),
            ]);

            return response()->json(['status' => true, 'message' => "Transfer {$event['event']} processed and reversed successfully."], 200);
        }

        // Handle Paystack Refund Webhooks (Automated Customer Refund Reconciliation)
        if ($event['event'] === 'refund.processed') {
            $rData = $event['data'] ?? [];
            // Paystack refund webhook payloads use refund_reference (not reference).
            $rRef = (string)($rData['refund_reference'] ?? ($rData['reference'] ?? ($rData['id'] ?? '')));
            $transRef = (string)($rData['transaction_reference'] ?? '');
            Log::info("Paystack Webhook: Automated refund.processed for ref: {$rRef}, trans: {$transRef}");

            $finOpMatches = \App\Models\FinancialOperation::where('operation_type', 'customer_refund')
                ->where(function ($q) use ($rRef) {
                    if (!empty($rRef)) {
                        $q->where('operation_reference', $rRef)
                          ->orWhereJsonContains('metadata->refund_reference', $rRef)
                          ->orWhereHas('gatewayAttempts', function ($sub) use ($rRef) {
                              $sub->where('gateway_reference', $rRef);
                          });
                    } else {
                        // A transaction reference may have several partial refunds; it
                        // is not a unique refund-operation identifier.
                        $q->whereRaw('1 = 0');
                    }
                })
                ->get();

            // A gateway refund reference must resolve to exactly one operation.
            // Include confirmed operations so a duplicate success can be idempotent;
            // never pick an arbitrary pending operation if corrupted metadata or
            // gateway-attempt records make the reference ambiguous.
            $finOp = $finOpMatches->count() === 1 ? $finOpMatches->first() : null;

            if ($finOp) {
                // A signed webhook proves origin, not that this refund matches our ledger.
                // Bind the refund to the original payment when Paystack supplies its reference.
                $originalPaymentReference = (string) (($finOp->metadata ?? [])['payment_reference'] ?? '');
                $gatewayTransactionReference = (string) (
                    $rData['transaction_reference']
                    ?? (is_array($rData['transaction'] ?? null)
                        ? ($rData['transaction']['reference'] ?? '')
                        : (is_string($rData['transaction'] ?? null) ? $rData['transaction'] : ''))
                );
                if (
                    $originalPaymentReference !== ''
                    && $gatewayTransactionReference !== ''
                    && !hash_equals($originalPaymentReference, $gatewayTransactionReference)
                ) {
                    $this->updateProcessedEvent($processedEvent, [
                        'status' => 'failed',
                        'error_reason' => 'REFUND_TRANSACTION_REFERENCE_MISMATCH: Refund references a different original payment.',
                        'processed_at' => Carbon::now(),
                    ]);
                    Log::critical('Paystack refund webhook transaction reference mismatch; financial operation was not finalized.', [
                        'event_id' => $processedEvent->event_id,
                        'operation_id' => $finOp->id,
                        'expected_payment_reference' => $originalPaymentReference,
                        'received_transaction_reference' => $gatewayTransactionReference,
                    ]);
                    return response()->json(['status' => false, 'message' => 'Refund does not match the original payment reference.'], 400);
                }

                // Require explicit amount/currency and compare against the reserved operation
                // before any reservation, order, return-case, or journal state can be finalized.
                $webhookAmountIsWholeKobo = is_int($rData['amount'] ?? null)
                    || (is_string($rData['amount'] ?? null) && preg_match('/^\\d+$/D', $rData['amount']) === 1);
                if (!array_key_exists('amount', $rData) || !$webhookAmountIsWholeKobo) {
                    $this->updateProcessedEvent($processedEvent, [
                        'status' => 'failed',
                        'error_reason' => 'REFUND_MISSING_AMOUNT: Refund webhook must supply an integer amount in kobo.',
                        'processed_at' => Carbon::now(),
                    ]);
                    Log::critical('Paystack refund webhook missing or malformed amount; financial operation was not finalized.', [
                        'event_id' => $processedEvent->event_id,
                        'operation_id' => $finOp->id,
                    ]);
                    return response()->json(['status' => false, 'message' => 'Refund amount is required for reconciliation.'], 400);
                }

                $webhookAmountKobo = (int) $rData['amount'];
                if ($webhookAmountKobo !== (int) $finOp->amount_kobo) {
                    $this->updateProcessedEvent($processedEvent, [
                        'status' => 'failed',
                        'error_reason' => "REFUND_AMOUNT_MISMATCH: Operation #{$finOp->id} expects {$finOp->amount_kobo} kobo; webhook supplied {$webhookAmountKobo} kobo.",
                        'processed_at' => Carbon::now(),
                    ]);
                    Log::critical('Paystack refund webhook amount mismatch; financial operation was not finalized.', [
                        'event_id' => $processedEvent->event_id,
                        'operation_id' => $finOp->id,
                        'expected_amount_kobo' => $finOp->amount_kobo,
                        'received_amount_kobo' => $webhookAmountKobo,
                    ]);
                    return response()->json(['status' => false, 'message' => 'Refund amount does not match the reserved operation.'], 400);
                }

                if (strtoupper(trim((string) ($rData['currency'] ?? ''))) !== strtoupper((string) $finOp->currency)) {
                    $this->updateProcessedEvent($processedEvent, [
                        'status' => 'failed',
                        'error_reason' => 'REFUND_CURRENCY_MISMATCH: Refund webhook currency is missing or does not match the financial operation.',
                        'processed_at' => Carbon::now(),
                    ]);
                    Log::critical('Paystack refund webhook currency mismatch; financial operation was not finalized.', [
                        'event_id' => $processedEvent->event_id,
                        'operation_id' => $finOp->id,
                        'expected_currency' => $finOp->currency,
                        'received_currency' => $rData['currency'] ?? null,
                    ]);
                    return response()->json(['status' => false, 'message' => 'Refund currency does not match the reserved operation.'], 400);
                }

                $finalization = app(\App\Services\FinancialOperationService::class)
                    ->finalizeRefundConfirmation(
                        $finOp,
                        $rData,
                        null,
                        (int) $processedEvent->id,
                        (int) $processedEvent->attempts
                    );
                if (!empty($finalization['stale_webhook_claim'])) {
                    // Do not update the inbox here: a newer worker owns this generation.
                    return response()->json([
                        'status' => false,
                        'message' => 'Webhook claim expired before refund finalization; retry/reconciliation required.',
                    ], 429);
                }
                if (!empty($finalization['protected'])) {
                    $this->updateProcessedEvent($processedEvent, [
                        'status' => 'unmatched',
                        'error_reason' => 'REFUND_TERMINAL_STATE_CONFLICT: Late success cannot reopen a failed/reversed refund operation; manual reconciliation required.',
                        'processed_at' => Carbon::now(),
                    ]);
                    Log::critical('Paystack refund success conflicts with a terminal failed/reversed financial operation.', [
                        'event_id' => $processedEvent->event_id,
                        'operation_id' => $finOp->id,
                        'operation_status' => $finalization['operation']->status ?? $finOp->status,
                    ]);
                    return response()->json([
                        'status' => true,
                        'message' => 'Refund event received but conflicts with a terminal operation; flagged for manual reconciliation.',
                    ], 200);
                }
            } else {
                // Do not mark an order refunded merely because a signed webhook names its
                // payment reference. Without a matching FinancialOperation, we cannot
                // safely reconcile the refund against its reservation and journal entries.
                // Keep the event visible for reconciliation/manual review instead.
                $reason = $finOpMatches->isEmpty()
                    ? 'REFUND_OPERATION_NOT_FOUND: No matching customer refund operation for gateway event.'
                    : 'REFUND_OPERATION_AMBIGUOUS: Gateway refund reference matches multiple customer refund operations.';
                $this->updateProcessedEvent($processedEvent, [
                    'status' => 'unmatched',
                    'error_reason' => $reason,
                    'processed_at' => Carbon::now(),
                ]);

                Log::warning('Paystack refund.processed event did not resolve to exactly one FinancialOperation.', [
                    'event_id' => $processedEvent->event_id,
                    'refund_reference' => $rRef,
                    'transaction_reference' => $transRef,
                    'matches' => $finOpMatches->count(),
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Refund event received but not reconciled; flagged for review.',
                ], 200);
            }

            $this->updateProcessedEvent($processedEvent, [
                'status' => 'completed',
                'processed_at' => Carbon::now(),
            ]);

            return response()->json(['status' => true, 'message' => 'Refund processed webhook recorded and reconciled.'], 200);
        }

        if ($event['event'] === 'refund.failed') {
            $rData = $event['data'] ?? [];
            $rRef = (string)($rData['refund_reference'] ?? ($rData['reference'] ?? ($rData['id'] ?? '')));
            $transRef = (string)($rData['transaction_reference'] ?? '');

            // Match the specific refund operation by gateway refund reference. A payment
            // reference alone can identify multiple partial refunds, so it is not safe
            // evidence for failing or releasing any one financial operation.
            $finOpQuery = \App\Models\FinancialOperation::where('operation_type', 'customer_refund')
                ->where(function ($q) use ($rRef) {
                    if ($rRef !== '') {
                        $q->where('operation_reference', $rRef)
                          ->orWhereJsonContains('metadata->refund_reference', $rRef)
                          ->orWhereHas('gatewayAttempts', function ($sub) use ($rRef) {
                              $sub->where('gateway_reference', $rRef);
                          });
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                });

            $matches = $finOpQuery->get();
            if ($matches->count() !== 1) {
                $this->updateProcessedEvent($processedEvent, [
                    'status' => 'unmatched',
                    'error_reason' => 'REFUND_OPERATION_NOT_UNAMBIGUOUS: Failed refund event could not be tied to exactly one financial operation.',
                    'processed_at' => Carbon::now(),
                ]);
                Log::warning('Paystack refund.failed event could not be tied to exactly one financial operation.', [
                    'event_id' => $processedEvent->event_id,
                    'refund_reference' => $rRef,
                    'transaction_reference' => $transRef,
                    'matches' => $matches->count(),
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Refund failure received but not reconciled; flagged for review.',
                ], 200);
            }

            $finOp = $matches->first();
            $expectedPaymentReference = (string) (($finOp->metadata ?? [])['payment_reference'] ?? '');
            $reportedPaymentReference = (string) (
                $rData['transaction_reference']
                ?? (is_array($rData['transaction'] ?? null)
                    ? ($rData['transaction']['reference'] ?? '')
                    : (is_string($rData['transaction'] ?? null) ? $rData['transaction'] : ''))
            );
            if (
                $expectedPaymentReference !== ''
                && $reportedPaymentReference !== ''
                && !hash_equals($expectedPaymentReference, $reportedPaymentReference)
            ) {
                $this->updateProcessedEvent($processedEvent, [
                    'status' => 'unmatched',
                    'error_reason' => 'REFUND_TRANSACTION_REFERENCE_MISMATCH: Failed refund event references a different original payment.',
                    'processed_at' => Carbon::now(),
                ]);
                Log::critical('Paystack refund.failed transaction reference mismatch; financial operation was not changed.', [
                    'event_id' => $processedEvent->event_id,
                    'operation_id' => $finOp->id,
                    'expected_payment_reference' => $expectedPaymentReference,
                    'received_transaction_reference' => $reportedPaymentReference,
                ]);
                return response()->json([
                    'status' => true,
                    'message' => 'Refund failure received but original payment reference conflicts; flagged for review.',
                ], 200);
            }
            // Failed-refund payloads may omit amount/currency. If Paystack supplies
            // either field, treat it as evidence and reject conflicting or malformed values.
            // Do not release a reservation on a failure event that describes a different refund.
            if (array_key_exists('amount', $rData)) {
                $failedWebhookAmountIsWholeKobo = is_int($rData['amount'])
                    || (is_string($rData['amount']) && preg_match('/^\\d+$/D', $rData['amount']) === 1);
                if (!$failedWebhookAmountIsWholeKobo || (int) $rData['amount'] !== (int) $finOp->amount_kobo) {
                    $this->updateProcessedEvent($processedEvent, [
                        'status' => 'unmatched',
                        'error_reason' => 'REFUND_AMOUNT_MISMATCH: Failed refund event amount is invalid or differs from the financial operation.',
                        'processed_at' => Carbon::now(),
                    ]);
                    Log::critical('Paystack refund.failed amount mismatch; financial operation and reservation were not changed.', [
                        'event_id' => $processedEvent->event_id,
                        'operation_id' => $finOp->id,
                        'expected_amount_kobo' => $finOp->amount_kobo,
                        'received_amount_kobo' => $rData['amount'],
                    ]);
                    return response()->json([
                        'status' => true,
                        'message' => 'Refund failure amount conflicts with the financial operation; flagged for review.',
                    ], 200);
                }
            }

            if (array_key_exists('currency', $rData)
                && strtoupper(trim((string) $rData['currency'])) !== strtoupper((string) $finOp->currency)) {
                $this->updateProcessedEvent($processedEvent, [
                    'status' => 'unmatched',
                    'error_reason' => 'REFUND_CURRENCY_MISMATCH: Failed refund event currency differs from the financial operation.',
                    'processed_at' => Carbon::now(),
                ]);
                Log::critical('Paystack refund.failed currency mismatch; financial operation and reservation were not changed.', [
                    'event_id' => $processedEvent->event_id,
                    'operation_id' => $finOp->id,
                    'expected_currency' => $finOp->currency,
                    'received_currency' => $rData['currency'],
                ]);
                return response()->json([
                    'status' => true,
                    'message' => 'Refund failure currency conflicts with the financial operation; flagged for review.',
                ], 200);
            }

            $failureReconciliation = \Illuminate\Support\Facades\DB::transaction(function () use ($finOp, $rData, $processedEvent) {
                // Fence failure/release side effects in the same transaction as inbox
                // ownership so an expired worker cannot release a newer worker's reservation.
                $claimedEvent = \App\Models\ProcessedWebhookEvent::where('id', $processedEvent->id)
                    ->lockForUpdate()
                    ->first();
                if (!$claimedEvent
                    || $claimedEvent->status !== 'processing'
                    || (int) $claimedEvent->attempts !== (int) $processedEvent->attempts) {
                    return 'stale';
                }

                $lockedOp = \App\Models\FinancialOperation::where('id', $finOp->id)->lockForUpdate()->firstOrFail();
                if ($lockedOp->status === 'confirmed') {
                    // A late failure notification must never downgrade a confirmed refund.
                    return 'processed';
                }
                if ($lockedOp->status !== 'failed' && $lockedOp->status !== 'reversed') {
                    $lockedOp->update([
                        'status' => 'failed',
                        'failed_at' => Carbon::now(),
                    ]);
                    \App\Models\FinancialReservation::where('financial_operation_id', $lockedOp->id)
                        ->where('status', 'active')
                        ->update([
                            'status' => 'released',
                            'released_at' => Carbon::now(),
                        ]);
                    $lockedOp->gatewayAttempts()->where('gateway_reference', $rData['refund_reference'] ?? ($rData['reference'] ?? ($rData['id'] ?? '')))
                        ->update(['outcome' => 'failed', 'response_payload' => $rData]);
                }

                $orderId = $lockedOp->metadata['order_id'] ?? null;
                if ($orderId) {
                    // The order-level status is a summary, not the state of this one
                    // refund. Preserve outstanding partial refunds and any earlier
                    // confirmed partial refund when one operation fails.
                    $hasOutstandingRefund = \App\Models\FinancialOperation::where('operation_type', 'customer_refund')
                        ->whereJsonContains('metadata->order_id', $orderId)
                        ->whereNotIn('status', ['confirmed', 'failed', 'reversed'])
                        ->exists();
                    $hasConfirmedRefund = \App\Models\FinancialOperation::where('operation_type', 'customer_refund')
                        ->whereJsonContains('metadata->order_id', $orderId)
                        ->where('status', 'confirmed')
                        ->exists();
                    $hasPendingReturnCase = \App\Models\ReturnCase::where('order_id', $orderId)
                        ->whereIn('status', ['refund_pending', 'refund_approved'])
                        ->exists();

                    $summaryStatus = ($hasOutstandingRefund || $hasPendingReturnCase)
                        ? 'pending'
                        : ($hasConfirmedRefund ? 'completed' : 'failed');

                    Order::where('id', $orderId)->update([
                        'paystack_refund_status' => $summaryStatus,
                    ]);
                }

                return 'processed';
            });

            if ($failureReconciliation === 'stale') {
                // Do not write inbox status: the newer attempt owns the row.
                return response()->json([
                    'status' => false,
                    'message' => 'Webhook claim expired before refund-failure reconciliation; retry/reconciliation required.',
                ], 429);
            }

            $this->updateProcessedEvent($processedEvent, [
                'status' => 'completed',
                'processed_at' => Carbon::now(),
            ]);

            return response()->json(['status' => true, 'message' => 'Refund failure reconciled against its financial operation.'], 200);
        }

        // Handle Paystack In-Flight Refund Notifications (Reconciliation Evidence, NOT Outflow Authorization)
        if (in_array($event['event'], ['refund.pending', 'refund.processing', 'refund.needs-attention'], true)) {
            $rData = $event['data'] ?? [];
            $rRef = (string)($rData['refund_reference'] ?? ($rData['reference'] ?? ($rData['id'] ?? '')));
            $transRef = (string)($rData['transaction_reference'] ?? ($rData['transaction'] ?? ''));

            Log::info("Paystack Webhook: {$event['event']} for refund ref: {$rRef}, trans: {$transRef}");

            $finOp = null;
            if (!empty($rRef)) {
                $finOp = \App\Models\FinancialOperation::where('operation_type', 'customer_refund')
                    ->where(function ($q) use ($rRef) {
                        $q->where('gateway_reference', $rRef)
                          ->orWhereHas('gatewayAttempts', function ($aq) use ($rRef) {
                              $aq->where('gateway_reference', $rRef);
                          });
                    })
                    ->first();
            }

            if (!$finOp && !empty($transRef)) {
                $finOp = \App\Models\FinancialOperation::where('operation_type', 'customer_refund')
                    ->whereJsonContains('metadata->payment_reference', $transRef)
                    ->whereIn('status', ['pending', 'holding_reservation', 'initiated'])
                    ->first();
            }

            if ($finOp) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($finOp, $event, $rData) {
                    $lockedOp = \App\Models\FinancialOperation::where('id', $finOp->id)->lockForUpdate()->first();
                    if ($lockedOp && in_array($lockedOp->status, ['pending', 'holding_reservation'], true)) {
                        $lockedOp->update([
                            'status' => 'initiated',
                            'metadata' => array_merge($lockedOp->metadata ?? [], [
                                'provider_status' => $event['event'],
                                'provider_event_at' => Carbon::now()->toIso8601String(),
                            ]),
                        ]);
                    }
                    if ($lockedOp && ($event['event'] === 'refund.needs-attention')) {
                        \App\Models\AdminAuditLog::create([
                            'action' => 'refund_needs_attention_alert',
                            'target_type' => \App\Models\FinancialOperation::class,
                            'target_id' => $lockedOp->id,
                            'new_values' => ['event' => $event['event'], 'payload' => $rData],
                            'ip_address' => request()->ip() ?? '127.0.0.1',
                        ]);
                    }
                });
            }

            $this->updateProcessedEvent($processedEvent, [
                'status' => 'completed',
                'processed_at' => Carbon::now(),
            ]);

            return response()->json(['status' => true, 'message' => "Refund status '{$event['event']}' recorded as reconciliation evidence."], 200);
        }

        // Handle Paystack Customer Dispute Notifications (Chargebacks & Retrieval Requests)
        if (str_starts_with((string)$event['event'], 'charge.dispute.')) {
            $dData = $event['data'] ?? [];
            $transRef = (string)($dData['transaction']['reference'] ?? ($dData['transaction_reference'] ?? ($dData['reference'] ?? '')));
            $disputeStatus = (string)($dData['status'] ?? 'pending');

            Log::warning("Paystack Webhook: Dispute event '{$event['event']}' for trans ref '{$transRef}'", $dData);

            $order = !empty($transRef) ? Order::where('payment_reference', $transRef)->first() : null;

            if ($order) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($order, $event, $dData, $disputeStatus) {
                    $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();
                    if ($lockedOrder) {
                        if ($event['event'] === 'charge.dispute.create') {
                            if (in_array($lockedOrder->vendor_settlement_status, ['pending', 'ready_for_payout'])) {
                                $lockedOrder->vendor_settlement_status = 'settlement_frozen_dispute';
                            }
                        }

                        \App\Models\AdminAuditLog::create([
                            'user_id' => null,
                            'action' => 'paystack_charge_dispute_notification',
                            'target_type' => Order::class,
                            'target_id' => $lockedOrder->id,
                            'new_values' => [
                                'dispute_event' => $event['event'],
                                'dispute_status' => $disputeStatus,
                                'dispute_amount' => $dData['amount'] ?? null,
                                'reason' => $dData['reason'] ?? 'customer_dispute',
                            ],
                            'ip_address' => request()->ip() ?? '127.0.0.1',
                            'user_agent' => 'Paystack Webhook',
                        ]);

                        $lockedOrder->save();
                    }
                });
            }

            $this->updateProcessedEvent($processedEvent, [
                'status' => 'completed',
                'processed_at' => Carbon::now(),
            ]);

            return response()->json(['status' => true, 'message' => "Dispute event '{$event['event']}' logged for audit."], 200);
        }

        // Unsupported-but-valid Paystack event types are intentionally ignored, but
        // the inbox claim must still reach a terminal state. Leaving it as "processing"
        // causes needless retries forever and obscures actual crash recovery.
        if ($event['event'] !== 'charge.success') {
            $this->updateProcessedEvent($processedEvent, [
                'status' => 'completed',
                'processed_at' => Carbon::now(),
                'error_reason' => null,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Event ignored: ' . $event['event'],
            ], 200);
        }

        $data = $event['data'] ?? [];
        $reference = (string) ($data['reference'] ?? '');

        if (empty($reference)) {
            return response()->json([
                'status' => false,
                'message' => 'Missing reference in event payload.',
            ], 422);
        }

        $order = Order::where('payment_reference', $reference)->first();

        if (!$order) {
            $this->updateProcessedEvent($processedEvent, [
                'status' => 'failed',
                'error_reason' => "Order not found for reference '{$reference}'.",
            ]);
            Log::warning("Paystack Webhook: Order not found for reference '{$reference}'.");
            return response()->json([
                'status' => false,
                'message' => 'Order not found for transaction reference.',
            ], 404);
        }

        // A valid webhook HMAC authenticates the event sender, but server-to-server
        // verification is the authoritative payment evidence used for settlement.
        // Network/verification uncertainty leaves this inbox event retryable and the
        // order unpaid; it never falls back to trusting the webhook body.
        $verification = $this->paystackService->verifyTransaction($reference, $order);
        if (empty($verification['success'])) {
            $verificationCode = (string) ($verification['code'] ?? 'GATEWAY_ERROR');
            $verificationMessage = (string) ($verification['message'] ?? 'Server-to-server payment verification failed.');

            // A definitive provider rejection of a signed charge.success payload is
            // an operational exception, not just a failed webhook attempt. Preserve
            // the evidence for treasury review; transient/configuration failures are
            // left retryable without being mislabeled as a captured payment.
            if (!in_array($verificationCode, ['GATEWAY_ERROR', 'EXCEPTION', 'CONFIG_MISSING'], true)) {
                $incomingTransactionId = trim((string) ($data['id'] ?? ''));
                $incomingAmount = $data['amount'] ?? null;
                $incomingAmountIsInteger = is_int($incomingAmount)
                    || (is_string($incomingAmount) && preg_match('/^\\d+$/', $incomingAmount) === 1);
                $exceptionKey = 'PAYSTACK-VERIFY-REJECT-' . hash(
                    'sha256',
                    $order->id . '|' . (string) $processedEvent->event_id . '|' . $verificationCode
                );
                $verificationException = \App\Models\PaymentCaptureException::firstOrCreate(
                    ['idempotency_key' => $exceptionKey],
                    [
                        'order_id' => $order->id,
                        'gateway_provider' => 'paystack',
                        'gateway_transaction_id' => $incomingTransactionId !== '' ? $incomingTransactionId : null,
                        'payment_reference' => $reference,
                        'amount_kobo' => $incomingAmountIsInteger ? (int) $incomingAmount : null,
                        'currency' => strtoupper((string) ($data['currency'] ?? '')) ?: null,
                        'exception_type' => 'server_verification_rejected_' . strtolower($verificationCode),
                        'status' => 'pending_admin_review',
                        'evidence' => [
                            'event_id' => (string) $processedEvent->event_id,
                            'webhook_transaction_id' => $incomingTransactionId ?: null,
                            'webhook_amount' => $incomingAmount,
                            'webhook_currency' => $data['currency'] ?? null,
                            'webhook_status' => $data['status'] ?? null,
                            'webhook_reference' => $reference,
                            'server_verification_code' => $verificationCode,
                            'server_verification_message' => $verificationMessage,
                        ],
                        'detected_at' => Carbon::now(),
                    ]
                );

                if ($verificationException->wasRecentlyCreated) {
                    \App\Models\AdminAuditLog::create([
                        'user_id' => null,
                        'action' => 'paystack_server_verification_rejected_charge',
                        'target_type' => Order::class,
                        'target_id' => $order->id,
                        'new_values' => [
                            'exception_key' => $exceptionKey,
                            'verification_code' => $verificationCode,
                            'payment_reference' => $reference,
                            'settlement_blocked' => true,
                        ],
                        'ip_address' => request()->ip() ?? '127.0.0.1',
                        'user_agent' => 'Paystack webhook verification',
                    ]);
                }
            }

            $this->updateProcessedEvent($processedEvent, [
                'status' => 'failed',
                'error_reason' => 'SERVER_VERIFICATION_' . $verificationCode . ': ' . $verificationMessage,
            ]);
            Log::warning("Paystack webhook server verification failed for order #{$order->id}: {$verificationMessage}");

            return response()->json([
                'status' => false,
                'message' => 'Payment verification is not yet conclusive. The order remains unpaid pending retry/reconciliation.',
            ], in_array($verificationCode, ['GATEWAY_ERROR', 'EXCEPTION', 'CONFIG_MISSING'], true) ? 503 : 422);
        }

        $verifiedData = $verification['data'] ?? [];
        $verificationIsSimulated = !empty($verification['is_simulated']) && app()->environment('testing');
        if (!$verificationIsSimulated) {
            $webhookTransactionId = trim((string) ($data['id'] ?? ''));
            $verifiedTransactionId = trim((string) ($verifiedData['id'] ?? ''));
            $webhookAmount = $data['amount'] ?? null;
            $verifiedAmount = $verifiedData['amount'] ?? null;
            $webhookAmountValid = is_int($webhookAmount)
                || (is_string($webhookAmount) && preg_match('/^\\d+$/', $webhookAmount) === 1);
            $verifiedAmountValid = is_int($verifiedAmount)
                || (is_string($verifiedAmount) && preg_match('/^\\d+$/', $verifiedAmount) === 1);

            $identityMatches = $webhookTransactionId !== ''
                && $verifiedTransactionId !== ''
                && hash_equals($verifiedTransactionId, $webhookTransactionId)
                && strtolower((string) ($data['status'] ?? '')) === strtolower((string) ($verifiedData['status'] ?? ''))
                && strtoupper((string) ($data['currency'] ?? '')) === strtoupper((string) ($verifiedData['currency'] ?? ''))
                && $webhookAmountValid
                && $verifiedAmountValid
                && (int) $webhookAmount === (int) $verifiedAmount
                && hash_equals((string) ($verifiedData['reference'] ?? ''), $reference);

            if (!$identityMatches) {
                $exceptionKey = 'PAYSTACK-WEBHOOK-MISMATCH-' . hash('sha256', (string) $processedEvent->event_id);
                \App\Models\PaymentCaptureException::firstOrCreate(
                    ['idempotency_key' => $exceptionKey],
                    [
                        'order_id' => $order->id,
                        'gateway_provider' => 'paystack',
                        'gateway_transaction_id' => $webhookTransactionId !== '' ? $webhookTransactionId : null,
                        'payment_reference' => $reference,
                        'amount_kobo' => $webhookAmountValid ? (int) $webhookAmount : null,
                        'currency' => strtoupper((string) ($data['currency'] ?? '')) ?: null,
                        'exception_type' => 'webhook_verification_mismatch',
                        'status' => 'pending_admin_review',
                        'evidence' => [
                            'webhook_transaction_id' => $webhookTransactionId ?: null,
                            'verified_transaction_id' => $verifiedTransactionId ?: null,
                            'webhook_amount' => $webhookAmount,
                            'verified_amount' => $verifiedAmount,
                            'webhook_currency' => $data['currency'] ?? null,
                            'verified_currency' => $verifiedData['currency'] ?? null,
                            'webhook_status' => $data['status'] ?? null,
                            'verified_status' => $verifiedData['status'] ?? null,
                            'webhook_reference' => $data['reference'] ?? null,
                            'verified_reference' => $verifiedData['reference'] ?? null,
                            'event_id' => (string) $processedEvent->event_id,
                        ],
                        'detected_at' => Carbon::now(),
                    ]
                );
                \App\Models\AdminAuditLog::create([
                    'user_id' => null,
                    'action' => 'paystack_webhook_verification_mismatch',
                    'target_type' => Order::class,
                    'target_id' => $order->id,
                    'new_values' => [
                        'exception_key' => $exceptionKey,
                        'event_id' => (string) $processedEvent->event_id,
                        'payment_reference' => $reference,
                        'settlement_blocked' => true,
                    ],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'user_agent' => 'Paystack webhook verification',
                ]);
                $this->updateProcessedEvent($processedEvent, [
                    'status' => 'failed',
                    'error_reason' => 'SERVER_VERIFICATION_MISMATCH: webhook and verified transaction evidence differ.',
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'Webhook evidence differs from the server-verified transaction. Settlement is blocked for admin review.',
                ], 422);
            }
        }

        // The testing-only simulated gateway cannot provide an external transaction
        // record; production always settles from the verified provider response.
        $settlementData = $verificationIsSimulated ? $data : $verifiedData;

        // Authoritative Settlement Core Execution under Pessimistic Lock
        $settlement = $this->settlementService->settlePaystackOrder(
            $order,
            $settlementData,
            null,
            'paystack_webhook',
            (int) $processedEvent->id,
            (int) $processedEvent->attempts
        );

        if (($settlement['status'] ?? null) === 'STALE_WEBHOOK_CLAIM') {
            // A newer attempt owns the inbox row. Do not overwrite its status.
            return response()->json([
                'status' => false,
                'message' => 'Webhook claim expired before order settlement; retry/reconciliation required.',
            ], 429);
        }

        if (!$settlement['success']) {
            $this->updateProcessedEvent($processedEvent, [
                'status' => 'failed',
                'error_reason' => $settlement['message'] ?? 'Settlement failed',
            ]);
            Log::error("Paystack Webhook settlement failed for order #{$order->id}: " . ($settlement['message'] ?? ''));
            return response()->json([
                'status' => false,
                'message' => $settlement['message'],
            ], 422);
        }

        // Zero-Automated-Outflow Law: Webhook records facts and creates an auditable pending refund request;
        // it must NEVER autonomously dispatch funds through the payment gateway.
        if (!empty($settlement['trigger_gateway_refund'])) {
            try {
                $finOpService = app(\App\Services\FinancialOperationService::class);
                $existingOp = \App\Models\FinancialOperation::where('operation_type', 'customer_refund')
                    ->whereJsonContains('metadata->order_id', $order->id)
                    ->whereIn('status', ['pending', 'initiated', 'confirmed'])
                    ->first();

                if (!$existingOp) {
                    $finOpService->createRefundOperation(
                        $order,
                        $settlement['refund_amount'] ?? (string)$order->grand_total,
                        "Refund pending administrative authorization for cancelled/depleted Order #{$order->order_number}",
                        null,
                        null,
                        ['type' => 'pending_admin_review_late_payment']
                    );
                    Log::info("Late-payment refund operation queued for Super Admin review for Order #{$order->id}. Autonomous gateway dispatch blocked.");
                }
            } catch (\Throwable $e) {
                Log::error("Failed to create pending refund operation for out-of-stock Order #{$order->id}: " . $e->getMessage());
            }
        }

        $this->updateProcessedEvent($processedEvent, [
            'status' => 'completed',
            'processed_at' => Carbon::now(),
        ]);

        return response()->json([
            'status' => true,
            'settlement' => $settlement['status'],
            'message' => $settlement['message'],
        ], 200);
    }
}
