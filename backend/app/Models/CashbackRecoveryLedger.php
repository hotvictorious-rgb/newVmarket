<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashbackRecoveryLedger extends Model
{
    protected $fillable = [
        'customer_id',
        'order_id',
        'return_case_id',
        'amount',
        'recovered_amount',
        'status',
        'idempotency_key',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'recovered_amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function returnCase()
    {
        return $this->belongsTo(ReturnCase::class);
    }

    public function outstandingAmount(): string
    {
        $outstanding = \App\Services\BcMathService::sub((string) $this->amount, (string) $this->recovered_amount);
        return \App\Services\BcMathService::comp($outstanding, '0.00') > 0 ? $outstanding : '0.00';
    }
}
