<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsCompanyProfile extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'registration_number',
        'contact_email',
        'contact_phone',
        'hq_address',
        'lga_id',
        'fleet_size',
        'commission_rate',
        'wallet_balance',
        'bank_name',
        'bank_code',
        'account_number',
        'account_name',
        'bank_updated_at',
        'status',
    ];

    protected $casts = [
        'wallet_balance' => 'decimal:2',
        'bank_updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function riders()
    {
        return $this->hasMany(RiderProfile::class, 'logistics_company_id');
    }

    public function collectionPoints()
    {
        return $this->hasMany(LogisticsCollectionPoint::class, 'logistics_company_id');
    }

    public function payoutRequests()
    {
        return $this->hasMany(LogisticsPayoutRequest::class, 'logistics_company_id');
    }

    public function hqLga()
    {
        return $this->belongsTo(Lga::class, 'lga_id');
    }

    /**
     * Institutional Bank Cooldown: 48-hour security window after bank account modifications.
     */
    public function isWithinBankCooldown(): bool
    {
        if (!$this->bank_updated_at) {
            return false;
        }

        return \Carbon\Carbon::now()->lt($this->bank_updated_at->copy()->addHours(48));
    }

    /**
     * Credit corporate escrow wallet balance using BCMath.
     */
    public function creditWallet(string $amount): void
    {
        $current = $this->wallet_balance ?? '0.00';
        $sum = \App\Services\BcMathService::add($current, $amount);
        $this->wallet_balance = number_format((float)$sum, 2, '.', '');
        $this->save();
    }

    /**
     * Debit corporate escrow wallet balance using BCMath.
     */
    public function debitWallet(string $amount): void
    {
        $current = $this->wallet_balance ?? '0.00';
        $difference = \App\Services\BcMathService::sub($current, $amount);
        $this->wallet_balance = number_format((float)$difference, 2, '.', '');
        $this->save();
    }
}
