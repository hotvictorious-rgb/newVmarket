<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsPayoutRequest extends Model
{
    protected $fillable = [
        'logistics_company_id',
        'treasury_bank_credit_batch_id',
        'amount',
        'status',
        'bank_name',
        'account_number',
        'account_name',
        'approved_by_admin_id',
        'disbursed_at',
        'settlement_proof_path',
        'bank_transaction_reference',
        'transfer_reference',
        'gateway_status',
        'paystack_transfer_code',
        'processing_token',
        'processing_started_at',
        'admin_notes',
        'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'disbursed_at' => 'datetime',
        'processing_started_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(LogisticsCompanyProfile::class, 'logistics_company_id');
    }

    public function approvedByAdmin()
    {
        return $this->belongsTo(User::class, 'approved_by_admin_id');
    }

    public function treasuryBankCreditBatch()
    {
        return $this->belongsTo(TreasuryBankCreditBatch::class, 'treasury_bank_credit_batch_id');
    }
}