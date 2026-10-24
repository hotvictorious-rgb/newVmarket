<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualBankTransferPayment extends Model
{
    protected $fillable = [
        'order_id',
        'customer_id',
        'evidence_path',
        'evidence_mime',
        'evidence_size_bytes',
        'evidence_sha256',
        'status',
        'bank_transaction_reference',
        'credited_amount_kobo',
        'currency',
        'reviewed_by_admin_id',
        'submitted_at',
        'reviewed_at',
        'decision_reason',
        'evidence_history',
    ];

    protected $hidden = [
        'evidence_path',
        'evidence_sha256',
        'bank_transaction_reference',
    ];

    protected $casts = [
        'evidence_size_bytes' => 'integer',
        'credited_amount_kobo' => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'evidence_history' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_admin_id');
    }
}
