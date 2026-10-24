<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetailerSupplierPayoutRequest extends Model
{
    protected $fillable = [
        'supplier_vendor_id',
        'treasury_bank_credit_batch_id',
        'amount',
        'status',
        'gateway_status',
        'transfer_reference',
        'paystack_transfer_code',
        'recipient_code',
        'processing_token',
        'processing_started_at',
        'bank_name',
        'bank_code',
        'account_number',
        'account_name',
        'approved_by_admin_id',
        'disbursed_at',
        'settlement_proof_path',
        'bank_transaction_reference',
        'rejection_reason',
        'admin_notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processing_started_at' => 'datetime',
        'disbursed_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(VendorProfile::class, 'supplier_vendor_id');
    }

    public function treasuryBankCreditBatch()
    {
        return $this->belongsTo(TreasuryBankCreditBatch::class, 'treasury_bank_credit_batch_id');
    }

    public function allocations()
    {
        return $this->hasMany(RetailerSupplierPayoutAllocation::class, 'retailer_supplier_payout_request_id');
    }
}
