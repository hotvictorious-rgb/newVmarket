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
        'amount' => 'string',
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
        return Carbon::now()->gte($this->matures_at);
    }

    public function isExpired(): bool
    {
        return Carbon::now()->gt($this->expires_at);
    }
}
