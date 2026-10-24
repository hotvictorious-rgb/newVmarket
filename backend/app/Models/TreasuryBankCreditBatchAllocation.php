<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreasuryBankCreditBatchAllocation extends Model
{
    protected $fillable = [
        'treasury_bank_credit_batch_id',
        'order_id',
        'gateway_transaction_id',
        'payment_reference',
        'amount_kobo',
        'currency',
        'idempotency_key',
        'added_by_admin_id',
        'added_at',
    ];

    protected $casts = [
        'amount_kobo' => 'integer',
        'added_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(TreasuryBankCreditBatch::class, 'treasury_bank_credit_batch_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function addedByAdmin()
    {
        return $this->belongsTo(User::class, 'added_by_admin_id');
    }
}
