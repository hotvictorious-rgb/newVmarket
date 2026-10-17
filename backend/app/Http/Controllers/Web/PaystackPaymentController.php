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
                if ($existing && $existing->status === 'completed') {
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

            // 1. Reconcile Logistics Payout via strict exact match (Audit Finding E)
            $logisticsPayout = null;
            if (!empty($tRef) || !empty($tCode)) {
                $logisticsPayout = \App\Models\LogisticsPayoutRequest::where(function ($q) use ($tRef, $tCode) {
                    $hasCondition = false;
                    if (!empty($tRef)) {
                        $q->where('transfer_reference', $tRef)
                          ->orWhere('settlement_proof_path', 'paystack_transfer_ref:' . $tRef);
                        $hasCondition = true;
                    }
                    if (!empty($tCode)) {
                        if ($hasCondition) {
                            $q->orWhere('paystack_transfer_code', $tCode)
                              ->orWhere('settlement_proof_path', 'paystack_transfer:' . $tCode);
                        } else {
                            $q->where('paystack_transfer_code', $tCode)
                              ->orWhere('settlement_proof_path', 'paystack_transfer:' . $tCode);
                        }
                    }
                })->first();
            }

            // 2. Reconcile Vendor Payout via strict exact match (Audit Finding E & G)
            $vendorPayout = null;
            if (!empty($tRef) || !empty($tCode)) {
                $vendorPayout = \App\Models\VendorPayoutRequest::where(function ($q) use ($tRef, $tCode) {
                    $hasCondition = false;
                    if (!empty($tRef)) {
                        $q->where('transfer_reference', $tRef)
                          ->orWhere('settlement_proof_path', 'paystack_transfer_ref:' . $tRef);
                        $hasCondition = true;
                    }
                    if (!empty($tCode)) {
                        if ($hasCondition) {
                            $q->orWhere('paystack_transfer_code', $tCode)
                              ->orWhere('settlement_proof_path', 'paystack_transfer:' . $tCode);
                        } else {
                            $q->where('paystack_transfer_code', $tCode)
                              ->orWhere('settlement_proof_path', 'paystack_transfer:' . $tCode);
                        }
                    }
                })->first();
            }

            // Delegate 100% of payout finalization to idempotent PayoutFinalizationService
            if ($vendorPayout) {
                $this->finalizationService->finalizeVendorPayout($vendorPayout, $tData);
            }

            if ($logisticsPayout) {
                $this->finalizationService->finalizeLogisticsPayout($logisticsPayout, $tData);
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

            // A. Handle Logistics Fleet Payout Reversal via strict exact match
            $logisticsPayout = null;
            if (!empty($tRef) || !empty($tCode)) {
                $logisticsPayout = \App\Models\LogisticsPayoutRequest::where(function ($q) use ($tRef, $tCode) {
                    $hasCondition = false;
                    if (!empty($tRef)) {
                        $q->where('transfer_reference', $tRef)
                          ->orWhere('settlement_proof_path', 'paystack_transfer_ref:' . $tRef);
                        $hasCondition = true;
                    }
                    if (!empty($tCode)) {
                        if ($hasCondition) {
                            $q->orWhere('paystack_transfer_code', $tCode)
                              ->orWhere('settlement_proof_path', 'paystack_transfer:' . $tCode);
                        } else {
                            $q->where('paystack_transfer_code', $tCode)
                              ->orWhere('settlement_proof_path', 'paystack_transfer:' . $tCode);
                        }
                    }
                })->first();
            }

            if ($logisticsPayout && $logisticsPayout->status === 'approved') {
                \Illuminate\Support\Facades\DB::transaction(function () use ($logisticsPayout, $tData, $event, $reason) {
                    $lockedPayout = \App\Models\LogisticsPayoutRequest::where('id', $logisticsPayout->id)->lockForUpdate()->firstOrFail();
                    if ($lockedPayout->status === 'rejected') {
                        return;
                    }

                    $company = $lockedPayout->company;
                    // Restore company corporate wallet balance
                    $company->wallet_balance = \App\Services\BcMathService::add((string)$company->wallet_balance, (string)$lockedPayout->amount);
                    $company->save();

                    $lockedPayout->status = 'rejected';
                    $lockedPayout->rejection_reason = "Paystack transfer {$event['event']}: {$reason}";
                    $lockedPayout->admin_notes = ($lockedPayout->admin_notes ? $lockedPayout->admin_notes . ' | ' : '') . "Reversed by Paystack Webhook ({$event['event']}): {$reason}";
                    $lockedPayout->save();

                    // Reversing Journal Entries
                    $revRef = 'REV-FLEET-' . $lockedPayout->id . '-' . date('YmdHis');
                    \App\Models\FinancialJournalEntry::create([
                        'order_id' => null,
                        'transaction_reference' => $revRef,
                        'event_id' => 'EVT-REV-FLEET-' . $lockedPayout->id,
                        'beneficiary_type' => \App\Models\LogisticsCompanyProfile::class,
                        'beneficiary_id' => $company->id,
                        'account_name' => 'logistics_payout_payable',
                        'entry_type' => 'credit',
                        'amount' => (string)$lockedPayout->amount,
                        'currency' => 'NGN',
                        'description' => "Corporate payout liability restored after Paystack transfer failure: {$company->company_name}",
                    ]);

                    \App\Models\FinancialJournalEntry::create([
                        'order_id' => null,
                        'transaction_reference' => $revRef,
                        'event_id' => 'EVT-REV-FLEET-' . $lockedPayout->id,
                        'beneficiary_type' => \App\Models\LogisticsCompanyProfile::class,
                        'beneficiary_id' => $company->id,
                        'account_name' => 'cash_in_escrow',
                        'entry_type' => 'debit',
                        'amount' => (string)$lockedPayout->amount,
                        'currency' => 'NGN',
                        'description' => "Escrow cash restored after Paystack transfer failure: {$company->company_name}",
                    ]);
                });
            }
            if ($logisticsPayout) { \App\Services\NotificationDispatchService::dispatchPayoutRejected($logisticsPayout, "Paystack transfer {$event['event']}: {$reason}"); }

            // B. Handle Merchant Vendor Payout Reversal via strict exact match
            $vendorPayout = null;
            if (!empty($tRef) || !empty($tCode)) {
                $vendorPayout = \App\Models\VendorPayoutRequest::where(function ($q) use ($tRef, $tCode) {
                    $hasCondition = false;
                    if (!empty($tRef)) {
                        $q->where('transfer_reference', $tRef)
                          ->orWhere('settlement_proof_path', 'paystack_transfer_ref:' . $tRef);
                        $hasCondition = true;
                    }
                    if (!empty($tCode)) {
                        if ($hasCondition) {
                            $q->orWhere('paystack_transfer_code', $tCode)
                              ->orWhere('settlement_proof_path', 'paystack_transfer:' . $tCode);
                        } else {
                            $q->where('paystack_transfer_code', $tCode)
                              ->orWhere('settlement_proof_path', 'paystack_transfer:' . $tCode);
                        }
                    }
                })->first();
            }

            if ($vendorPayout && $vendorPayout->status === 'approved') {
                \Illuminate\Support\Facades\DB::transaction(function () use ($vendorPayout, $tData, $event, $reason) {
                    $lockedPayout = \App\Models\VendorPayoutRequest::where('id', $vendorPayout->id)->lockForUpdate()->firstOrFail();
                    if ($lockedPayout->status === 'rejected') {
                        return;
                    }

                    $vendor = $lockedPayout->vendor;

                    $lockedPayout->status = 'rejected';
                    $lockedPayout->rejection_reason = "Paystack transfer {$event['event']}: {$reason}";
                    $lockedPayout->admin_notes = ($lockedPayout->admin_notes ? $lockedPayout->admin_notes . ' | ' : '') . "Reversed by Paystack Webhook ({$event['event']}): {$reason}";
                    $lockedPayout->save();

                    // Restore ONLY the exact orders that were allocated to this failed payout request (Finding 1 & P1)
                    $allocations = \App\Models\VendorPayoutAllocation::where('vendor_payout_request_id', $lockedPayout->id)->get();
                    if ($allocations->isNotEmpty()) {
                        foreach ($allocations as $alloc) {
                            $alloc->update([
                                'status' => 'reversed',
                                'reversed_at' => Carbon::now(),
                            ]);
                            $order = \App\Models\Order::where('id', $alloc->order_id)->lockForUpdate()->first();
                            if ($order && $order->vendor_settlement_status === 'settled') {
                                $order->vendor_settlement_status = 'eligible';
                                $order->save();
                            }
                        }
                    } else {
                        // Audit Finding D: Halt Unsafe Guessed Reversals
                        // If allocations are missing, never guess and un-settle arbitrary recent orders
                        Log::alert("Paystack Webhook Reversal: Payout #{$lockedPayout->id} has no allocation records. Halting order reversal for manual treasury review.");
                        $lockedPayout->admin_notes = ($lockedPayout->admin_notes ? $lockedPayout->admin_notes . ' | ' : '') . 'Order allocations missing; orders not reversed to prevent ledger corruption. Requires treasury audit.';
                        $lockedPayout->save();
                    }

                    // Reversing Journal Entries: discharge vendor payable liability and restore escrow cash
                    $formattedPayoutAmount = bcadd((string)$lockedPayout->amount, '0', 2);
                    $revRef = 'REV-VND-' . $lockedPayout->id . '-' . date('YmdHis');

                    \App\Models\FinancialJournalEntry::create([
                        'order_id' => null,
                        'transaction_reference' => $revRef,
                        'event_id' => 'EVT-REV-VND-' . $lockedPayout->id,
                        'beneficiary_type' => \App\Models\VendorProfile::class,
                        'beneficiary_id' => $vendor->id,
                        'account_name' => 'vendor_escrow_holding',
                        'entry_type' => 'credit',
                        'amount' => $formattedPayoutAmount,
                        'currency' => 'NGN',
                        'description' => "Merchant escrow holding restored after Paystack transfer failure: {$vendor->business_name}",
                    ]);

                    \App\Models\FinancialJournalEntry::create([
                        'order_id' => null,
                        'transaction_reference' => $revRef,
                        'event_id' => 'EVT-REV-VND-' . $lockedPayout->id,
                        'beneficiary_type' => \App\Models\VendorProfile::class,
                        'beneficiary_id' => $vendor->id,
                        'account_name' => 'cash_in_escrow',
                        'entry_type' => 'debit',
                        'amount' => $formattedPayoutAmount,
                        'currency' => 'NGN',
                        'description' => "Escrow cash restored after Paystack transfer failure: {$vendor->business_name}",
                    ]);
                });
            }
            if ($vendorPayout) {
                $vendorPayout->gateway_status = 'failed';
                $vendorPayout->save();
                \App\Services\NotificationDispatchService::dispatchPayoutRejected($vendorPayout, "Paystack transfer {$event['event']}: {$reason}");
            }
            if ($logisticsPayout) {
                $logisticsPayout->gateway_status = 'failed';
                $logisticsPayout->save();
            }

            $processedEvent->update([
                'status' => 'completed',
                'processed_at' => Carbon::now(),
            ]);

            return response()->json(['status' => true, 'message' => "Transfer {$event['event']} processed and refunded."], 200);
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
