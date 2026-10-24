<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\PaymentCaptureException;
use App\Models\PaymentCaptureExceptionResolution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminPaymentCaptureExceptionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeSuperAdmin($request);

        $exceptions = PaymentCaptureException::with(['order', 'resolvedByAdmin'])
            ->with('resolution.preparedByAdmin', 'resolution.confirmedByAdmin')
            ->orderByRaw("CASE WHEN status = 'pending_admin_review' THEN 0 ELSE 1 END")
            ->latest('id')
            ->paginate(30);

        return view('admin.payment-capture-exceptions.index', compact('exceptions'));
    }

    /**
     * Prepare an immutable instruction for a human to handle the exact extra capture.
     * This endpoint never calls Paystack or dispatches money.
     */
    public function prepare(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);
        $data = $request->validate([
            'resolution_type' => ['required', 'in:manual_refund_required,reviewed_no_refund'],
            'reason' => ['required', 'string', 'min:10', 'max:1500'],
        ]);

        $resolution = DB::transaction(function () use ($request, $id, $data) {
            $exception = PaymentCaptureException::whereKey($id)->lockForUpdate()->firstOrFail();

            $existing = PaymentCaptureExceptionResolution::where('payment_capture_exception_id', $exception->id)
                ->lockForUpdate()->first();
            if ($existing) {
                throw ValidationException::withMessages([
                    'resolution_type' => 'A resolution instruction already exists. Do not create a second instruction; reconcile the existing record.',
                ]);
            }
            if ($exception->status !== 'pending_admin_review') {
                throw ValidationException::withMessages([
                    'resolution_type' => 'Only an exception awaiting admin review can be resolved.',
                ]);
            }

            if ($data['resolution_type'] === 'manual_refund_required') {
                if ($exception->exception_type !== 'distinct_successful_capture'
                    || trim((string) $exception->gateway_transaction_id) === ''
                    || trim((string) $exception->payment_reference) === ''
                    || (int) $exception->amount_kobo <= 0
                    || strtoupper((string) $exception->currency) !== 'NGN') {
                    throw ValidationException::withMessages([
                        'resolution_type' => 'A manual refund instruction requires a proven distinct successful capture with an exact transaction ID, payment reference, positive amount and NGN currency. Identity-unproven captures must not be refunded from this workflow.',
                    ]);
                }
            }

            $key = 'CAPTURE-EXCEPTION-' . $exception->id . '-' . hash('sha256',
                $data['resolution_type'] . '|' . $exception->gateway_transaction_id . '|' . $exception->amount_kobo
            );

            $resolution = PaymentCaptureExceptionResolution::create([
                'payment_capture_exception_id' => $exception->id,
                'resolution_type' => $data['resolution_type'],
                'status' => $data['resolution_type'] === 'manual_refund_required'
                    ? 'pending_manual_refund'
                    : 'reviewed_no_refund',
                'gateway_provider' => $exception->gateway_provider,
                'captured_transaction_id' => $exception->gateway_transaction_id,
                'payment_reference' => $exception->payment_reference,
                'captured_amount_kobo' => $exception->amount_kobo,
                'currency' => strtoupper((string) $exception->currency),
                'prepared_by_admin_id' => $request->user()->id,
                'reason' => trim($data['reason']),
                'prepared_at' => now(),
                'idempotency_key' => $key,
            ]);

            $oldStatus = $exception->status;
            $exception->forceFill([
                'status' => $data['resolution_type'] === 'reviewed_no_refund'
                    ? 'resolved_no_refund'
                    : 'refund_instruction_pending',
                'resolved_at' => $data['resolution_type'] === 'reviewed_no_refund' ? now() : null,
                'resolved_by_admin_id' => $data['resolution_type'] === 'reviewed_no_refund' ? $request->user()->id : null,
                'resolution_notes' => trim($data['reason']),
            ])->save();

            AdminAuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'payment_capture_exception_resolution_prepared',
                'target_type' => PaymentCaptureException::class,
                'target_id' => $exception->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => [
                    'resolution_id' => $resolution->id,
                    'resolution_type' => $resolution->resolution_type,
                    'status' => $resolution->status,
                    'captured_transaction_id' => $resolution->captured_transaction_id,
                    'captured_amount_kobo' => $resolution->captured_amount_kobo,
                    'payment_reference' => $resolution->payment_reference,
                    'automatic_refund_dispatched' => false,
                    'reason' => $resolution->reason,
                ],
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => 'Payment capture exception resolution desk',
            ]);

            return $resolution;
        }, 3);

        return back()->with('success', $resolution->resolution_type === 'manual_refund_required'
            ? 'Exact-capture manual refund instruction recorded. No refund was sent. A different Super Admin must confirm the completed gateway refund with matching evidence.'
            : 'Exception reviewed and closed without refund. The decision was audited.');
    }

    /**
     * Record proof of a refund already completed manually in the gateway dashboard.
     * A different active Super Admin must confirm it; this action never calls a gateway.
     */
    public function confirmManualRefund(Request $request, int $id)
    {
        $this->authorizeSuperAdmin($request);
        $data = $request->validate([
            'refund_reference' => ['required', 'string', 'min:4', 'max:120'],
            'refunded_amount_kobo' => ['required', 'integer', 'min:1'],
            'confirmation_notes' => ['required', 'string', 'min:10', 'max:1500'],
        ]);

        DB::transaction(function () use ($request, $id, $data) {
            $resolution = PaymentCaptureExceptionResolution::whereKey($id)->lockForUpdate()->firstOrFail();
            $exception = PaymentCaptureException::whereKey($resolution->payment_capture_exception_id)
                ->lockForUpdate()->firstOrFail();

            if ($resolution->resolution_type !== 'manual_refund_required'
                || $resolution->status !== 'pending_manual_refund'
                || $exception->status !== 'refund_instruction_pending') {
                throw ValidationException::withMessages([
                    'refund_reference' => 'Only an outstanding exact-capture manual refund instruction can be confirmed.',
                ]);
            }
            if ((int) $data['refunded_amount_kobo'] !== (int) $resolution->captured_amount_kobo) {
                throw ValidationException::withMessages([
                    'refunded_amount_kobo' => 'Refunded kobo must exactly equal the identified extra capture. Partial or excess amounts require a separate adjudicated workflow.',
                ]);
            }
            if ((int) $request->user()->id === (int) $resolution->prepared_by_admin_id) {
                throw ValidationException::withMessages([
                    'refund_reference' => 'Dual control required: a different Super Admin must confirm the manually completed gateway refund.',
                ]);
            }

            $resolution->forceFill([
                'status' => 'refund_confirmed',
                'refund_reference' => trim($data['refund_reference']),
                'refunded_amount_kobo' => (int) $data['refunded_amount_kobo'],
                'confirmed_by_admin_id' => $request->user()->id,
                'confirmation_notes' => trim($data['confirmation_notes']),
                'confirmed_at' => now(),
            ])->save();

            $exception->forceFill([
                'status' => 'resolved_refunded',
                'resolved_at' => now(),
                'resolved_by_admin_id' => $request->user()->id,
                'resolution_notes' => trim($data['confirmation_notes']),
            ])->save();

            AdminAuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'payment_capture_exception_manual_refund_confirmed',
                'target_type' => PaymentCaptureException::class,
                'target_id' => $exception->id,
                'old_values' => ['status' => 'refund_instruction_pending'],
                'new_values' => [
                    'resolution_id' => $resolution->id,
                    'captured_transaction_id' => $resolution->captured_transaction_id,
                    'capture_amount_kobo' => $resolution->captured_amount_kobo,
                    'refund_reference' => $resolution->refund_reference,
                    'refunded_amount_kobo' => $resolution->refunded_amount_kobo,
                    'prepared_by_admin_id' => $resolution->prepared_by_admin_id,
                    'confirmed_by_admin_id' => $resolution->confirmed_by_admin_id,
                    'gateway_refund_dispatched_by_application' => false,
                ],
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => 'Payment capture exception resolution desk',
            ]);
        }, 3);

        return back()->with('success', 'Manual gateway refund evidence recorded and dual-control audited. The application did not dispatch the refund.');
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()
            && $request->user()->user_type === 'super_admin'
            && $request->user()->status === 'active', 403);
    }
}
