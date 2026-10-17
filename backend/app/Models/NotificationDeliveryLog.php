<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationDeliveryLog extends Model
{
    use HasFactory;

    protected $table = 'notification_delivery_logs';

    protected $fillable = [
        'channel',
        'recipient',
        'event_type',
        'title',
        'status',
        'gateway_response',
        'error_message',
    ];

    public static function log(
        string $channel,
        string $recipient,
        string $eventType,
        ?string $title,
        string $status,
        mixed $response = null,
        mixed $error = null
    ): self {
        return static::create([
            'channel' => $channel,
            'recipient' => $recipient,
            'event_type' => $eventType,
            'title' => $title,
            'status' => $status,
            'gateway_response' => is_array($response) ? json_encode($response) : (string)$response,
            'error_message' => is_array($error) ? json_encode($error) : (string)$error,
        ]);
    }
}
