<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\FinancialJournalEntry;
use App\Models\LogisticsPayoutRequest;
use App\Models\ManualBankTransferPayment;
use App\Models\ManualBankTransferRefundCase;
use App\Models\Order;
use App\Models\RetailerSupplierPayoutRequest;
use App\Models\ReturnCase;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CheckoutExecutionService;
use App\Services\OrderSettlementService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ManualBankTransferController extends Controller
{
    /**
     * Customer submits a private receipt for a manual-transfer order.
     * While review remains open, an audited replacement receipt may be submitted.
     * A rejected submission is terminal: customer must place a new order to retry.
     */
    public function submitEvidence(Request $request, string $orderNumber)
    {
        $validated = $request->validate([
            'payment_screenshot' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'token' => ['nullable', 'string', 'max:200'],
        ]);

        $order = Order::where('order_number', trim($orderNumber))->firstOrFail();
        $user = $request->user();
        $providedToken = (string) ($validated['token'] ?? $request->query('token', ''));
        $sessionToken = (string) session('order_guest_token_' . $order->order_number);

        $authorized = $user && (int) $user->id === (int) $order->customer_id;
        if (!$authorized && $order->guest_access_token) {
            $authorized = ($providedToken !== '' && hash_equals((string) $order->guest_access_token, $providedToken))
                || ($sessionToken !== '' && hash_equals((string) $order->guest_access_token, $sessionToken));
        }

        abort_unless($authorized, 403, 'You are not authorized to submit evidence for this order.');

        if ($order->payment_method !== 'manual_bank_transfer') {
            return back()->withErrors(['payment_screenshot' => 'This order is not assigned to Manual Bank Transfer. Payment methods cannot be crossed.']);
        }

        $accountSnapshot = $order->manual_bank_transfer_account_snapshot;
        if (!is_array($accountSnapshot) || empty($accountSnapshot['account_number']) || empty($accountSnapshot['bank_name']) || empty($accountSnapshot['account_name'])) {
            return back()->withErrors(['payment_screenshot' => 'This order has no valid receiving-account snapshot. Do not transfer money; contact Victorious MARKET support.']);
        }

        if (!in_array($order->payment_status, ['pending', 'unpaid'], true) || $order->paid_at) {
            return back()->withErrors(['payment_screenshot' => 'This order is no longer eligible for a manual payment submission.']);
        }

        $file = $request->file('payment_screenshot');
        if (!$file || !$file->isValid()) {
            return back()->withErrors(['payment_screenshot' => 'The uploaded receipt could not be validated. Please select a clear image and try again.']);
        }

        // Never use the user-provided filename. Keep evidence on the private local disk.
        $extension = strtolower((string) $file->extension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return back()->withErrors(['payment_screenshot' => 'Only JPEG, PNG or WebP receipt images are accepted.']);
        }

        $relativePath = $file->storeAs(
            'manual-bank-transfer/' . $order->id,
            (string) Str::uuid() . '.' . $extension,
            'local'
        );

        if (!$relativePath) {
            return back()->withErrors(['payment_screenshot' => 'The receipt could not be stored securely. Please try again.']);
        }

        try {
            DB::transaction(function () use ($order, $user, $file, $relativePath, $request) {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

                if ($lockedOrder->payment_method !== 'manual_bank_transfer'
                    || !in_array($lockedOrder->payment_status, ['pending', 'unpaid'], true)
                    || $lockedOrder->paid_at) {
                    throw ValidationException::withMessages([
                        'payment_screenshot' => 'The order payment state changed. No receipt was attached.',
                    ]);
                }

                $mime = (string) $file->getMimeType();
                if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    throw ValidationException::withMessages([
                        'payment_screenshot' => 'The uploaded file content is not an accepted receipt image.',
                    ]);
                }

                $existingPayment = ManualBankTransferPayment::where('order_id', $lockedOrder->id)->lockForUpdate()->first();
                if ($existingPayment) {
                    if ($existingPayment->status === 'approved') {
                        throw ValidationException::withMessages([
                            'payment_screenshot' => 'This order is already verified and marked paid.',
                        ]);
                    }
                    if ($existingPayment->status === 'rejected') {
                        throw ValidationException::withMessages([
                            'payment_screenshot' => 'Payment evidence for this order was rejected. Resubmission is closed.',
                        ]);
                    }

                    // Review is open (pending_review or evidence_requested): customer is replacing evidence
                    $history = is_array($existingPayment->evidence_history) ? $existingPayment->evidence_history : [];
                    $history[] = [
                        'action' => 'replaced',
                        'previous_path' => $existingPayment->evidence_path,
                        'evidence_path' => $existingPayment->evidence_path,
                        'previous_sha256' => $existingPayment->evidence_sha256,
                        'previous_submitted_at' => $existingPayment->submitted_at?->toIso8601String(),
                        'replaced_at' => now()->toIso8601String(),
                    ];

                    $newSha256 = hash_file('sha256', Storage::disk('local')->path($relativePath));

                    $existingPayment->update([
                        'evidence_path' => $relativePath,
                        'evidence_mime' => $mime,
                        'evidence_size_bytes' => (int) $file->getSize(),
                        'evidence_sha256' => $newSha256,
                        'status' => 'pending_review',
                        'submitted_at' => now(),
                        'evidence_history' => $history,
                    ]);

                    AdminAuditLog::create([
                        'user_id' => $user?->id,
                        'action' => 'manual_bank_transfer_evidence_replaced',
                        'target_type' => ManualBankTransferPayment::class,
                        'target_id' => $existingPayment->id,
                        'old_values' => ['status' => $existingPayment->getOriginal('status')],
                        'new_values' => [
                            'order_id' => $lockedOrder->id,
                            'status' => 'pending_review',
                            'evidence_sha256' => $newSha256,
                            'evidence_size_bytes' => (int) $file->getSize(),
                        ],
                        'ip_address' => $request->ip() ?? '127.0.0.1',
                        'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                    ]);

                    return;
                }

                $newSha256 = hash_file('sha256', Storage::disk('local')->path($relativePath));
                $payment = ManualBankTransferPayment::create([
                    'order_id' => $lockedOrder->id,
                    'customer_id' => $lockedOrder->customer_id,
                    'evidence_path' => $relativePath,
                    'evidence_mime' => $mime,
                    'evidence_size_bytes' => (int) $file->getSize(),
                    'evidence_sha256' => $newSha256,
                    'status' => 'pending_review',
                    'currency' => 'NGN',
                    'submitted_at' => now(),
                    'evidence_history' => [[
                        'action' => 'submitted',
                        'sha256' => $newSha256,
                        'submitted_at' => now()->toIso8601String(),
                    ]],
                ]);

                AdminAuditLog::create([
                    'user_id' => $user?->id,
                    'action' => 'manual_bank_transfer_evidence_submitted',
                    'target_type' => ManualBankTransferPayment::class,
                    'target_id' => $payment->id,
                    'old_values' => null,
                    'new_values' => [
                        'order_id' => $lockedOrder->id,
                        'order_number' => $lockedOrder->order_number,
                        'payment_method' => 'manual_bank_transfer',
                        'evidence_sha256' => $payment->evidence_sha256,
                        'evidence_size_bytes' => $payment->evidence_size_bytes,
                        'payment_approved' => false,
                    ],
                    'ip_address' => $request->ip() ?? '127.0.0.1',
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($relativePath);
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);
            return back()->withErrors(['payment_screenshot' => 'The receipt could not be recorded. No payment was approved; contact support if this continues.']);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => true,
                'message' => 'Receipt submitted for admin review. The order remains unpaid until actual bank credit is verified.',
                'payment_status' => 'pending_review',
            ], 201);
        }

        return redirect()->route('order.status', ['order_number' => $order->order_number, 'token' => $providedToken ?: null])
            ->with('success', 'Your transfer receipt was submitted securely. Your order remains unpaid until an authorized admin verifies the actual bank credit.');
    }

    public function index()
    {
        $paymentConfig = CheckoutExecutionService::getPaymentConfiguration();
        $paystackEnabled = $paymentConfig['paystack_enabled'];
        $manualBankTransferEnabled = $paymentConfig['manual_bank_transfer_enabled'];
        $activePaymentMethod = $paymentConfig['active_method'];
        $bankAccount = $paymentConfig['manual_bank_transfer_account'];
        $refundCases = ManualBankTransferRefundCase::with(['order', 'payment', 'reviewer'])
            ->orderByRaw("CASE WHEN status = 'pending_admin_review' THEN 0 WHEN status = 'approved_for_dispatch' THEN 1 WHEN status = 'dispatched_awaiting_reconciliation' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->paginate(25, ['*'], 'refund_page');
        $payments = ManualBankTransferPayment::with(['order.customer', 'customer', 'reviewer'])
            ->whereIn('status', ['pending_review', 'evidence_requested', 'approved', 'rejected'])
            ->orderByRaw("CASE WHEN status = 'pending_review' THEN 0 WHEN status = 'evidence_requested' THEN 1 ELSE 2 END")
            ->orderBy('submitted_at')
            ->paginate(25);

        return view('admin.manual-bank-transfers.index', compact('payments', 'refundCases', 'activePaymentMethod', 'bankAccount', 'paystackEnabled', 'manualBankTransferEnabled'));
    }

    public function saveSettings(Request $request)
    {
        $this->authorizeSuperAdmin($request);
        $hasExplicitBooleans = $request->has('paystack_enabled') || $request->has('manual_bank_transfer_enabled');

        if ($hasExplicitBooleans) {
            $paystackEnabled = $request->boolean('paystack_enabled');
            $manualTransferEnabled = $request->boolean('manual_bank_transfer_enabled');
        } else {
            $legacyMethod = (string) $request->input('active_payment_method', 'disabled');
            $paystackEnabled = in_array($legacyMethod, ['paystack', 'both', 'coexistence'], true);
            $manualTransferEnabled = in_array($legacyMethod, ['manual_bank_transfer', 'both', 'coexistence'], true);
        }

        if ($manualTransferEnabled) {
            $request->validate([
                'bank_name' => ['required', 'string', 'max:100'],
                'account_name' => ['required', 'string', 'max:150'],
                'account_number' => ['required', 'regex:/^[0-9]{10}$/'],
                'customer_instructions' => ['nullable', 'string', 'max:2000'],
            ]);
        }

        $oldConfig = CheckoutExecutionService::getPaymentConfiguration()['raw'];
        $account = [
            'bank_name' => trim((string) $request->input('bank_name', '')),
            'account_name' => trim((string) $request->input('account_name', '')),
            'account_number' => trim((string) $request->input('account_number', '')),
            'customer_instructions' => trim((string) $request->input('customer_instructions', '')),
            'updated_at' => now()->toIso8601String(),
        ];

        DB::transaction(function () use ($paystackEnabled, $manualTransferEnabled, $account, $oldConfig, $request) {
            $activeMethod = ($paystackEnabled && $manualTransferEnabled)
                ? 'coexistence'
                : ($paystackEnabled ? 'paystack' : ($manualTransferEnabled ? 'manual_bank_transfer' : 'disabled'));

            $newConfig = [
                'paystack_enabled' => $paystackEnabled,
                'manual_bank_transfer_enabled' => $manualTransferEnabled,
                'active_method' => $activeMethod,
                'manual_bank_transfer_account' => $account,
                'updated_at' => now()->toIso8601String(),
            ];
            SystemSetting::set('checkout.payment_configuration', $newConfig, 'payments');

            AdminAuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'payment_method_configuration_changed',
                'target_type' => SystemSetting::class,
                'target_id' => null,
                'old_values' => ['payment_configuration' => $oldConfig],
                'new_values' => ['payment_configuration' => $newConfig],
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        });

        $statusMsg = match (true) {
            $paystackEnabled && $manualTransferEnabled => 'Payment configuration saved: Paystack and Manual Bank Transfer are coexisting simultaneously.',
            $paystackEnabled => 'Payment configuration saved: Paystack is active.',
            $manualTransferEnabled => 'Payment configuration saved: Manual Bank Transfer is active.',
            default => 'Payment configuration saved: All checkout payment methods are disabled.',
        };

        return redirect()->route('admin.manual-bank-transfers.index')->with('status', $statusMsg);
    }

    public function approve(Request $request, int $id, OrderSettlementService $settlementService)
    {
        $this->authorizeSuperAdmin($request);
        $validated = $request->validate([
            'bank_transaction_reference' => ['required', 'string', 'min:3', 'max:150', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'credited_amount' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'],
            'confirm_bank_credit' => ['accepted'],
            'decision_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $amount = $validated['credited_amount'];
        $normalizedAmount = bcadd($amount, '0', 2);
        $amountKobo = (int) bcmul($normalizedAmount, '100', 0);
        $bankReference = trim($validated['bank_transaction_reference']);

        try {
            $result = DB::transaction(function () use ($id, $validated, $amountKobo, $bankReference, $request, $settlementService) {
                $payment = ManualBankTransferPayment::whereKey($id)->lockForUpdate()->firstOrFail();
                $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

                if (!in_array($payment->status, ['pending_review', 'evidence_requested'], true)) {
                    throw ValidationException::withMessages(['payment' => 'This payment submission has already been decided and cannot be reviewed again.']);
                }
                if ($order->payment_method !== 'manual_bank_transfer') {
                    throw ValidationException::withMessages(['payment' => 'Payment-method mismatch. Manual bank transfer cannot settle this order.']);
                }
                if ($order->payment_status === 'paid' || $order->paid_at) {
                    throw ValidationException::withMessages(['payment' => 'This order is already paid. A second payment is forbidden.']);
                }
                if (!in_array($order->payment_status, ['pending', 'unpaid'], true)) {
                    throw ValidationException::withMessages(['payment' => 'The order is not in a payable state.']);
                }
                if (empty($order->payment_reference)) {
                    throw ValidationException::withMessages(['payment' => 'The order has no immutable internal payment reference; approval is blocked.']);
                }
                if (ManualBankTransferPayment::where('bank_transaction_reference', $bankReference)->where('id', '!=', $payment->id)->exists()
                    || RetailerSupplierPayoutRequest::where('bank_transaction_reference', $bankReference)->exists()
                    || LogisticsPayoutRequest::where('bank_transaction_reference', $bankReference)->exists()
                    || ManualBankTransferRefundCase::where('manual_dispatch_reference', $bankReference)->exists()) {
                    throw ValidationException::withMessages(['bank_transaction_reference' => 'This bank transaction reference has already been assigned in the system.']);
                }
                if (Order::where('payment_reference', $bankReference)->orWhere('paystack_transaction_id', $bankReference)->exists()) {
                    throw ValidationException::withMessages(['bank_transaction_reference' => 'This reference is already used by an order/payment identity and cannot be reused as a manual bank transaction.']);
                }

                $expectedKobo = (int) bcmul(bcadd((string) $order->grand_total, '0', 2), '100', 0);
                if ($amountKobo !== $expectedKobo) {
                    throw ValidationException::withMessages(['credited_amount' => 'The credited amount must exactly match this order total. The payment remains unapproved.']);
                }

                $settlement = $settlementService->settleManualBankTransferOrder(
                    $order,
                    $bankReference,
                    $amountKobo,
                    $request->user()
                );

                if (empty($settlement['success'])) {
                    throw ValidationException::withMessages(['payment' => $settlement['message'] ?? 'Settlement rejected this payment.']);
                }

                $payment->status = 'approved';
                $payment->bank_transaction_reference = $bankReference;
                $payment->credited_amount_kobo = $amountKobo;
                $payment->currency = 'NGN';
                $payment->reviewed_by_admin_id = $request->user()->id;
                $payment->reviewed_at = now();
                $payment->decision_reason = trim((string) ($validated['decision_reason'] ?? 'Verified against actual receiving-account credit.'));
                $payment->save();

                AdminAuditLog::create([
                    'user_id' => $request->user()->id,
                    'action' => 'manual_bank_transfer_payment_approved',
                    'target_type' => ManualBankTransferPayment::class,
                    'target_id' => $payment->id,
                    'old_values' => ['status' => $payment->getOriginal('status')],
                    'new_values' => [
                        'status' => 'approved',
                        'order_id' => $order->id,
                        'bank_transaction_reference' => $bankReference,
                        'credited_amount_kobo' => $amountKobo,
                        'currency' => 'NGN',
                        'order_payment_status' => $settlement['order']->payment_status ?? 'paid',
                        'paystack_transaction_id' => null,
                    ],
                    'ip_address' => $request->ip() ?? '127.0.0.1',
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                ]);

                return $settlement;
            });
        } catch (QueryException $e) {
            report($e);
            return back()->withErrors(['payment' => 'This bank transaction reference may already be in use. The payment was not approved.']);
        }

        $message = ($result['status'] ?? '') === 'REFUND_PENDING'
            ? 'Bank credit verified and recorded. The order could not be safely fulfilled after its prior cancellation/expiry and is held for authorized refund review; no automatic refund was sent.'
            : 'Payment verified and approved. The order has entered the existing post-payment settlement/sourcing workflow.';

        return redirect()->route('admin.manual-bank-transfers.index')->with('status', $message);
    }

    public function reject(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);
        $validated = $request->validate([
            'decision_reason' => ['nullable', 'string', 'min:5', 'max:2000'],
            'rejection_reason' => ['nullable', 'string', 'min:5', 'max:2000'],
        ]);
        $reason = trim((string) ($validated['decision_reason'] ?? $validated['rejection_reason'] ?? ''));
        if (strlen($reason) < 5) {
            throw ValidationException::withMessages(['decision_reason' => 'A valid decision reason is required (at least 5 characters).']);
        }

        DB::transaction(function () use ($id, $reason, $request) {
            $payment = ManualBankTransferPayment::whereKey($id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            if (!in_array($payment->status, ['pending_review', 'evidence_requested'], true)) {
                throw ValidationException::withMessages(['payment' => 'This payment submission has already been decided.']);
            }
            if ($order->payment_status === 'paid' || $order->paid_at) {
                throw ValidationException::withMessages(['payment' => 'A paid order cannot have its payment rejected. Create an exception case instead.']);
            }

            $payment->status = 'rejected';
            $payment->reviewed_by_admin_id = $request->user()->id;
            $payment->reviewed_at = now();
            $payment->decision_reason = $reason;
            $payment->save();

            $order->payment_status = 'failed';
            $order->sourcing_status = 'payment_rejected';
            $order->expires_at = now();
            $order->save();

            AdminAuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'manual_bank_transfer_payment_rejected',
                'target_type' => ManualBankTransferPayment::class,
                'target_id' => $payment->id,
                'old_values' => ['status' => $payment->getOriginal('status'), 'order_payment_status' => 'pending'],
                'new_values' => [
                    'status' => 'rejected',
                    'order_id' => $order->id,
                    'order_payment_status' => 'failed',
                    'reason' => $payment->decision_reason,
                    'sourcing_started' => false,
                ],
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        });

        return redirect()->route('admin.manual-bank-transfers.index')
            ->with('status', 'Payment evidence rejected. The order remains unpaid and cannot receive another payment submission.');
    }

    public function requestReplacementEvidence(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);
        $reason = (string) ($request->input('reason') ?: $request->input('request_reason'));
        if (strlen(trim($reason)) < 3) {
            return back()->withErrors(['reason' => 'A valid reason is required (at least 3 characters).']);
        }

        $payment = ManualBankTransferPayment::whereKey($id)->firstOrFail();
        if ($payment->status !== 'pending_review') {
            return back()->with('error', 'Only a payment with pending review can request replacement evidence.');
        }

        $payment->status = 'evidence_requested';
        $payment->decision_reason = $reason;
        $payment->reviewed_by_admin_id = $request->user()->id;
        $payment->save();

        AdminAuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'manual_bank_transfer_replacement_requested',
            'target_type' => ManualBankTransferPayment::class,
            'target_id' => $payment->id,
            'old_values' => ['status' => 'pending_review'],
            'new_values' => [
                'status' => 'evidence_requested',
                'reason' => $reason,
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return redirect()->route('admin.manual-bank-transfers.index')
            ->with('status', 'Replacement evidence requested. The customer may upload replacement receipt while review is open.');
    }

    public function evidence(int $id)
    {
        $payment = ManualBankTransferPayment::whereKey($id)->firstOrFail();
        $expectedPrefix = 'manual-bank-transfer/' . $payment->order_id . '/';
        abort_unless(str_starts_with($payment->evidence_path, $expectedPrefix), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($payment->evidence_path), 404);
        $absolutePath = $disk->path($payment->evidence_path);

        return response()->file($absolutePath, [
            'Content-Type' => $payment->evidence_mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Frame-Options' => 'DENY',
        ]);
    }

    // =========================================================================
    // MANUAL BANK TRANSFER REFUND RESOLUTION WORKFLOW
    // =========================================================================


    public function approveRefund(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);
        $validated = $request->validate([
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $refundCase = ManualBankTransferRefundCase::whereKey($id)->firstOrFail();
        if ($refundCase->status !== 'pending_admin_review') {
            return back()->with('error', 'Only a pending manual refund case can be approved for dispatch.');
        }

        $refundCase->update([
            'status' => 'approved_for_dispatch',
            'reviewed_by_admin_id' => $request->user()->id,
            'reviewed_at' => now(),
            'decision_notes' => $validated['decision_notes'] ?? null,
        ]);

        AdminAuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'manual_bank_transfer_refund_approved',
            'target_type' => ManualBankTransferRefundCase::class,
            'target_id' => $refundCase->id,
            'old_values' => ['status' => 'pending_admin_review'],
            'new_values' => [
                'status' => 'approved_for_dispatch',
                'reviewed_by_admin_id' => $request->user()->id,
                'decision_notes' => $refundCase->decision_notes,
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return redirect()->route('admin.manual-bank-transfers.index')
            ->with('status', 'Manual refund approved for wire dispatch. Execute external bank transfer to customer.');
    }

    public function dispatchRefund(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);

        if (now()->isSunday()) {
            return back()->with('error', 'Refund disbursements occur Monday through Saturday only.');
        }

        $validated = $request->validate([
            'manual_dispatch_reference' => ['required', 'string', 'min:3', 'max:150', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'dispatch_proof' => ['required', 'file', 'mimes:jpeg,jpg,png,pdf,webp', 'max:10240'],
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $refundCase = ManualBankTransferRefundCase::whereKey($id)->firstOrFail();
        if (!in_array($refundCase->status, ['approved_for_dispatch', 'pending_admin_review'], true)) {
            return back()->with('error', 'Only an approved or pending manual refund case can be dispatched.');
        }

        $dispatchRef = trim($validated['manual_dispatch_reference']);

        // Prevent duplicate dispatch reference across all payment/settlement records
        if (ManualBankTransferRefundCase::where('manual_dispatch_reference', $dispatchRef)->where('id', '!=', $refundCase->id)->exists()
            || RetailerSupplierPayoutRequest::where('bank_transaction_reference', $dispatchRef)->exists()
            || LogisticsPayoutRequest::where('bank_transaction_reference', $dispatchRef)->exists()
            || ManualBankTransferPayment::where('bank_transaction_reference', $dispatchRef)->exists()) {
            throw ValidationException::withMessages([
                'manual_dispatch_reference' => 'This bank reference has already been used in the system and cannot be reused.',
            ]);
        }

        $file = $request->file('dispatch_proof');
        $ext = strtolower((string) $file->extension());
        $proofPath = $file->storeAs(
            'settlement-proofs/refunds',
            $refundCase->id . '_' . Str::uuid() . '.' . $ext,
            'local'
        );

        if (!$proofPath) {
            return back()->with('error', 'Failed to securely store outgoing refund dispatch proof.');
        }

        $refundCase->update([
            'status' => 'dispatched_awaiting_reconciliation',
            'manual_dispatch_reference' => $dispatchRef,
            'manual_dispatch_proof_path' => $proofPath,
            'reviewed_by_admin_id' => $request->user()->id,
            'reviewed_at' => now(),
            'decision_notes' => trim(($refundCase->decision_notes ? $refundCase->decision_notes . "\n" : '') . ($validated['decision_notes'] ?? '')),
        ]);

        AdminAuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'manual_bank_transfer_refund_dispatched',
            'target_type' => ManualBankTransferRefundCase::class,
            'target_id' => $refundCase->id,
            'old_values' => ['status' => $refundCase->getOriginal('status')],
            'new_values' => [
                'status' => 'dispatched_awaiting_reconciliation',
                'manual_dispatch_reference' => $dispatchRef,
                'manual_dispatch_proof_path' => $proofPath,
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return redirect()->route('admin.manual-bank-transfers.index')
            ->with('status', 'Manual bank refund dispatched successfully. Reconcile with corporate bank statement debit once cleared.');
    }

    public function reconcileRefund(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);

        $refundCase = ManualBankTransferRefundCase::with(['order', 'payment'])->whereKey($id)->firstOrFail();
        if ($refundCase->status !== 'dispatched_awaiting_reconciliation') {
            return back()->with('error', 'Only a dispatched refund case awaiting reconciliation can be reconciled.');
        }

        DB::transaction(function () use ($refundCase, $request) {
            $order = Order::whereKey($refundCase->order_id)->lockForUpdate()->firstOrFail();

            $nairaAmount = bcdiv((string) $refundCase->amount_kobo, '100', 2);
            $eventId = 'EVT-MANUAL-REFUND-' . $refundCase->id;

            FinancialJournalEntry::firstOrCreate([
                'order_id' => $order->id,
                'transaction_reference' => $refundCase->manual_dispatch_reference,
                'event_id' => $eventId,
                'idempotency_key' => 'MANUAL-REFUND-' . $refundCase->id . '-DR',
            ], [
                'beneficiary_type' => User::class,
                'beneficiary_id' => $order->customer_id,
                'account_name' => 'escrow_holding',
                'entry_type' => 'debit',
                'amount' => $nairaAmount,
                'currency' => $refundCase->currency,
                'description' => 'Reversal of customer escrow holding upon confirmed manual bank refund.',
            ]);

            FinancialJournalEntry::firstOrCreate([
                'order_id' => $order->id,
                'transaction_reference' => $refundCase->manual_dispatch_reference,
                'event_id' => $eventId,
                'idempotency_key' => 'MANUAL-REFUND-' . $refundCase->id . '-CR',
            ], [
                'beneficiary_type' => User::class,
                'beneficiary_id' => $order->customer_id,
                'account_name' => 'cash_in_escrow',
                'entry_type' => 'credit',
                'amount' => $nairaAmount,
                'currency' => $refundCase->currency,
                'description' => 'Outflow of customer funds via confirmed manual corporate bank refund.',
            ]);

            $order->payment_status = 'refunded';
            $order->save();

            $returnCase = ReturnCase::where('order_id', $order->id)->whereIn('status', ['approved', 'under_review'])->first();
            if ($returnCase) {
                $returnCase->status = 'refunded';
                $returnCase->save();
            }

            $refundCase->update([
                'status' => 'completed',
                'manual_dispatch_confirmed_at' => now(),
            ]);

            AdminAuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'manual_bank_transfer_refund_reconciled',
                'target_type' => ManualBankTransferRefundCase::class,
                'target_id' => $refundCase->id,
                'old_values' => ['status' => 'dispatched_awaiting_reconciliation'],
                'new_values' => [
                    'status' => 'completed',
                    'manual_dispatch_confirmed_at' => now()->toIso8601String(),
                ],
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        });

        return redirect()->route('admin.manual-bank-transfers.index')
            ->with('status', 'Manual bank refund reconciled, marked completed, and balanced in the double-entry financial journal.');
    }

    public function rejectRefund(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $refundCase = ManualBankTransferRefundCase::whereKey($id)->firstOrFail();
        if (in_array($refundCase->status, ['completed', 'dispatched_awaiting_reconciliation'], true)) {
            return back()->with('error', 'Cannot reject a dispatched or completed refund case.');
        }

        $refundCase->update([
            'status' => 'rejected',
            'reviewed_by_admin_id' => $request->user()->id,
            'reviewed_at' => now(),
            'decision_notes' => $validated['rejection_reason'],
        ]);

        AdminAuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'manual_bank_transfer_refund_rejected',
            'target_type' => ManualBankTransferRefundCase::class,
            'target_id' => $refundCase->id,
            'old_values' => ['status' => $refundCase->getOriginal('status')],
            'new_values' => [
                'status' => 'rejected',
                'reason' => $validated['rejection_reason'],
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return redirect()->route('admin.manual-bank-transfers.index')
            ->with('status', 'Manual refund case rejected.');
    }

    public function refundProof(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);
        $refundCase = ManualBankTransferRefundCase::whereKey($id)->firstOrFail();

        if (empty($refundCase->manual_dispatch_proof_path)) {
            abort(404, 'No refund dispatch proof attached.');
        }

        $disk = Storage::disk('local');
        abort_unless($disk->exists($refundCase->manual_dispatch_proof_path), 404);

        $path = $disk->path($refundCase->manual_dispatch_proof_path);
        $mime = $disk->mimeType($refundCase->manual_dispatch_proof_path) ?: 'application/octet-stream';

        return response()->file($path, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Frame-Options' => 'DENY',
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user || !in_array($user->user_type, ['super_admin', 'admin', 'admin_employee'], true)) {
            abort(403, 'Admin access required.');
        }
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->isSuperAdmin() || $user->status !== 'active') {
            abort(403, 'An active Super Admin account is required for this operation.');
        }
    }
}
