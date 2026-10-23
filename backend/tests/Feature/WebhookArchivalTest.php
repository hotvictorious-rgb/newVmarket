<?php

namespace Tests\Feature;

use App\Models\ArchivedWebhookEvent;
use App\Models\ProcessedWebhookEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WebhookArchivalTest extends TestCase
{
    use RefreshDatabase;

    protected string $secretKey = 'sk_test_archival_mock_secret_key';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.paystack.secret_key', $this->secretKey);
    }

    /**
     * Test completed webhooks older than 90 days are archived, while newer ones are retained.
     */
    public function test_completed_webhooks_older_than_retention_window_are_archived(): void
    {
        $oldDate = Carbon::now()->subDays(95);
        $recentDate = Carbon::now()->subDays(30);

        // 1. Old completed event (eligible)
        $oldCompleted = ProcessedWebhookEvent::create([
            'event_id' => 'evt_old_completed_001',
            'event_type' => 'charge.success',
            'payload_hash' => hash('sha256', '{"data":"old"}'),
            'payload' => '{"data":"old"}',
            'status' => 'completed',
            'attempts' => 1,
            'processed_at' => $oldDate,
            'created_at' => $oldDate,
            'updated_at' => $oldDate,
        ]);

        // 2. Recent completed event (ineligible)
        $recentCompleted = ProcessedWebhookEvent::create([
            'event_id' => 'evt_recent_completed_002',
            'event_type' => 'charge.success',
            'payload_hash' => hash('sha256', '{"data":"recent"}'),
            'payload' => '{"data":"recent"}',
            'status' => 'completed',
            'attempts' => 1,
            'processed_at' => $recentDate,
            'created_at' => $recentDate,
            'updated_at' => $recentDate,
        ]);

        // Execute archival command
        $exitCode = Artisan::call('webhooks:archive', ['--days' => 90]);
        $this->assertSame(0, $exitCode);

        // Assert old event moved to archived_webhook_events
        $this->assertDatabaseMissing('processed_webhook_events', ['event_id' => 'evt_old_completed_001']);
        $this->assertDatabaseHas('archived_webhook_events', [
            'event_id' => 'evt_old_completed_001',
            'status' => 'completed',
            'original_id' => $oldCompleted->id,
        ]);

        // Assert recent completed event stays in processed_webhook_events
        $this->assertDatabaseHas('processed_webhook_events', ['event_id' => 'evt_recent_completed_002']);
        $this->assertDatabaseMissing('archived_webhook_events', ['event_id' => 'evt_recent_completed_002']);
    }

    /**
     * Invariant: Failed, unmatched, and processing events older than 90 days MUST be retained
     * in processed_webhook_events indefinitely for auditability and dispute investigation.
     */
    public function test_failed_and_unmatched_events_are_strictly_retained_in_inbox(): void
    {
        $oldDate = Carbon::now()->subDays(120);

        $oldFailed = ProcessedWebhookEvent::create([
            'event_id' => 'evt_old_failed_003',
            'event_type' => 'charge.success',
            'payload_hash' => hash('sha256', '{"data":"failed"}'),
            'payload' => '{"data":"failed"}',
            'status' => 'failed',
            'error_reason' => 'Database lock timeout during settlement',
            'attempts' => 3,
            'processed_at' => $oldDate,
            'created_at' => $oldDate,
            'updated_at' => $oldDate,
        ]);

        $oldUnmatched = ProcessedWebhookEvent::create([
            'event_id' => 'evt_old_unmatched_004',
            'event_type' => 'charge.success',
            'payload_hash' => hash('sha256', '{"data":"unmatched"}'),
            'payload' => '{"data":"unmatched"}',
            'status' => 'unmatched',
            'error_reason' => 'No order found with reference REF-UNKNOWN-999',
            'attempts' => 1,
            'processed_at' => $oldDate,
            'created_at' => $oldDate,
            'updated_at' => $oldDate,
        ]);

        // Execute archival command
        $exitCode = Artisan::call('webhooks:archive', ['--days' => 90]);
        $this->assertSame(0, $exitCode);

        // Both failed and unmatched must STILL exist in processed_webhook_events
        $this->assertDatabaseHas('processed_webhook_events', ['event_id' => 'evt_old_failed_003', 'status' => 'failed']);
        $this->assertDatabaseHas('processed_webhook_events', ['event_id' => 'evt_old_unmatched_004', 'status' => 'unmatched']);
        $this->assertDatabaseMissing('archived_webhook_events', ['event_id' => 'evt_old_failed_003']);
        $this->assertDatabaseMissing('archived_webhook_events', ['event_id' => 'evt_old_unmatched_004']);
    }

    /**
     * Test dry-run mode leaves database completely unchanged.
     */
    public function test_dry_run_mode_does_not_modify_database(): void
    {
        $oldDate = Carbon::now()->subDays(100);

        ProcessedWebhookEvent::create([
            'event_id' => 'evt_dry_run_005',
            'event_type' => 'charge.success',
            'payload_hash' => hash('sha256', '{"data":"dry"}'),
            'payload' => '{"data":"dry"}',
            'status' => 'completed',
            'attempts' => 1,
            'processed_at' => $oldDate,
            'created_at' => $oldDate,
            'updated_at' => $oldDate,
        ]);

        $exitCode = Artisan::call('webhooks:archive', ['--days' => 90, '--dry-run' => true]);
        $this->assertSame(0, $exitCode);

        // ProcessedWebhookEvent must NOT have been deleted
        $this->assertDatabaseHas('processed_webhook_events', ['event_id' => 'evt_dry_run_005']);
        $this->assertDatabaseMissing('archived_webhook_events', ['event_id' => 'evt_dry_run_005']);
    }

    /**
     * Test replay defense against archived webhooks.
     */
    public function test_replay_of_archived_webhook_returns_200_and_does_not_duplicate(): void
    {
        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'id' => 998877,
                'reference' => 'REF-ARCHIVED-TEST-001',
                'status' => 'success',
                'amount' => 500000,
                'currency' => 'NGN',
            ],
        ]);
        $eventHash = hash('sha256', $payload);

        // Pre-populate directly into archived_webhook_events
        ArchivedWebhookEvent::create([
            'original_id' => 500,
            'event_id' => 'evt_archived_replay_006',
            'event_type' => 'charge.success',
            'payload_hash' => $eventHash,
            'status' => 'completed',
            'attempts' => 1,
            'payload' => $payload,
            'processed_at' => Carbon::now()->subDays(100),
            'original_created_at' => Carbon::now()->subDays(100),
            'original_updated_at' => Carbon::now()->subDays(100),
            'archived_at' => Carbon::now()->subDays(10),
        ]);

        $secretKey = app(\App\Services\PaystackService::class)->getSecretKey();
        $signature = hash_hmac('sha512', $payload, $secretKey);

        $response = $this->withHeaders([
            'x-paystack-signature' => $signature,
            'x-paystack-event-id' => 'evt_archived_replay_006',
        ])->call('POST', '/api/v1/payment/paystack/webhook', [], [], [], [], $payload);

        $response->assertStatus(200);
        $this->assertStringContainsString('Event already processed', (string) $response->getContent());

        // ProcessedWebhookEvent must NOT have created a new row
        $this->assertDatabaseMissing('processed_webhook_events', ['event_id' => 'evt_archived_replay_006']);
    }
}
