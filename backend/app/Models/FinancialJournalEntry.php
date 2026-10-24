<?php

namespace App\Models;

use App\Services\BcMathService;
use Illuminate\Database\Eloquent\Model;

class FinancialJournalEntry extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            // The database unique index is only useful when every normal writer
            // supplies a stable key. Preserve explicit keys; otherwise derive one
            // from the business event and ledger leg, never from the amount, so a
            // changed amount cannot masquerade as a new financial instruction.
            if (trim((string) $entry->idempotency_key) !== '') {
                return;
            }

            $eventId = trim((string) $entry->event_id);
            $transactionIdentity = $eventId !== ''
                ? 'event:' . $eventId
                : 'reference:' . trim((string) $entry->transaction_reference);

            $identity = implode('|', [
                (string) ($entry->order_id ?? '-'),
                (string) ($entry->order_item_id ?? '-'),
                $transactionIdentity,
                (string) ($entry->beneficiary_type ?? '-'),
                (string) ($entry->beneficiary_id ?? '-'),
                trim((string) $entry->account_name),
                trim((string) $entry->entry_type),
                strtoupper(trim((string) ($entry->currency ?? 'NGN'))),
            ]);

            $entry->idempotency_key = 'JRN-' . hash('sha256', $identity);
        });
    }

    protected $fillable = [
        'order_id',
        'order_item_id',
        'financial_operation_id',
        'transaction_reference',
        'event_id',
        'idempotency_key',
        'beneficiary_type',
        'beneficiary_id',
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

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function financialOperation()
    {
        return $this->belongsTo(FinancialOperation::class, 'financial_operation_id');
    }

    public function beneficiary()
    {
        return $this->morphTo();
    }

    /**
     * Mathematical proof that an order's journal entries are strictly balanced (Debits === Credits).
     */
    public static function assertOrderBalanced(int $orderId): bool
    {
        $order = Order::find($orderId);
        if (!$order) {
            return false;
        }

        // If child order, pivot to parent order to inspect complete multi-vendor cluster
        if ($order->parent_order_id) {
            $orderId = $order->parent_order_id;
            $order = Order::find($orderId);
        }

        $orderIds = [$orderId];
        if ($order && $order->children()->exists()) {
            $orderIds = array_merge($orderIds, $order->children()->pluck('id')->toArray());
        }

        $entries = static::whereIn('order_id', $orderIds)->get();

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