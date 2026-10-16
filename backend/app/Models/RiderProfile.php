<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderProfile extends Model
{
    protected $fillable = [
        'user_id',
        'logistics_company_id',
        'assigned_lga_id',
        'vehicle_type',
        'license_plate',
        'nin_number',
        'is_active',
        'is_online',
        'current_latitude',
        'current_longitude',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_online' => 'boolean',
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'current_balance' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function logisticsCompany()
    {
        return $this->belongsTo(LogisticsCompanyProfile::class, 'logistics_company_id');
    }

    public function assignedLga()
    {
        return $this->belongsTo(Lga::class, 'assigned_lga_id');
    }

    public function assignedOrders()
    {
        return $this->hasMany(Order::class, 'rider_id');
    }

    public function isFleetRider(): bool
    {
        return !empty($this->logistics_company_id);
    }

    /**
     * Credit courier wallet balance using BCMath.
     * Guaranteed domain guard against negative amounts.
     */
    public function creditBalance(string $amount, string $reason = 'disbursement', ?int $sourceCompanyId = null): void
    {
        if (\App\Services\BcMathService::comp($amount, '0.00') <= 0) {
            throw new \InvalidArgumentException("Courier wallet credit amount must be strictly greater than 0.00.");
        }

        $current = $this->current_balance ?? '0.00';
        $sum = \App\Services\BcMathService::add($current, $amount);
        $this->current_balance = number_format((float)$sum, 2, '.', '');
        $this->save();
    }

    /**
     * Debit courier wallet balance using BCMath.
     * Guaranteed non-overdraft defense: prevents courier balance from dropping below 0.00.
     */
    public function debitBalance(string $amount, string $reason = 'payout'): void
    {
        if (\App\Services\BcMathService::comp($amount, '0.00') <= 0) {
            throw new \InvalidArgumentException("Courier wallet debit amount must be strictly greater than 0.00.");
        }

        $current = $this->current_balance ?? '0.00';
        if (\App\Services\BcMathService::comp($current, $amount) < 0) {
            throw new \RuntimeException("Insufficient courier wallet balance. Available: ₦{$current}, Requested: ₦{$amount}.");
        }

        $difference = \App\Services\BcMathService::sub($current, $amount);
        $this->current_balance = number_format((float)$difference, 2, '.', '');
        $this->save();
    }
}
