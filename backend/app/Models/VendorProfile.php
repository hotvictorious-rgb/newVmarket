<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class VendorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'business_name',
        'nin_number',
        'nin_document_path',
        'cac_number',
        'cac_document_path',
        'kyc_status',
        'kyc_reviewed_at',
        'kyc_notes',
        'bank_name',
        'account_number',
        'account_name',
        'bank_code',
        'bank_updated_at',
        'bank_otp_hash',
        'bank_otp_expires_at',
        'marketplace_status',
    ];

    protected $casts = [
        'kyc_reviewed_at' => 'datetime',
        'bank_updated_at' => 'datetime',
        'bank_otp_expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shops()
    {
        return $this->hasMany(Shop::class, 'vendor_id');
    }

    public function primaryShop()
    {
        return $this->hasOne(Shop::class, 'vendor_id')->where('is_primary_branch', true);
    }

    public function employees()
    {
        return $this->hasMany(VendorEmployeeProfile::class, 'vendor_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'vendor_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'vendor_id');
    }

    /**
     * Institutional Bank Cooldown: 48-hour security window after bank account modifications.
     */
    public function isWithinBankCooldown(): bool
    {
        if (!$this->bank_updated_at) {
            return false;
        }

        return Carbon::now()->lt($this->bank_updated_at->copy()->addHours(48));
    }

    public function isBankCooldownActive(): bool
    {
        return $this->isWithinBankCooldown();
    }

    public function isKycVerified(): bool
    {
        return $this->kyc_status === 'verified';
    }
}
