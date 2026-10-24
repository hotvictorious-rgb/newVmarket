<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreasuryBankCreditBatchAllocation extends Model
{
    protected $fillable = [
        'treasury_bank_credit_batch_id',
        'order_id',
        'gateway_transaction_id',
        'payment_reference',
        'amount_kobo',
        'currency',
        'idempotency_key',
        'added_by_admin_id',
        'added_at',
    ];

    protected $casts = [
        'amount_kobo' => 'integer',
        'added_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(TreasuryBankCreditBatch::class, 'treasury_bank_credit_batch_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function addedByAdmin()
    {
        return $this->belongsTo(User::class, 'added_by_admin_id');
    }

    /**
     * Authoritative matching invariant between an order and a treasury collection allocation.
     * Supports both Paystack online settlements and verified direct Manual Bank Transfers.
     */
    public static function verifyOrderMatch(
        Order $order,
        TreasuryBankCreditBatchAllocation|array $allocationData,
        string $batchProvider,
        string $batchCurrency = 'NGN'
    ): array {
        if ($order->payment_status !== 'paid') {
            return ['valid' => false, 'error' => 'Order is not marked paid.'];
        }

        if (strtoupper($batchCurrency) !== 'NGN') {
            return ['valid' => false, 'error' => 'Treasury batch currency must be NGN.'];
        }

        $expectedKobo = (int) bcmul((string) $order->grand_total, '100', 0);
        $allocatedKobo = is_array($allocationData) ? (int) $allocationData['amount_kobo'] : (int) $allocationData->amount_kobo;
        if ($allocatedKobo !== $expectedKobo) {
            return ['valid' => false, 'error' => "Allocation amount ({$allocatedKobo} kobo) does not equal the order captured total ({$expectedKobo} kobo)."];
        }

        $paymentRef = is_array($allocationData) ? trim($allocationData['payment_reference']) : trim($allocationData->payment_reference);
        if (trim((string) $order->payment_reference) === '' || !hash_equals((string) $order->payment_reference, $paymentRef)) {
            return ['valid' => false, 'error' => 'Payment reference does not match the order record.'];
        }

        $gatewayTxId = is_array($allocationData) ? trim($allocationData['gateway_transaction_id']) : trim($allocationData->gateway_transaction_id);

        if ($batchProvider === 'paystack') {
            if ($order->payment_method !== 'paystack') {
                return ['valid' => false, 'error' => 'Paystack treasury batch requires a Paystack-paid order.'];
            }
            if (trim((string) $order->paystack_transaction_id) === '' || !hash_equals((string) $order->paystack_transaction_id, $gatewayTxId)) {
                return ['valid' => false, 'error' => 'Paystack transaction ID does not match the order record.'];
            }
        } elseif ($batchProvider === 'manual_bank_transfer') {
            if ($order->payment_method !== 'manual_bank_transfer') {
                return ['valid' => false, 'error' => 'Manual transfer treasury batch requires a manual-bank-transfer order.'];
            }
            $manualPayment = ManualBankTransferPayment::where('order_id', $order->id)
                ->where('status', 'approved')
                ->first();
            if (!$manualPayment || trim((string) $manualPayment->bank_transaction_reference) === '') {
                return ['valid' => false, 'error' => 'Approved manual bank transfer payment record with verified bank reference not found.'];
            }
            if (!hash_equals((string) $manualPayment->bank_transaction_reference, $gatewayTxId)) {
                return ['valid' => false, 'error' => 'Bank transaction reference does not match the verified manual payment record.'];
            }
        } else {
            return ['valid' => false, 'error' => "Unsupported gateway provider: {$batchProvider}."];
        }

        return ['valid' => true, 'error' => null];
    }
}
