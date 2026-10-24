<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;

class PaymentCaptureExceptionResolution extends Model
{
    protected $fillable = [
        'payment_capture_exception_id',
        'resolution_type',
        'status',
        'gateway_provider',
        'captured_transaction_id',
        'payment_reference',
        'captured_amount_kobo',
        'currency',
        'refund_reference',
        'refunded_amount_kobo',
        'prepared_by_admin_id',
        'confirmed_by_admin_id',
        'reason',
        'confirmation_notes',
        'prepared_at',
        'confirmed_at',
        'idempotency_key',
    ];

    protected $casts = [
        'captured_amount_kobo' => 'integer',
        'refunded_amount_kobo' => 'integer',
        'prepared_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function captureException()
    {
        return $this->belongsTo(PaymentCaptureException::class, 'payment_capture_exception_id');
    }

    public function preparedByAdmin()
    {
        return $this->belongsTo(User::class, 'prepared_by_admin_id');
    }

    public function confirmedByAdmin()
    {
        return $this->belongsTo(User::class, 'confirmed_by_admin_id');
    }
}
