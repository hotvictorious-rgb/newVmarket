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
            return redirect()->route('order.status', $redirectParams)
                ->with('warning', 'Order was cancelled due to inventory depletion before payment was verified. A full refund of ₦' . number_format((float)$order->grand_total, 2) . ' has been initiated to your bank account.');
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
    private function resolveAndValidatePayout(array $tData, \App\Models\ProcessedWebhookEvent $processedEvent): ?array
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
                $processedEvent->update([
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
            $processedEvent->update([
                'status' => 'failed',
                'error_reason' => "REFERENCE_CONFLICT: Payout #{$payout->id} expected ref '{$payout->transfer_reference}', got '{$tRef}'.",
                'processed_at' => Carbon::now(),
            ]);
            return ['error' => 'Conflicting transfer reference'];
        }

        // Amount Invariant Check (Audit Finding P0): Match integer kobo
        if (isset($tData['amount'])) {
            $webhookKobo = (int)$tData['amount'];
            $expectedKobo = (int)bcmul((string)$payout->amount, '100', 0);
            if ($webhookKobo !== $expectedKobo) {
                Log::critical("Paystack Webhook Amount Mismatch! Payout #{$payout->id} expected {$expectedKobo} kobo, received {$webhookKobo} kobo.");
                $processedEvent->update([
                    'status' => 'failed',
                    'error_reason' => "AMOUNT_MISMATCH: Payout #{$payout->id} expected {$expectedKobo} kobo, received {$webhookKobo} kobo.",
                    'processed_at' => Carbon::now(),
                ]);
                return ['error' => 'Payout amount mismatch'];
            }
        }

        // Currency Invariant Check
        if (isset($tData['currency'])) {
            $currency = strtoupper(trim((string)$tData['currency']));
            if ($currency !== 'NGN') {
                Log::critical("Paystack Webhook Currency Mismatch! Expected NGN, received {$currency}.", [$currency]);
                $processedEvent->update([
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

        // Authoritative Webhook Inbox State Machine (Audit P0 & P1 Concurrency-Safe Resolution)
        // Transactional Row-Level Lock prevents concurrent race conditions and permanent event skipping
        $claimResult = \Illuminate\Support\Facades\DB::transaction(function () use ($eventId, $event, $payload, $eventHash) {
            $existingEvent = \App\Models\ProcessedWebhookEvent::where('event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if ($existingEvent) {
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
                    $existingEvent->updated_at = \Carbon\Carbon::now();
                    $existingEvent->save();
                    return ['action' => 'process', 'event' => $existingEvent];
                }
                if ($existingEvent->status === 'failed') {
                    // Previous attempt failed, atomically retry attempt
                    $existingEvent->increment('attempts');
                    $existingEvent->status = 'processing';
                    $existingEvent->payload = $payload;
                    $existingEvent->updated_at = \Carbon\Carbon::now();
                    $existingEvent->save();
                    return ['action' => 'process', 'event' => $existingEvent];
                }
            }

            // First-time arrival: atomically insert with status = 'processing' and stored payload
            try {
                $newEvent = \App\Models\ProcessedWebhookEvent::create([
                    'event_id' => $eventId,
                    'event_type' => (string)($event['event'] ?? 'unknown'),
                    'payload_hash' => $eventHash,
                    'payload' => $payload,
                    'status' => 'processing',
                    'attempts' => 1,
                ]);
                return ['action' => 'process', 'event' => $newEvent];
            } catch (\Illuminate\Database\QueryException $e) {
                $existing = \App\Models\ProcessedWebhookEvent::where('event_id', $eventId)->first();
                if ($existing && in_array($existing->status, ['completed', 'unmatched'], true)) {
                    return ['action' => 'completed', 'message' => 'Event already processed.'];
                }
                return ['action' => 'throttled', 'message' => 'Event is currently being processed.'];
            }
        });

        if ($claimResult['action'] === 'completed') {
            return response()->json(['status' => true, 'message' => $claimResult['message']], 200);
        }
        if ($claimResult['action'] === 'throttled') {
            return response()->json(['status' => false, 'message' => $claimResult['message']], 429);
        }

        $processedEvent = $claimResult['event'];

        // Handle Paystack Transfer Webhooks (Automated Fleet & Vendor Payout Reconciliation)
        if ($event['event'] === 'transfer.success') {
            $tData = $event['data'] ?? [];
            $tRef = (string)($tData['reference'] ?? '');
            $tCode = (string)($tData['transfer_code'] ?? '');
            Log::info("Paystack Webhook: Automated transfer success for reference: {$tRef}, code: {$tCode}");

            $resolved = $this->resolveAndValidatePayout($tData, $processedEvent);

            if (isset($resolved['error'])) {
                return response()->json(['status' => false, 'message' => $resolved['error']], 400);
            }

            if (!$resolved) {
                $processedEvent->update([
                    'status' => 'unmatched',
                    'error_reason' => "No matching vendor or logistics payout request found for reference '{$tRef}' or transfer_code '{$tCode}'",
                    'processed_at' => Carbon::now(),
                ]);
                Log::warning("Paystack Webhook: Unmatched transfer.success event #{$processedEvent->id} for ref '{$tRef}', code '{$tCode}'");

                return response()->json(['status' => false, 'message' => 'Unmatched transfer.success event recorded for treasury audit.'], 200);
            }

            // Strictly finalize exactly ONE unambiguous payout entity
            if ($resolved['type'] === 'vendor') {
                $this->finalizationService->finalizeVendorPayout($resolved['payout'], $tData);
            } elseif ($resolved['type'] === 'logistics') {
                $this->finalizationService->finalizeLogisticsPayout($resolved['payout'], $tData);
            }

            $processedEvent->update([
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

            $resolved = $this->resolveAndValidatePayout($tData, $processedEvent);

            if (isset($resolved['error'])) {
                return response()->json(['status' => false, 'message' => $resolved['error']], 400);
            }

            if (!$resolved) {
                $processedEvent->update([
                    'status' => 'unmatched',
                    'error_reason' => "No matching vendor or logistics payout request found for reference '{$tRef}' or transfer_code '{$tCode}'",
                    'processed_at' => Carbon::now(),
                ]);
                Log::warning("Paystack Webhook: Unmatched {$event['event']} event #{$processedEvent->id} for ref '{$tRef}', code '{$tCode}'");

                return response()->json(['status' => false, 'message' => "Unmatched {$event['event']} event recorded for treasury audit."], 200);
            }

            // Strictly revert exactly ONE unambiguous payout entity
            if ($resolved['type'] === 'logistics') {
                $this->finalizationService->revertLogisticsPayout(
                    $resolved['payout'],
                    "Paystack transfer {$event['event']}: {$reason}"
                );
            } elseif ($resolved['type'] === 'vendor') {
                $this->finalizationService->revertVendorPayout(
                    $resolved['payout'],
                    "Paystack transfer {$event['event']}: {$reason}"
                );
            }

            $processedEvent->update([
                'status' => 'completed',
                'processed_at' => Carbon::now(),
            ]);

            return response()->json(['status' => true, 'message' => "Transfer {$event['event']} processed and reversed successfully."], 200);
        }

        // Only process charge.success events
        if ($event['event'] !== 'charge.success') {
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
            $processedEvent->update([
                'status' => 'failed',
                'error_reason' => "Order not found for reference '{$reference}'.",
            ]);
            Log::warning("Paystack Webhook: Order not found for reference '{$reference}'.");
            return response()->json([
                'status' => false,
                'message' => 'Order not found for transaction reference.',
            ], 404);
        }

        // Authoritative Settlement Core Execution under Pessimistic Lock
        $settlement = $this->settlementService->settlePaystackOrder(
            $order,
            $data,
            null,
            'paystack_webhook'
        );

        if (!$settlement['success']) {
            $processedEvent->update([
                'status' => 'failed',
                'error_reason' => $settlement['message'] ?? 'Settlement failed',
            ]);
            Log::error("Paystack Webhook settlement failed for order #{$order->id}: " . ($settlement['message'] ?? ''));
            return response()->json([
                'status' => false,
                'message' => $settlement['message'],
            ], 422);
        }

        $processedEvent->update([
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
