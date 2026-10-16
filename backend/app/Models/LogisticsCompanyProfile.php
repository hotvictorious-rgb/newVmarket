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
     * Closed-Loop Fleet Payroll: Only the logistics company can disburse funds to its assigned riders.
     * Mathematical Zero-Sum Transfer:
     * Debits company corporate wallet, credits rider wallet by the exact same amount.
     * System Money Delta: -X + X = 0.00.
     * Protects logistics company against phantom rider wallet inflation.
     */
    public function disburseToRider(RiderProfile $rider, string $amount, ?string $notes = null, ?User $actorUser = null): array
    {
        if ((int)$rider->logistics_company_id !== (int)$this->id) {
            throw new \InvalidArgumentException("Security Barrier: Rider #{$rider->id} does not belong to this logistics company (#{$this->id}). Cross-company disbursements are strictly prohibited.");
        }

        if (\App\Services\BcMathService::comp($amount, '0.00') <= 0) {
            throw new \InvalidArgumentException("Disbursement amount must be strictly greater than 0.00.");
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($rider, $amount, $notes, $actorUser) {
            $lockedCompany = self::where('id', $this->id)->lockForUpdate()->first();
            $lockedRider = RiderProfile::where('id', $rider->id)->lockForUpdate()->first();

            $formattedAmount = number_format((float)$amount, 2, '.', '');

            if (\App\Services\BcMathService::comp((string)$lockedCompany->wallet_balance, $formattedAmount) < 0) {
                throw new \RuntimeException("Insufficient corporate wallet balance (Available: ₦" . number_format((float)$lockedCompany->wallet_balance, 2) . ", Requested: ₦" . number_format((float)$formattedAmount, 2) . "). Logistics company cannot pay riders what it has not earned.");
            }

            // 1. Atomically Debit Logistics Company Corporate Wallet
            $lockedCompany->debitWallet($formattedAmount);

            // 2. Atomically Credit Rider Wallet
            $lockedRider->creditBalance($formattedAmount, 'fleet_corporate_disbursement', $lockedCompany->id);

            // 3. Authoritative Double-Entry Financial Journal Record
            $ref = 'FLEET-PAY-' . date('YmdHis') . '-' . strtoupper(\Illuminate\Support\Str::random(8));
            \App\Models\FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => $ref,
                'account_name' => 'logistics_company_wallet',
                'entry_type' => 'debit',
                'amount' => $formattedAmount,
                'currency' => 'NGN',
                'description' => "Corporate payroll disbursement from {$lockedCompany->company_name} to Courier {$lockedRider->user->name}",
            ]);

            \App\Models\FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => $ref,
                'account_name' => 'rider_wallet',
                'entry_type' => 'credit',
                'amount' => $formattedAmount,
                'currency' => 'NGN',
                'description' => "Courier payroll credited from {$lockedCompany->company_name}",
            ]);

            // 4. Immutable Audit Trail
            \App\Models\AdminAuditLog::create([
                'user_id' => $actorUser ? $actorUser->id : null,
                'action' => 'logistics_rider_disbursement',
                'target_type' => RiderProfile::class,
                'target_id' => $lockedRider->id,
                'old_values' => [
                    'company_wallet' => $this->wallet_balance,
                    'rider_balance' => $rider->current_balance,
                ],
                'new_values' => [
                    'company_wallet' => $lockedCompany->wallet_balance,
                    'rider_balance' => $lockedRider->current_balance,
                    'disbursed_amount' => $formattedAmount,
                    'notes' => $notes,
                    'reference' => $ref,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'success' => true,
                'reference' => $ref,
                'amount' => $formattedAmount,
                'company_wallet_balance' => $lockedCompany->wallet_balance,
                'rider_new_balance' => $lockedRider->current_balance,
                'message' => "Successfully disbursed ₦" . number_format((float)$formattedAmount, 2) . " to {$lockedRider->user->name}.",
            ];
        });
    }
}
