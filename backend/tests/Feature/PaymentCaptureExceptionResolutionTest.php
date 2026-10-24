<?php

namespace Tests\\Feature;

use App\\Models\\PaymentCaptureException;
use App\\Models\\PaymentCaptureExceptionResolution;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class PaymentCaptureExceptionResolutionTest extends TestCase
{
    use RefreshDatabase;

    private User $adminOne;
    private User $adminTwo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminOne = User::factory()->create([
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);
        $this->adminTwo = User::factory()->create([
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);
    }

    private function createException(array $overrides = []): PaymentCaptureException
    {
        return PaymentCaptureException::create(array_merge([
            'order_id' => null,
            'gateway_provider' => 'paystack',
            'gateway_transaction_id' => 'TX-DUPLICATE-002',
            'payment_reference' => 'ORDER-REFERENCE-001',
            'amount_kobo' => 250000,
            'currency' => 'NGN',
            'exception_type' => 'distinct_successful_capture',
            'idempotency_key' => 'CAPTURE-EXCEPTION-' . uniqid('', true),
            'status' => 'pending_admin_review',
            'evidence' => ['gateway_status' => 'success', 'channel' => 'card'],
            'detected_at' => now(),
        ], $overrides));
    }

    public function test_distinct_capture_creates_manual_instruction_without_dispatching_refund(): void
    {
        $exception = $this->createException();

        $this->actingAs($this->adminOne)->post(route('admin.payment-capture-exceptions.prepare', $exception->id), [
            'resolution_type' => 'manual_refund_required',
            'reason' => 'Gateway evidence proves this is a second successful capture.',
        ])->assertRedirect()->assertSessionHas('success');

        $resolution = PaymentCaptureExceptionResolution::where('payment_capture_exception_id', $exception->id)->firstOrFail();
        $this->assertSame('pending_manual_refund', $resolution->status);
        $this->assertSame('TX-DUPLICATE-002', $resolution->captured_transaction_id);
        $this->assertSame(250000, (int) $resolution->captured_amount_kobo);
        $this->assertSame('refund_instruction_pending', $exception->fresh()->status);
        $this->assertDatabaseMissing('gateway_attempts', [
            'gateway_reference' => 'TX-DUPLICATE-002',
            'attempt_type' => 'refund',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'payment_capture_exception_resolution_prepared',
            'target_type' => PaymentCaptureException::class,
            'target_id' => $exception->id,
        ]);
    }

    public function test_identity_unproven_capture_cannot_be_given_a_refund_instruction(): void
    {
        $exception = $this->createException([
            'exception_type' => 'payment_identity_unproven',
            'gateway_transaction_id' => null,
        ]);

        $this->actingAs($this->adminOne)->from('/admin/payment-capture-exceptions')->post(
            route('admin.payment-capture-exceptions.prepare', $exception->id),
            [
                'resolution_type' => 'manual_refund_required',
                'reason' => 'Capture identity has not been independently proven.',
            ]
        )->assertSessionHasErrors('resolution_type');

        $this->assertDatabaseMissing('payment_capture_exception_resolutions', [
            'payment_capture_exception_id' => $exception->id,
        ]);
        $this->assertSame('pending_admin_review', $exception->fresh()->status);
    }

    public function test_manual_refund_confirmation_requires_different_admin_and_exact_amount(): void
    {
        $exception = $this->createException();
        $this->actingAs($this->adminOne)->post(route('admin.payment-capture-exceptions.prepare', $exception->id), [
            'resolution_type' => 'manual_refund_required',
            'reason' => 'Distinct capture was verified against gateway transaction evidence.',
        ])->assertRedirect();

        $resolution = PaymentCaptureExceptionResolution::where('payment_capture_exception_id', $exception->id)->firstOrFail();

        $this->actingAs($this->adminOne)->post(route('admin.payment-capture-exceptions.confirm-manual-refund', $resolution->id), [
            'refund_reference' => 'RFND-DUP-002',
            'refunded_amount_kobo' => 250000,
            'confirmation_notes' => 'Refund completed manually in Paystack dashboard and matched.',
        ])->assertSessionHasErrors('refund_reference');

        $this->actingAs($this->adminTwo)->post(route('admin.payment-capture-exceptions.confirm-manual-refund', $resolution->id), [
            'refund_reference' => 'RFND-DUP-002',
            'refunded_amount_kobo' => 249999,
            'confirmation_notes' => 'Attempted partial refund confirmation must be rejected.',
        ])->assertSessionHasErrors('refunded_amount_kobo');

        $this->assertSame('pending_manual_refund', $resolution->fresh()->status);

        $this->actingAs($this->adminTwo)->post(route('admin.payment-capture-exceptions.confirm-manual-refund', $resolution->id), [
            'refund_reference' => 'RFND-DUP-002',
            'refunded_amount_kobo' => 250000,
            'confirmation_notes' => 'Refund completed manually in Paystack dashboard and matched.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('refund_confirmed', $resolution->fresh()->status);
        $this->assertSame('resolved_refunded', $exception->fresh()->status);
        $this->assertSame((int) $this->adminTwo->id, (int) $resolution->fresh()->confirmed_by_admin_id);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'payment_capture_exception_manual_refund_confirmed',
            'target_type' => PaymentCaptureException::class,
            'target_id' => $exception->id,
        ]);
    }
}
