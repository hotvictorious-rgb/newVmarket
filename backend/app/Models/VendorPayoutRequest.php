<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorPayoutRequest extends Model
{
    protected $fillable = [
        'vendor_id',
        'amount',
        'status',
        'bank_name',
        'bank_code',
        'account_number',
        'account_name',
        'approved_by_admin_id',
        'disbursed_at',
        'settlement_proof_path',
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

    /**
     * A payout reserves earnings only while its outcome is unresolved.
     * A successful transfer is terminal even though the legacy status remains "approved".
     */
    public function scopeActiveReservation($query)
    {
        return $query
            ->whereIn('status', ['pending', 'approved', 'processing', 'in_flight', 'uncertain'])
            ->where(function ($gateway) {
                $gateway->whereNull('gateway_status')
                    ->orWhereNotIn('gateway_status', ['success', 'failed', 'reversed']);
            });
    }

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function approvedByAdmin()
    {
        return $this->belongsTo(User::class, 'approved_by_admin_id');
    }

    public function allocations()
    {
        return $this->hasMany(VendorPayoutAllocation::class, 'vendor_payout_request_id');
    }
}