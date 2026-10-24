<?php

namespace App\Console\Commands;

use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSupplierAssignment;
use App\Models\SupplierOffer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckSupplierConfirmationSlaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supplier:check-confirmation-sla';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitors 60-minute supplier confirmation SLA during operating hours (8:00 AM – 6:00 PM WAT) and applies 24-hr SKU penalty on breach.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking supplier confirmation SLA during operating hours (8:00 AM – 6:00 PM WAT)...');

        $now = Carbon::now('Africa/Lagos');

        $assignments = OrderSupplierAssignment::where('status', 'awaiting_supplier_confirmation')
            ->whereNotNull('assigned_at')
            ->with('order')
            ->get();

        $breachedCount = 0;

        foreach ($assignments as $assignment) {
            $order = $assignment->order;
            if (!$order || !$order->retailer_model_order || $order->payment_status !== 'paid') {
                continue;
            }

            $assignedAt = Carbon::parse($assignment->assigned_at, 'Africa/Lagos');
            $elapsedMinutes = $this->calculateOperatingMinutes($assignedAt, $now);

            if ($elapsedMinutes >= 60) {
                $didTimeout = DB::transaction(function () use ($assignment, $order, $elapsedMinutes, &$breachedCount): bool {
                    $lockedAssignment = OrderSupplierAssignment::whereKey($assignment->id)->lockForUpdate()->first();
                    $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();

                    if (!$lockedAssignment || !$lockedOrder
                        || $lockedAssignment->status !== 'awaiting_supplier_confirmation'
                        || !$lockedOrder->retailer_model_order
                        || $lockedOrder->payment_status !== 'paid') {
                        return false;
                    }

                    $lockedAssignment->status = 'timed_out';
                    $lockedAssignment->supplier_response_notes = "Confirmation SLA breached ({$elapsedMinutes} operating minutes elapsed). 24-hr SKU penalty applied.";
                    $lockedAssignment->save();

                    // Apply 24-hour SKU penalty to all assigned items for this supplier
                    $items = OrderItem::where('order_id', $lockedOrder->id)
                        ->where('supplier_vendor_id', $lockedAssignment->supplier_vendor_id)
                        ->where('supplier_shop_id', $lockedAssignment->supplier_shop_id)
                        ->get();

                    foreach ($items as $item) {
                        $offerPenaltyQuery = SupplierOffer::where('product_id', $item->product_id)
                            ->where('vendor_id', $lockedAssignment->supplier_vendor_id)
                            ->where('shop_id', $lockedAssignment->supplier_shop_id);
                        if ($item->fulfillment_supplier_offer_id) {
                            $offerPenaltyQuery->whereKey($item->fulfillment_supplier_offer_id);
                        }
                        $offerPenaltyQuery->update([
                            'unavailability_penalty_expires_at' => Carbon::now()->addHours(24),
                        ]);
                    }

                    $lockedOrder->sourcing_status = 'timed_out';
                    $lockedOrder->sourcing_notes = "Supplier confirmation SLA timed out ({$elapsedMinutes} operating min). Escalated to Admin Command Tower for reassignment.";
                    $lockedOrder->save();

                    AdminAuditLog::create([
                        'user_id' => null,
                        'action' => 'supplier_confirmation_sla_timed_out',
                        'target_type' => Order::class,
                        'target_id' => $lockedOrder->id,
                        'old_values' => [
                            'assignment_id' => $lockedAssignment->id,
                            'supplier_vendor_id' => $lockedAssignment->supplier_vendor_id,
                            'status' => 'awaiting_supplier_confirmation',
                        ],
                        'new_values' => [
                            'assignment_id' => $lockedAssignment->id,
                            'status' => 'timed_out',
                            'elapsed_operating_minutes' => $elapsedMinutes,
                            'penalty_hours' => 24,
                            'penalty_expires_at' => Carbon::now()->addHours(24)->toIso8601String(),
                        ],
                        'ip_address' => '127.0.0.1',
                        'user_agent' => 'System/CLI',
                    ]);

                    $breachedCount++;
                    return true;
                });

                if ($didTimeout) {
                    try {
                        $failover = app(\App\Services\SupplierReassignmentRecoveryService::class)
                            ->attemptAutomaticFailover($order->id, $assignment->id);
                        if (!($failover['success'] ?? false)) {
                            $this->warn("Order #{$order->id}: automatic failover did not complete; Super Admin review remains required.");
                        }
                    } catch (\Throwable $exception) {
                        AdminAuditLog::create([
                            'user_id' => null,
                            'action' => 'automatic_supplier_failover_attempt_failed',
                            'target_type' => Order::class,
                            'target_id' => $order->id,
                            'old_values' => ['assignment_id' => (int) $assignment->id],
                            'new_values' => [
                                'reason' => 'Automatic failover after SLA timeout raised an exception and failed closed.',
                                'exception_class' => get_class($exception),
                            ],
                            'ip_address' => '127.0.0.1',
                            'user_agent' => 'System/CLI',
                        ]);
                        $this->error("Order #{$order->id}: automatic failover failed closed; Super Admin review remains required.");
                    }
                }
            }
        }

        $this->info("Completed SLA check. {$breachedCount} supplier assignment(s) timed out.");

        return Command::SUCCESS;
    }

    /**
     * Calculates active operating minutes between 8:00 AM and 6:00 PM (Africa/Lagos)
     * between two timestamps. Pauses outside this window and resumes at 8:00 AM.
     */
    public function calculateOperatingMinutes(Carbon $start, Carbon $end): int
    {
        if ($start->gte($end)) {
            return 0;
        }

        $startTz = $start->copy()->setTimezone('Africa/Lagos');
        $endTz = $end->copy()->setTimezone('Africa/Lagos');

        $totalMinutes = 0;
        $currentDay = $startTz->copy()->startOfDay();
        $lastDay = $endTz->copy()->startOfDay();

        while ($currentDay->lte($lastDay)) {
            $windowStart = $currentDay->copy()->setTime(8, 0, 0);
            $windowEnd = $currentDay->copy()->setTime(18, 0, 0);

            $dayStart = $currentDay->isSameDay($startTz) ? $startTz : $windowStart;
            $dayEnd = $currentDay->isSameDay($endTz) ? $endTz : $windowEnd;

            $effectiveStart = $dayStart->gt($windowStart) ? $dayStart->copy() : $windowStart;
            $effectiveEnd = $dayEnd->lt($windowEnd) ? $dayEnd->copy() : $windowEnd;

            if ($effectiveStart->lt($effectiveEnd)) {
                $totalMinutes += $effectiveStart->diffInMinutes($effectiveEnd);
            }

            $currentDay->addDay();
        }

        return (int) $totalMinutes;
    }
}
