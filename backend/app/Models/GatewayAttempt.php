<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatewayAttempt extends Model
{
    protected $fillable = [
        'financial_operation_id',
        'gateway_provider',
        'endpoint',
        'attempt_reference',
        'gateway_reference',
        'request_hash',
        'request_payload',
        'response_status_code',
        'response_payload',
        'outcome',
        'error_message',
        'attempted_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'attempted_at' => 'datetime',
        'response_status_code' => 'integer',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(FinancialOperation::class, 'financial_operation_id');
    }
}