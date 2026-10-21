<?php

namespace App\Services;

use App\Models\CashbackRecoveryLedger;
use App\Models\CustomerCashbackLedger;
use App\Models\FinancialJournalEntry;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CashbackMaturityService
{
    public function matureOrder(Order $order): array
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();
            if (!$lockedOrder || !$lockedOrder->isFulfilled()) {
                return ['matured' => false, 'blocked' => true, 'reason' => 'order_not_fulfilled'];
            }

            $now = Carbon::now();
            $hasBlockingReturn = $lockedOrder->returnCases()
                ->whereNotIn('status', ['resolved', 'refunded', 'replacement_completed', 'rejected', 'closed'])
                ->exists();
            $hasBlockingDispute = $lockedOrder->dispute()
                ->whereIn('status', ['filed', 'under_review'])
                ->exists();
            if ($hasBlockingReturn || $hasBlockingDispute) {
                return ['matured' => false, 'blocked' => true, 'reason' => 'blocking_case'];
            }

            $lot = CustomerCashbackLedger::where('order_id', $lockedOrder->id)
                ->where('status', 'pending_maturation')
                ->whereNotNull('matures_at')
                ->where('matures_at', '<=', $now)
                ->lockForUpdate()
                ->first();

            if (!$lot) {
                return ['matured' => false, 'blocked' => false, 'reason' => 'no_due_lot'];
            }

            $remainingLot = (string) $lot->amount;
            $recoveryRows = CashbackRecoveryLedger::where('customer_id', $lot->customer_id)
                ->whereIn('status', ['pending_recovery', 'partially_recovered'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($recoveryRows as $recovery) {
                if (BcMathService::comp($remainingLot, '0.00') <= 0) {
                    break;
                }
                $outstanding = $recovery->outstandingAmount();
                if (BcMathService::comp($outstanding, '0.00') <= 0) {
                    continue;
                }
                $offset = BcMathService::comp($remainingLot, $outstanding) <= 0 ? $remainingLot : $outstanding;
                if (BcMathService::comp($offset, '0.00') <= 0) {
                    continue;
                }

                $eventBase = 'EVT-CB-RECOVERY-OFFSET-LOT-' . $lot->id . '-REC-' . $recovery->id;
                FinancialJournalEntry::create([
                    'order_id' => $lockedOrder->id,
                    'transaction_reference' => $lockedOrder->payment_reference ?? $lockedOrder->order_number,
                    'event_id' => $eventBase . '-LIABILITY',
                    'beneficiary_type' => User::class,
                    'beneficiary_id' => $lot->customer_id,
                    'account_name' => 'cashback_liability',
                    'entry_type' => 'debit',
                    'amount' => $offset,
                    'currency' => 'NGN',
                    'description' => 'Offset previously spent cashback recovery against newly matured cashback.',
                ]);
                FinancialJournalEntry::create([
                    'order_id' => $lockedOrder->id,
                    'transaction_reference' => $lockedOrder->payment_reference ?? $lockedOrder->order_number,
                    'event_id' => $eventBase . '-RECOVERY',
                    'beneficiary_type' => User::class,
                    'beneficiary_id' => $lot->customer_id,
                    'account_name' => 'cashback_recovery_receivable',
                    'entry_type' => 'credit',
                    'amount' => $offset,
                    'currency' => 'NGN',
                    'description' => 'Recover previously spent cashback from future matured reward.',
                ]);

                $recovery->recovered_amount = BcMathService::add((string) $recovery->recovered_amount, $offset);
                $recovery->status = BcMathService::comp((string) $recovery->recovered_amount, (string) $recovery->amount) >= 0
                    ? 'recovered'
                    : 'partially_recovered';
                $recovery->save();
                $remainingLot = BcMathService::sub($remainingLot, $offset);
            }

            $lot->amount = $remainingLot;
            $lot->matures_at = $now;
            if (BcMathService::comp($remainingLot, '0.00') <= 0) {
                $lot->status = 'recovered';
                $lot->expires_at = null;
            } else {
                $lot->status = 'available';
                // The 365-day clock starts at actual maturity after all blocks and offsets.
                $lot->expires_at = $now->copy()->addDays(365);
            }
            $lot->save();

            return [
                'matured' => $lot->status === 'available',
                'blocked' => false,
                'recovered' => $lot->status === 'recovered',
                'available_amount' => $remainingLot,
            ];
        });
    }

    public function processDueLots(): array
    {
        $now = Carbon::now();
        $matured = 0;
        $recovered = 0;
        $blocked = 0;
        $checked = 0;

        CustomerCashbackLedger::query()
            ->where('status', 'pending_maturation')
            ->whereNotNull('matures_at')
            ->where('matures_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($lots) use (&$matured, &$recovered, &$blocked, &$checked) {
                foreach ($lots as $lot) {
                    $checked++;
                    $result = $this->matureOrder(Order::find($lot->order_id) ?? new Order());
                    if (!empty($result['matured'])) {
                        $matured++;
                    } elseif (!empty($result['recovered'])) {
                        $recovered++;
                    } elseif (!empty($result['blocked'])) {
                        $blocked++;
                    }
                }
            });

        $expired = 0;
        CustomerCashbackLedger::query()
            ->where('status', 'available')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($lots) use (&$expired, $now) {
                foreach ($lots as $candidate) {
                    DB::transaction(function () use ($candidate, &$expired, $now) {
                        $lot = CustomerCashbackLedger::whereKey($candidate->id)->lockForUpdate()->first();
                        if (!$lot || $lot->status !== 'available' || !$lot->expires_at || $lot->expires_at->gt($now)) {
                            return;
                        }
                        $lot->status = 'expired';
                        $lot->save();
                        $expired++;
                    });
                }
            });

        return compact('checked', 'matured', 'recovered', 'blocked', 'expired');
    }
}
