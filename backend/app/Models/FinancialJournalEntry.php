<?php

namespace App\Models;

use App\Services\BcMathService;
use Illuminate\Database\Eloquent\Model;

class FinancialJournalEntry extends Model
{
    protected $fillable = [
        'order_id',
        'transaction_reference',
        'account_name',
        'entry_type',
        'amount',
        'currency',
        'description',
    ];

    protected $casts = [
        'amount' => 'string',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Mathematical proof that an order's journal entries are strictly balanced (Debits === Credits).
     */
    public static function assertOrderBalanced(int $orderId): bool
    {
        $entries = static::where('order_id', $orderId)->get();

        $debits = '0.00';
        $credits = '0.00';

        foreach ($entries as $entry) {
            if ($entry->entry_type === 'debit') {
                $debits = BcMathService::add($debits, (string)$entry->amount);
            } elseif ($entry->entry_type === 'credit') {
                $credits = BcMathService::add($credits, (string)$entry->amount);
            }
        }

        return BcMathService::comp($debits, $credits) === 0;
    }
}
