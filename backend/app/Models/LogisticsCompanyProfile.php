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
        'status',
    ];

    protected $casts = [
        'wallet_balance' => 'decimal:2',
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

    public function hqLga()
    {
        return $this->belongsTo(Lga::class, 'lga_id');
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
}
