<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorProfile extends Model
{
    use SoftDeletes;

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
        'deleted_at' => 'datetime',
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

    public function payoutRequests()
    {
        return $this->hasMany(VendorPayoutRequest::class, 'vendor_id');
    }

    /**
     * Check if merchant has in-flight unfulfilled orders across all branches.
     */
    public function hasInFlightOrders(): bool
    {
        return $this->orders()
            ->whereIn('order_status', ['pending', 'confirmed', 'processing', 'ready_for_pickup', 'out_for_delivery'])
            ->exists();
    }

    /**
     * Check if merchant has pending escrow balances across all branches.
     */
    public function hasPendingEscrow(): bool
    {
        return $this->orders()
            ->whereIn('vendor_settlement_status', ['held', 'eligible'])
            ->exists();
    }

    /**
     * Check if merchant has active pickup reservations across all branches.
     */
    public function hasActivePickupReservations(): bool
    {
        return PickupReservation::where('vendor_id', $this->id)
            ->whereIn('status', ['reserved', 'pending'])
            ->exists();
    }

    /**
     * Clearance guard: Validates whether a merchant profile can be deactivated or suspended.
     */
    public function canBeDeactivatedOrSuspended(?string &$reason = null): bool
    {
        if ($this->hasInFlightOrders()) {
            $reason = 'Merchant has active in-flight customer orders across branch outlets that must be fulfilled first.';
            return false;
        }

        if ($this->hasPendingEscrow()) {
            $reason = 'Merchant has pending held escrow balances awaiting settlement.';
            return false;
        }

        if ($this->hasActivePickupReservations()) {
            $reason = 'Merchant has active in-store pickup reservations awaiting customer collection.';
            return false;
        }

        return true;
    }

    /**
     * Deletion guard: In e-commerce & fintech, commercial entities with transaction history
     * can NEVER be deleted due to financial ledgers, audit trails, and tax compliance.
     */
    public function canBeSafelyDeleted(?string &$reason = null): bool
    {
        if ($this->orders()->exists()) {
            $reason = 'Merchant profile cannot be deleted because permanent historical transaction records and financial ledgers exist.';
            return false;
        }

        if (PickupReservation::where('vendor_id', $this->id)->exists()) {
            $reason = 'Merchant profile cannot be deleted because historical customer reservations exist.';
            return false;
        }

        if ($this->products()->exists()) {
            $reason = 'Merchant profile cannot be deleted while products are still listed. Delist products first.';
            return false;
        }

        return true;
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

    public function getGrossSales(): string
    {
        $sum = Order::where('vendor_id', $this->id)
            ->where('payment_status', 'paid')
            ->sum('merchandise_subtotal');
        return number_format((float)$sum, 2, '.', '');
    }

    public function getLifetimeNetEarnings(): string
    {
        $sum = Order::where('vendor_id', $this->id)
            ->where('payment_status', 'paid')
            ->whereNotIn('vendor_settlement_status', ['refunded'])
            ->sum('vendor_net_90');
        return number_format((float)$sum, 2, '.', '');
    }

    public function getEscrowHeldBalance(): string
    {
        $sum = Order::where('vendor_id', $this->id)
            ->where('payment_status', 'paid')
            ->whereIn('vendor_settlement_status', ['held', 'disputed'])
            ->sum('vendor_net_90');
        return number_format((float)$sum, 2, '.', '');
    }

    public function getPendingPayoutBalance(): string
    {
        $sum = VendorPayoutRequest::where('vendor_id', $this->id)
            ->where('status', 'pending')
            ->sum('amount');
        return number_format((float)$sum, 2, '.', '');
    }

    public function getTotalSettledBalance(): string
    {
        $sum = Order::where('vendor_id', $this->id)
            ->where('vendor_settlement_status', 'settled')
            ->sum('vendor_net_90');
        return number_format((float)$sum, 2, '.', '');
    }

    public function getWithdrawableBalance(): string
    {
        // Must be paid, delivered, and have zero active return cases or disputes
        // Authoritative Partial-Allocation Accounting: Sum only unallocated remaining payable
        $eligibleOrders = Order::where('vendor_id', $this->id)
            ->where('vendor_settlement_status', 'eligible')
            ->where('payment_status', 'paid')
            ->where('order_status', 'delivered')
            ->whereDoesntHave('returnCases', function ($q) {
                $q->whereNotIn('status', ['resolved', 'refunded', 'replacement_completed', 'rejected', 'closed']);
            })
            ->whereDoesntHave('dispute', function ($q) {
                $q->whereIn('status', ['filed', 'under_review']);
            })
            ->with(['payoutAllocations.payoutRequest'])
            ->get();

        $eligible = '0.00';
        foreach ($eligibleOrders as $order) {
            $eligible = \App\Services\BcMathService::add($eligible, $order->remaining_payable_to_vendor);
        }

        $pending = VendorPayoutRequest::where('vendor_id', $this->id)
            ->where('status', 'pending')
            ->sum('amount');

        // Deduct unrecovered liabilities/debts from previous returns or penalties
        $unrecoveredDebt = \App\Models\VendorRecoveryLedger::where('vendor_id', $this->id)
            ->where('status', 'pending_recovery')
            ->sum('amount');

        $totalDeductions = \App\Services\BcMathService::add((string)$pending, (string)$unrecoveredDebt);
        $diff = \App\Services\BcMathService::sub((string)$eligible, (string)$totalDeductions);
        if (\App\Services\BcMathService::comp($diff, '0.00') < 0) {
            return '0.00';
        }
        return number_format((float)$diff, 2, '.', '');
    }
}