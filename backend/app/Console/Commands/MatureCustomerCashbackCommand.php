<?php

namespace App\Console\Commands;

use App\Models\CustomerCashbackLedger;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MatureCustomerCashbackCommand extends Command
{
    protected $signature = 'cashback:mature';
    protected $description = 'Mature eligible customer cashback after the claim and maturity windows, and expire available lots.';

    public function handle(): int
    {
        $now = Carbon::now();
        $matured = 0;
        $expired = 0;
        $blocked = 0;

        CustomerCashbackLedger::query()
            ->where('status', 'pending_maturation')
            ->whereNotNull('matures_at')
            ->where('matures_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($lots) use (&$matured, &$blocked, $now) {
                foreach ($lots as $candidate) {
                    DB::transaction(function () use ($candidate, &$matured, &$blocked, $now) {
                        $lot = CustomerCashbackLedger::whereKey($candidate->id)->lockForUpdate()->first();
                        if (!$lot || $lot->status !== 'pending_maturation' || !$lot->matures_at || $lot->matures_at->gt($now)) {
                            return;
                        }

                        $order = Order::whereKey($lot->order_id)->lockForUpdate()->first();
                        if (!$order || !$order->isFulfilled()) {
                            $blocked++;
                            return;
                        }

                        $hasBlockingReturn = $order->returnCases()
                            ->whereNotIn('status', ['resolved', 'refunded', 'replacement_completed', 'rejected', 'closed'])
                            ->exists();
                        $hasBlockingDispute = $order->dispute()
                            ->whereIn('status', ['filed', 'under_review'])
                            ->exists();

                        if ($hasBlockingReturn || $hasBlockingDispute) {
                            $blocked++;
                            return;
                        }

                        if (bccomp((string) $lot->amount, '0.00', 2) <= 0) {
                            $lot->status = 'forfeited';
                            $lot->expires_at = null;
                            $lot->save();
                            return;
                        }

                        $lot->status = 'available';
                        // The 365-day validity period starts when the lot actually
                        // matures, not when the original expected maturity timestamp passed.
                        $lot->matures_at = $now;
                        $lot->expires_at = $now->copy()->addDays(365);
                        $lot->save();
                        $matured++;
                    });
                }
            });

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

        $this->info("Cashback maturity complete: {$matured} matured, {$expired} expired, {$blocked} blocked pending review.");

        return self::SUCCESS;
    }
}
