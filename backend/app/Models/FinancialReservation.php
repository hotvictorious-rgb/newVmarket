<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinancialReservation extends Model
{
    protected $fillable = [
        'financial_operation_id',
        'source_type',
        'source_id',
        'reserved_amount_kobo',
        'purpose',
        'status',
        'committed_at',
        'released_at',
    ];

    protected $casts = [
        'reserved_amount_kobo' => 'integer',
        'committed_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(FinancialOperation::class, 'financial_operation_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function getReservedAmountNairaAttribute(): string
    {
        return bcdiv((string)$this->reserved_amount_kobo, '100', 2);
    }
}