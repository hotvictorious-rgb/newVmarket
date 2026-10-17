<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTermiiNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;
    public int $timeout = 15;

    public function __construct(
        public string $recipient,
        public string $channel,
        public string $message,
        public ?string $senderId = null
    ) {}

    public function handle(): void
    {
        $apiKey = config('services.termii.key', env('TERMII_API_KEY'));
        if (empty($apiKey)) {
            return;
        }

        $sender = $this->senderId ?? config('services.termii.sender_id', env('TERMII_SENDER_ID', 'VMARKET'));
        $termiiEndpoint = 'https://api.ng.termii.com/api/sms/send';

        try {
            $response = Http::timeout(5)->post($termiiEndpoint, [
                'to' => $this->recipient,
                'from' => $sender,
                'sms' => $this->message,
                'type' => 'plain',
                'channel' => strtolower($this->channel) === 'whatsapp' ? 'whatsapp' : 'generic',
                'api_key' => $apiKey,
            ]);

            if ($response->successful()) {
                Log::info("Async Termii notification delivered to {$this->recipient}", [
                    'provider_ref' => $response->json('message_id'),
                ]);
            } else {
                Log::warning("Async Termii dispatch non-200 to {$this->recipient}: " . $response->body());
            }
        } catch (\Throwable $e) {
            Log::error("Async Termii dispatch exception to {$this->recipient}: " . $e->getMessage());
            throw $e;
        }
    }
}
