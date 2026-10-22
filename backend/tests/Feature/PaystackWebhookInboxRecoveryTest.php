<?php

namespace Tests\Feature;

use App\Models\ProcessedWebhookEvent;
use App\Services\FinancialReconciliationEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PaystackWebhookInboxRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'sk_test_webhook_inbox_recovery_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.paystack.secret_key', $this->secret);
        Config::set('services.paystack.simulate_in_tests', false);
    }

    private function signedWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha512', $raw, $this->secret);

        return $this->call(
            'POST',
            route('payment.paystack.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            ],
            $raw
        );
    }

    public function test_completed_webhook_replay_returns_success_without_reprocessing(): void
    {
        $payload = [
            'id' => 810001,
            'event' => 'customeridentification.success',
            'data' => ['reference' => 'VM-INBOX-REPLAY-001'],
        ];

        $first = $this->signedWebhook($payload);
        $first->assertOk();

        $event = ProcessedWebhookEvent::where('event_id', '810001')->firstOrFail();
        $this->assertSame('completed', $event->status);
        $this->assertSame(1, (int) $event->attempts);
        $processedAt = $event->processed_at;

        $second = $this->signedWebhook($payload);
        $second->assertOk();
        $second->assertJson(['status' => true, 'message' => 'Event already processed.']);

        $event->refresh();
        $this->assertSame('completed', $event->status);
        $this->assertSame(1, (int) $event->attempts);
        $this->assertEquals($processedAt, $event->processed_at);
        $this->assertSame(1, ProcessedWebhookEvent::where('event_id', '810001')->count());
    }

    public function test_reusing_webhook_event_id_with_different_signed_payload_is_rejected(): void
    {
        $firstPayload = [
            'id' => 810002,
            'event' => 'customeridentification.success',
            'data' => ['reference' => 'VM-INBOX-ORIGINAL'],
        ];

        $this->signedWebhook($firstPayload)->assertOk();

        $changedPayload = [
            'id' => 810002,
            'event' => 'customeridentification.success',
            'data' => ['reference' => 'VM-INBOX-TAMPERED'],
        ];

        $this->signedWebhook($changedPayload)->assertStatus(409);

        $event = ProcessedWebhookEvent::where('event_id', '810002')->firstOrFail();
        $this->assertSame('completed', $event->status);
        $this->assertSame(1, (int) $event->attempts);
        $this->assertStringContainsString('VM-INBOX-ORIGINAL', (string) $event->payload);
    }

    public function test_stale_processing_webhook_is_moved_to_retryable_failed_state_with_generation_bump(): void
    {
        $event = ProcessedWebhookEvent::create([
            'event_id' => '810003',
            'event_type' => 'customeridentification.success',
            'payload_hash' => hash('sha256', '{"event":"customeridentification.success"}'),
            'payload' => '{"event":"customeridentification.success"}',
            'status' => 'processing',
            'attempts' => 1,
        ]);

        ProcessedWebhookEvent::whereKey($event->id)->update([
            'updated_at' => Carbon::now()->subMinutes(10),
        ]);

        $reconciled = app(FinancialReconciliationEngine::class)->reconcileStaleWebhooks();

        $event->refresh();
        $this->assertSame(1, $reconciled);
        $this->assertSame('failed', $event->status);
        $this->assertSame(2, (int) $event->attempts);
        $this->assertSame('TIMEOUT_STALE_WORKER: Reset for retry.', $event->error_reason);
    }

    public function test_stale_webhook_at_attempt_limit_is_failed_for_manual_audit_without_another_retry(): void
    {
        $event = ProcessedWebhookEvent::create([
            'event_id' => '810004',
            'event_type' => 'customeridentification.success',
            'payload_hash' => hash('sha256', '{"event":"customeridentification.success"}'),
            'payload' => '{"event":"customeridentification.success"}',
            'status' => 'processing',
            'attempts' => 3,
        ]);

        ProcessedWebhookEvent::whereKey($event->id)->update([
            'updated_at' => Carbon::now()->subMinutes(10),
        ]);

        $reconciled = app(FinancialReconciliationEngine::class)->reconcileStaleWebhooks();

        $event->refresh();
        $this->assertSame(0, $reconciled);
        $this->assertSame('failed', $event->status);
        $this->assertSame(3, (int) $event->attempts);
        $this->assertStringContainsString('MAX_ATTEMPTS_EXCEEDED', (string) $event->error_reason);
    }
}
