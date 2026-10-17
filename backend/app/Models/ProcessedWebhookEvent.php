<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedWebhookEvent extends Model
{
    protected $fillable = [
        'event_id',
        'event_type',
        'payload_hash',
        'status',
        'error_reason',
        'attempts',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'attempts' => 'integer',
    ];
}