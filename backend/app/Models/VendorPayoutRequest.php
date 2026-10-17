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
        'admin_notes',
        'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'disbursed_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function approvedByAdmin()
    {
        return $this->belongsTo(User::class, 'approved_by_admin_id');
    }
}