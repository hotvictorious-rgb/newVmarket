<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivedWebhookEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'original_id',
        'event_id',
        'event_type',
        'payload_hash',
        'status',
        'error_reason',
        'attempts',
        'payload',
        'processed_at',
        'original_created_at',
        'original_updated_at',
        'archived_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'original_created_at' => 'datetime',
        'original_updated_at' => 'datetime',
        'archived_at' => 'datetime',
        'attempts' => 'integer',
        'original_id' => 'integer',
    ];
}
