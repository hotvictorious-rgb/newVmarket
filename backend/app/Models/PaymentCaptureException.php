<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentCaptureException extends Model
{
    protected $fillable = [
        'order_id',
        'gateway_provider',
        'gateway_transaction_id',
        'payment_reference',
        'amount_kobo',
        'currency',
        'exception_type',
        'idempotency_key',
        'status',
        'evidence',
        'detected_at',
        'resolved_at',
        'resolved_by_admin_id',
        'resolution_notes',
    ];

    protected $casts = [
        'amount_kobo' => 'integer',
        'evidence' => 'array',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function resolvedByAdmin()
    {
        return $this->belongsTo(User::class, 'resolved_by_admin_id');
    }
}
