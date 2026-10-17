<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogisticsCompanyProfile extends Model
{
    use SoftDeletes;

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
        'deleted_at' => 'datetime',
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
     * Prevents negative or zero credits.
     */
    public function creditWallet(string $amount): void
    {
        if (\App\Services\BcMathService::comp($amount, '0.00') <= 0) {
            throw new \InvalidArgumentException("Corporate wallet credit amount must be strictly greater than 0.00.");
        }

        $current = $this->wallet_balance ?? '0.00';
        $sum = \App\Services\BcMathService::add($current, $amount);
        $this->wallet_balance = number_format((float)$sum, 2, '.', '');
        $this->save();
    }

    /**
     * Debit corporate escrow wallet balance using BCMath.
     * Guaranteed non-overdraft defense: throws RuntimeException if balance is insufficient.
     */
    public function debitWallet(string $amount): void
    {
        if (\App\Services\BcMathService::comp($amount, '0.00') <= 0) {
            throw new \InvalidArgumentException("Corporate wallet debit amount must be strictly greater than 0.00.");
        }

        $current = $this->wallet_balance ?? '0.00';
        if (\App\Services\BcMathService::comp($current, $amount) < 0) {
            throw new \RuntimeException("Insufficient corporate wallet balance. Available: ₦{$current}, Requested: ₦{$amount}.");
        }

        $difference = \App\Services\BcMathService::sub($current, $amount);
        $this->wallet_balance = number_format((float)$difference, 2, '.', '');
        $this->save();
    }

    /**
     * Check if this logistics fleet or any of its couriers currently holds packages in transit.
     */
    public function hasActiveCustodyOrders(): bool
    {
        return Order::where('logistics_company_id', $this->id)
            ->where('order_status', 'out_for_delivery')
            ->exists();
    }

    /**
     * Clearance guard: Validates whether fleet profile can be deactivated.
     */
    public function canBeDeactivated(?string &$reason = null): bool
    {
        if ($this->hasActiveCustodyOrders()) {
            $reason = 'Logistics fleet has active customer parcels in physical custody during transit. Complete all deliveries before deactivation.';
            return false;
        }

        return true;
    }

    /**
     * Deletion guard: In e-commerce & fintech, entities with corporate wallet history
     * can NEVER be deleted due to double-entry financial ledgers.
     */
    public function canBeSafelyDeleted(?string &$reason = null): bool
    {
        if (Order::where('logistics_company_id', $this->id)->exists()) {
            $reason = 'Logistics fleet account cannot be deleted because permanent historical shipment and settlement logs exist.';
            return false;
        }

        return true;
    }

    public function getTotalDeliveryEarnings(): string
    {
        $sum = Order::where('logistics_company_id', $this->id)
            ->where('order_status', 'delivered')
            ->sum('delivery_fee');
        return number_format((float)$sum, 2, '.', '');
    }

    public function getInTransitEscrowBalance(): string
    {
        $sum = Order::where('logistics_company_id', $this->id)
            ->where('order_status', 'out_for_delivery')
            ->sum('delivery_fee');
        return number_format((float)$sum, 2, '.', '');
    }

    public function getPendingPayoutBalance(): string
    {
        $sum = LogisticsPayoutRequest::where('logistics_company_id', $this->id)
            ->where('status', 'pending')
            ->sum('amount');
        return number_format((float)$sum, 2, '.', '');
    }

    public function getTotalDisbursedBalance(): string
    {
        $sum = LogisticsPayoutRequest::where('logistics_company_id', $this->id)
            ->where('status', 'approved')
            ->sum('amount');
        return number_format((float)$sum, 2, '.', '');
    }

    public function getWithdrawableBalance(): string
    {
        return number_format((float)($this->wallet_balance ?? 0), 2, '.', '');
    }
}