<?php

namespace App\Models;

use App\Services\BcMathService;
use Illuminate\Database\Eloquent\Model;

class CustomerWalletCredit extends Model
{
    protected $hidden = [
        'refund_reserved_from_status',
    ];

    protected $fillable = [
        'customer_id',
        'order_id',
        'return_case_id',
        'amount',
        'credit_type',
        'status',
        'refund_reserved_from_status',
        'spent_on_order_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'string',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function returnCase()
    {
        return $this->belongsTo(ReturnCase::class, 'return_case_id');
    }

    public function spentOnOrder()
    {
        return $this->belongsTo(Order::class, 'spent_on_order_id');
    }

    /**
     * Compute total available 100% spendable refund wallet balance for customer.
     */
    public static function getAvailableBalance(int $customerId): string
    {
        $sum = static::where('customer_id', $customerId)
            ->where('status', 'available')
            ->sum('amount');

        return BcMathService::add((string)($sum ?? '0'), '0.00');
    }

    /**
     * Atomically spend wallet balance toward a new order checkout.
     */
    public static function spendWalletBalance(int $customerId, int $orderId, string $amountToSpend): string
    {
        $availableLots = static::where('customer_id', $customerId)
            ->where('status', 'available')
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->get();

        $remainingToDeduct = $amountToSpend;
        $totalDeducted = '0.00';

        foreach ($availableLots as $lot) {
            if (BcMathService::comp($remainingToDeduct, '0.00') <= 0) {
                break;
            }

            if (BcMathService::comp($lot->amount, $remainingToDeduct) <= 0) {
                // Entire lot consumed
                $deducted = $lot->amount;
                $lot->status = 'redeemed';
                $lot->spent_on_order_id = $orderId;
                $lot->save();

                $remainingToDeduct = BcMathService::sub($remainingToDeduct, $deducted);
                $totalDeducted = BcMathService::add($totalDeducted, $deducted);
            } else {
                // Partial lot consumed: reduce current lot, spawn new redeemed row
                $deducted = $remainingToDeduct;
                $newLotAmount = BcMathService::sub($lot->amount, $deducted);

                $lot->amount = $newLotAmount;
                $lot->save();

                static::create([
                    'customer_id' => $customerId,
                    'order_id' => $lot->order_id,
                    'return_case_id' => $lot->return_case_id,
                    'amount' => $deducted,
                    'credit_type' => $lot->credit_type,
                    'status' => 'redeemed',
                    'spent_on_order_id' => $orderId,
                    'notes' => "Redeemed on Order #{$orderId}",
                ]);

                $totalDeducted = BcMathService::add($totalDeducted, $deducted);
                $remainingToDeduct = '0.00';
            }
        }

        return $totalDeducted;
    }
}
