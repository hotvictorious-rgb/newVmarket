<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreasuryBankCreditBatch extends Model
{
    protected $fillable = [
        'gateway_provider',
        'gateway_settlement_reference',
        'bank_credit_reference',
        'corporate_account_last4',
        'net_credit_amount_kobo',
        'gross_collection_amount_kobo',
        'provider_fee_amount_kobo',
        'currency',
        'statement_value_date',
        'statement_evidence_reference',
        'reconciliation_notes',
        'status',
        'prepared_by_admin_id',
        'confirmed_by_admin_id',
        'prepared_at',
        'confirmed_at',
        'idempotency_key',
    ];

    protected $casts = [
        'net_credit_amount_kobo' => 'integer',
        'gross_collection_amount_kobo' => 'integer',
        'provider_fee_amount_kobo' => 'integer',
        'statement_value_date' => 'date',
        'prepared_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function preparedByAdmin()
    {
        return $this->belongsTo(User::class, 'prepared_by_admin_id');
    }

    public function confirmedByAdmin()
    {
        return $this->belongsTo(User::class, 'confirmed_by_admin_id');
    }

    public function allocations()
    {
        return $this->hasMany(TreasuryBankCreditBatchAllocation::class, 'treasury_bank_credit_batch_id');
    }

    public function supplierPayouts()
    {
        return $this->hasMany(RetailerSupplierPayoutRequest::class, 'treasury_bank_credit_batch_id');
    }

    public function logisticsPayouts()
    {
        return $this->hasMany(LogisticsPayoutRequest::class, 'treasury_bank_credit_batch_id');
    }
}
