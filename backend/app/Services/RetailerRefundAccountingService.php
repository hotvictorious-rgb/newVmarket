<?php

namespace App\Services;

use App\Models\CashbackRecoveryLedger;
use App\Models\CustomerCashbackLedger;
use App\Models\FinancialJournalEntry;
use App\Models\Order;
use App\Models\ReturnCase;
use App\Models\RetailerSupplierPayoutAllocation;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorRecoveryLedger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RetailerRefundAccountingService
{
    /**
     * Reverse retailer accounting for an approved merchandise/delivery refund.
     * Cash outflow/wallet credit is posted by the caller, so this method posts
     * only the balancing merchandise and delivery components.
     */
    public function reverse(
        Order $order,
        ReturnCase $returnCase,
        string $merchandiseRefund,
        string $deliveryFeeRefund,
        string $reference,
        ?int $financialOperationId = null,
        bool $authorizedSupplierFault = false
    ): void {
        if (!$order->retailer_model_order) {
            throw new RuntimeException('Retailer refund accounting cannot process a legacy marketplace order.');
        }

        $supplierCostRefund = '0.00';
        $returnItems = $returnCase->items()->with('orderItem')->orderBy('id')->lockForUpdate()->get();

        foreach ($returnItems as $returnItem) {
            $item = $returnItem->orderItem;
            if (!$item || (int) $item->order_id !== (int) $order->id
                || empty($item->fulfillment_supplier_vendor_id)
                || $item->fulfillment_supplier_cost_snapshot === null) {
                throw new RuntimeException('Retailer refund is missing the order item supplier/cost snapshot.');
            }

            $lineCost = BcMathService::mul((string) $item->fulfillment_supplier_cost_snapshot, (string) $returnItem->quantity);
            $supplierCostRefund = BcMathService::add($supplierCostRefund, $lineCost);
            if (BcMathService::comp($lineCost, '0.00') <= 0) {
                continue;
            }

            $supplierCostAlreadySettled = $order->vendor_settlement_status === 'settled'
                || RetailerSupplierPayoutAllocation::where('order_item_id', $item->id)
                    ->where('status', 'settled')
                    ->exists();

            if ($supplierCostAlreadySettled && $authorizedSupplierFault) {
                $recovery = VendorRecoveryLedger::firstOrCreate(
                    ['idempotency_key' => "return-case-{$returnCase->id}-supplier-cost-recovery-item-{$returnItem->id}"],
                    [
                        'vendor_id' => $item->fulfillment_supplier_vendor_id,
                        'return_case_id' => $returnCase->id,
                        'order_id' => $order->id,
                        'amount' => $lineCost,
                        'status' => 'pending_recovery',
                        'notes' => "Authorized supplier-cost recovery for retailer refund Return #{$returnCase->case_number}, item #{$item->id}",
                    ]
                );
                if ($recovery->wasRecentlyCreated) {
                    $this->journal($order, $returnCase, $reference, $financialOperationId, 'SUPPLIER-RECOVERY-' . $returnItem->id, VendorProfile::class, (int) $item->fulfillment_supplier_vendor_id, 'supplier_recovery_receivable', 'debit', $lineCost, 'Authorized recovery of settled supplier cost.', (int) $item->id);
                }
            } elseif ($supplierCostAlreadySettled) {
                $this->journal($order, $returnCase, $reference, $financialOperationId, 'SUPPLIER-LOSS-' . $returnItem->id, User::class, null, 'retailer_supplier_refund_loss_pending_mapping', 'debit', $lineCost, 'Settled supplier cost absorbed pending authorized recovery mapping.', (int) $item->id);
            } else {
                $this->journal($order, $returnCase, $reference, $financialOperationId, 'SUPPLIER-PAYABLE-' . $returnItem->id, VendorProfile::class, (int) $item->fulfillment_supplier_vendor_id, 'supplier_payable', 'debit', $lineCost, 'Reverse agreed supplier cost for returned retailer item.', (int) $item->id);
            }
        }

        $cashbackLot = CustomerCashbackLedger::where('order_id', $order->id)->lockForUpdate()->first();
        $priorCashbackLiabilityReversed = (string) FinancialJournalEntry::where('order_id', $order->id)
            ->where('account_name', 'cashback_liability')
            ->where('entry_type', 'debit')
            ->where('event_id', 'like', 'EVT-RETAILER-REFUND-%-CB-REV')
            ->sum('amount');
        $priorSpentRecovery = (string) CashbackRecoveryLedger::where('order_id', $order->id)->sum('amount');
        $alreadyReversed = BcMathService::add($priorCashbackLiabilityReversed, $priorSpentRecovery);
        $remainingRewardToReverse = BcMathService::sub((string) $order->customer_cashback_5, $alreadyReversed);
        if (BcMathService::comp($remainingRewardToReverse, '0.00') < 0) {
            $remainingRewardToReverse = '0.00';
        }
        $cashbackReversal = BcMathService::mul($merchandiseRefund, '0.05');
        if (BcMathService::comp($cashbackReversal, $remainingRewardToReverse) > 0) {
            $cashbackReversal = $remainingRewardToReverse;
        }
        if (BcMathService::comp($cashbackReversal, '0.00') > 0 && !$cashbackLot) {
            throw new RuntimeException('Retailer cashback reversal has no original cashback ledger lot.');
        }

        $unspentCashback = '0.00';
        if ($cashbackLot && in_array($cashbackLot->status, ['pending_maturation', 'available'], true)) {
            $unspentCashback = BcMathService::comp((string) $cashbackLot->amount, $cashbackReversal) <= 0
                ? (string) $cashbackLot->amount
                : $cashbackReversal;
        }
        $spentCashbackRecovery = BcMathService::sub($cashbackReversal, $unspentCashback);

        if (BcMathService::comp($unspentCashback, '0.00') > 0) {
            $this->journal($order, $returnCase, $reference, $financialOperationId, 'CB-REV', User::class, (int) $order->customer_id, 'cashback_liability', 'debit', $unspentCashback, 'Reverse unspent retailer cashback for the refunded merchandise.');
            $remainingLot = BcMathService::sub((string) $cashbackLot->amount, $unspentCashback);
            $cashbackLot->amount = $remainingLot;
            if (BcMathService::comp($remainingLot, '0.00') <= 0) {
                $cashbackLot->status = 'forfeited';
                $cashbackLot->expires_at = null;
            }
            $cashbackLot->save();
        }

        if (BcMathService::comp($spentCashbackRecovery, '0.00') > 0) {
            $recovery = CashbackRecoveryLedger::firstOrCreate(
                ['idempotency_key' => "return-case-{$returnCase->id}-cashback-recovery"],
                [
                    'customer_id' => $order->customer_id,
                    'order_id' => $order->id,
                    'return_case_id' => $returnCase->id,
                    'amount' => $spentCashbackRecovery,
                    'recovered_amount' => '0.00',
                    'status' => 'pending_recovery',
                    'notes' => "Spent cashback recovery from refunded retailer merchandise for Return #{$returnCase->case_number}",
                ]
            );
            if ($recovery->wasRecentlyCreated) {
                $this->journal($order, $returnCase, $reference, $financialOperationId, 'CB-RECOVERY', User::class, (int) $order->customer_id, 'cashback_recovery_receivable', 'debit', $spentCashbackRecovery, 'Track spent cashback for offset against future matured cashback.');
            }
        }

        $marginReversal = BcMathService::sub(
            BcMathService::sub($merchandiseRefund, $supplierCostRefund),
            $cashbackReversal
        );
        if (BcMathService::comp($marginReversal, '0.00') > 0) {
            $this->journal($order, $returnCase, $reference, $financialOperationId, 'RETAIL-MARGIN', User::class, null, 'retailer_merchandise_margin', 'debit', $marginReversal, 'Reverse retailer merchandise margin for the refund.');
        } elseif (BcMathService::comp($marginReversal, '0.00') < 0) {
            $this->journal($order, $returnCase, $reference, $financialOperationId, 'RETAIL-LOSS-REV', User::class, null, 'retailer_merchandise_loss', 'credit', BcMathService::sub('0.00', $marginReversal), 'Reverse the portion of a retailer merchandise loss related to the refund.');
        }

        if (BcMathService::comp($deliveryFeeRefund, '0.00') > 0) {
            $this->journal($order, $returnCase, $reference, $financialOperationId, 'DELIVERY-EXPENSE', User::class, null, 'delivery_refund_expense', 'debit', $deliveryFeeRefund, 'VM-funded customer delivery-fee refund; logistics company recovery remains separate.');
        }
    }

    private function journal(
        Order $order,
        ReturnCase $returnCase,
        string $reference,
        ?int $financialOperationId,
        string $suffix,
        ?string $beneficiaryType,
        ?int $beneficiaryId,
        string $account,
        string $entryType,
        string $amount,
        string $description,
        ?int $orderItemId = null
    ): void {
        if (BcMathService::comp($amount, '0.00') <= 0) {
            return;
        }

        FinancialJournalEntry::create([
            'order_id' => $order->id,
            'order_item_id' => $orderItemId,
            'financial_operation_id' => $financialOperationId,
            'transaction_reference' => $reference,
            'event_id' => 'EVT-RETAILER-REFUND-' . $returnCase->id . '-' . $suffix,
            'idempotency_key' => 'RETAILER-REFUND-' . $returnCase->id . '-' . $suffix,
            'beneficiary_type' => $beneficiaryType,
            'beneficiary_id' => $beneficiaryId,
            'account_name' => $account,
            'entry_type' => $entryType,
            'amount' => $amount,
            'currency' => 'NGN',
            'description' => $description . ' Return #' . $returnCase->case_number,
        ]);
    }
}
