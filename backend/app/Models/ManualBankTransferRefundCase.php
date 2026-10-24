<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualBankTransferRefundCase extends Model
{
    protected $fillable = [
        'order_id',
        'manual_bank_transfer_payment_id',
        'idempotency_key',
        'amount_kobo',
        'currency',
        'status',
        'reason',
        'created_by_admin_id',
        'reviewed_by_admin_id',
        'reviewed_at',
        'decision_notes',
        'manual_dispatch_reference',
        'manual_dispatch_proof_path',
        'manual_dispatch_confirmed_at',
    ];

    protected $hidden = [
        'manual_dispatch_proof_path',
    ];

    protected $casts = [
        'amount_kobo' => 'integer',
        'reviewed_at' => 'datetime',
        'manual_dispatch_confirmed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payment()
    {
        return $this->belongsTo(ManualBankTransferPayment::class, 'manual_bank_transfer_payment_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_admin_id');
    }
}
