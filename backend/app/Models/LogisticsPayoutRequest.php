<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsPayoutRequest extends Model
{
    protected $fillable = [
        'logistics_company_id',
        'amount',
        'status',
        'bank_name',
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

    public function company()
    {
        return $this->belongsTo(LogisticsCompanyProfile::class, 'logistics_company_id');
    }

    public function approvedByAdmin()
    {
        return $this->belongsTo(User::class, 'approved_by_admin_id');
    }
}