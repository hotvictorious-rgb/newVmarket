<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Carbon\Carbon;

class FinancialOperation extends Model
{
    protected $fillable = [
        'operation_reference',
        'idempotency_key',
        'operation_type',
        'owner_type',
        'owner_id',
        'amount_kobo',
        'currency',
        'status',
        'gateway_provider',
        'gateway_reference',
        'lease_token',
        'lease_expires_at',
        'metadata',
        'confirmed_at',
        'failed_at',
        'reversed_at',
    ];

    protected $casts = [
        'amount_kobo' => 'integer',
        'metadata' => 'array',
        'confirmed_at' => 'datetime',
        'failed_at' => 'datetime',
        'reversed_at' => 'datetime',
        'lease_expires_at' => 'datetime',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function gatewayAttempts(): HasMany
    {
        return $this->hasMany(GatewayAttempt::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(FinancialReservation::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(FinancialJournalEntry::class);
    }

    public function getAmountNairaAttribute(): string
    {
        return bcdiv((string)$this->amount_kobo, '100', 2);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['confirmed', 'failed', 'reversed'], true);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'holding_reservation', 'initiated'], true);
    }
}