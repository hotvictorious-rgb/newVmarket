<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\ManualBankTransferPayment;
use App\Models\Order;
use App\Models\SystemSetting;
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
     * Customer submits one private receipt for a manual-transfer order.
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

                if (ManualBankTransferPayment::where('order_id', $lockedOrder->id)->exists()) {
                    throw ValidationException::withMessages([
                        'payment_screenshot' => 'A receipt has already been submitted for this order. This order accepts one payment submission only.',
                    ]);
                }

                $mime = (string) $file->getMimeType();
                if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    throw ValidationException::withMessages([
                        'payment_screenshot' => 'The uploaded file content is not an accepted receipt image.',
                    ]);
                }

                $payment = ManualBankTransferPayment::create([
                    'order_id' => $lockedOrder->id,
                    'customer_id' => $lockedOrder->customer_id,
                    'evidence_path' => $relativePath,
                    'evidence_mime' => $mime,
                    'evidence_size_bytes' => (int) $file->getSize(),
                    'evidence_sha256' => hash_file('sha256', Storage::disk('local')->path($relativePath)),
                    'status' => 'pending_review',
                    'currency' => 'NGN',
                    'submitted_at' => now(),
                    'evidence_history' => [[
                        'action' => 'submitted',
                        'sha256' => hash_file('sha256', Storage::disk('local')->path($relativePath)),
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
        $paymentConfig = SystemSetting::get('checkout.payment_configuration', ['active_method' => 'paystack', 'manual_bank_transfer_account' => []]);
        $activePaymentMethod = is_array($paymentConfig) ? (string) ($paymentConfig['active_method'] ?? 'paystack') : 'paystack';
        $bankAccount = is_array($paymentConfig) ? ($paymentConfig['manual_bank_transfer_account'] ?? []) : [];
        $payments = ManualBankTransferPayment::with(['order.customer', 'customer', 'reviewer'])
            ->whereIn('status', ['pending_review', 'approved', 'rejected'])
            ->orderByRaw("CASE WHEN status = 'pending_review' THEN 0 ELSE 1 END")
            ->orderBy('submitted_at')
            ->paginate(25);

        return view('admin.manual-bank-transfers.index', compact('payments', 'activePaymentMethod', 'bankAccount'));
    }

    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'active_payment_method' => ['required', 'in:paystack,manual_bank_transfer,disabled'],
            'bank_name' => ['required_if:active_payment_method,manual_bank_transfer', 'nullable', 'string', 'max:100'],
            'account_name' => ['required_if:active_payment_method,manual_bank_transfer', 'nullable', 'string', 'max:150'],
            'account_number' => ['required_if:active_payment_method,manual_bank_transfer', 'nullable', 'regex:/^[0-9]{10}$/'],
            'customer_instructions' => ['required_if:active_payment_method,manual_bank_transfer', 'nullable', 'string', 'max:2000'],
        ]);

        $oldConfig = SystemSetting::get('checkout.payment_configuration', ['active_method' => 'paystack', 'manual_bank_transfer_account' => []]);
        $account = [
            'bank_name' => trim((string) ($validated['bank_name'] ?? '')),
            'account_name' => trim((string) ($validated['account_name'] ?? '')),
            'account_number' => trim((string) ($validated['account_number'] ?? '')),
            'customer_instructions' => trim((string) ($validated['customer_instructions'] ?? '')),
            'updated_at' => now()->toIso8601String(),
        ];

        DB::transaction(function () use ($validated, $account, $oldConfig, $request) {
            // One atomic JSON setting is the single source of truth; dual activation is unrepresentable.
            $newConfig = [
                'active_method' => $validated['active_payment_method'],
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

        return redirect()->route('admin.manual-bank-transfers.index')
            ->with('status', 'Payment configuration saved. Exactly one payment method is active, or checkout is disabled.');
    }

    public function approve(Request $request, int $id, OrderSettlementService $settlementService)
    {
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

                if ($payment->status !== 'pending_review') {
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
                if (ManualBankTransferPayment::where('bank_transaction_reference', $bankReference)->where('id', '!=', $payment->id)->exists()) {
                    throw ValidationException::withMessages(['bank_transaction_reference' => 'This bank transaction reference has already been assigned to another order.']);
                }
                if (Order::where('payment_reference', $bankReference)->orWhere('paystack_transaction_id', $bankReference)->exists()) {
                    throw ValidationException::withMessages(['bank_transaction_reference' => 'This reference is already used by an order/payment identity and cannot be reused as a manual bank transaction.']);
                }

                $expectedKobo = (int) bcmul(bcadd((string) $order->grand_total, '0', 2), '100', 0);
                if ($amountKobo !== $expectedKobo) {
                    throw ValidationException::withMessages(['credited_amount' => 'The credited amount must exactly match this order total. The payment remains unapproved.']);
                }

                // This method executes the same audited settlement core while explicitly
                // retaining the manual tender identity and never creating a Paystack ID.
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
                    'old_values' => ['status' => 'pending_review'],
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
            // A concurrent approval using the same bank reference is rejected by the DB
            // unique constraint even if both requests passed the pre-check.
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
        $validated = $request->validate([
            'decision_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        DB::transaction(function () use ($id, $validated, $request) {
            $payment = ManualBankTransferPayment::whereKey($id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'pending_review') {
                throw ValidationException::withMessages(['payment' => 'This payment submission has already been decided.']);
            }
            if ($order->payment_status === 'paid' || $order->paid_at) {
                throw ValidationException::withMessages(['payment' => 'A paid order cannot have its payment rejected. Create an exception case instead.']);
            }

            $payment->status = 'rejected';
            $payment->reviewed_by_admin_id = $request->user()->id;
            $payment->reviewed_at = now();
            $payment->decision_reason = trim($validated['decision_reason']);
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
                'old_values' => ['status' => 'pending_review', 'order_payment_status' => 'pending'],
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
}
