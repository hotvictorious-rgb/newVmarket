<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class CustomerCashbackLedger extends Model
{
    protected $fillable = [
        'customer_id',
        'order_id',
        'amount',
        'status',
        'matures_at',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'matures_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function isMatured(): bool
    {
        // Cashback has no maturity clock until fulfilment establishes the claim-window expiry.
        return $this->matures_at !== null && Carbon::now()->gte($this->matures_at);
    }

    public function isExpired(): bool
    {
        // The validity period starts at maturity; unfulfilled cashback cannot expire yet.
        return $this->expires_at !== null && Carbon::now()->gt($this->expires_at);
    }
}
