<?php

namespace App\Jobs;

use App\Services\FirebaseCloudMessagingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendFcmNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;
    public int $timeout = 15;

    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public array $data = [],
        public string $eventType = 'general'
    ) {}

    public function handle(FirebaseCloudMessagingService $fcmService): void
    {
        try {
            $result = $fcmService->sendToUser(
                $this->userId,
                $this->title,
                $this->body,
                $this->data,
                $this->eventType
            );

            if ($result['success']) {
                Log::info("Async FCM notification delivered to User #{$this->userId} ({$result['delivered_count']} devices)");
            } else {
                Log::info("Async FCM notification notice for User #{$this->userId}: " . ($result['message'] ?? 'No delivery.'));
            }
        } catch (\Throwable $e) {
            Log::error("Async FCM dispatch exception for User #{$this->userId}: " . $e->getMessage());
            throw $e;
        }
    }
}
