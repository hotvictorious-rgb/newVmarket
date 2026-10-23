<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderSettlementService;
use Illuminate\Console\Command;

class SettleSingleOrderCommand extends Command
{
    protected $signature = 'finance:settle-single-order {order_id}';
    protected $description = 'Worker command to settle a single order under its own process and database connection';

    public function handle(OrderSettlementService $settlementService): int
    {
        $orderId = (int) $this->argument('order_id');
        $order = Order::find($orderId);

        if (!$order) {
            $this->error("Order #{$orderId} not found");
            return 1;
        }

        $maxRetries = 10;
        $attempt = 0;
        $lastError = '';

        while ($attempt < $maxRetries) {
            $attempt++;
            try {
                $freshOrder = Order::find($orderId);
                if (!$freshOrder) {
                    $this->error("Order #{$orderId} not found");
                    return 1;
                }

                if ($freshOrder->payment_status === 'paid') {
                    $this->info("Order #{$orderId} already paid: " . $freshOrder->sourcing_status);
                    return 0;
                }

                $kobo = (int) bcmul((string) $freshOrder->grand_total, '100', 0);
                $result = $settlementService->settlePaystackOrder(
                    $freshOrder,
                    [
                        'reference' => $freshOrder->payment_reference,
                        'status' => 'success',
                        'amount' => $kobo,
                        'currency' => 'NGN',
                        'channel' => 'card',
                        'id' => 'tx_stress_' . $freshOrder->payment_reference,
                    ],
                    null,
                    'stress_concurrency_worker'
                );

                if ($result['success']) {
                    $this->info("Order #{$orderId} settled: " . $freshOrder->fresh()->sourcing_status);
                    return 0;
                }

                $lastError = $result['message'] ?? 'Settlement failed';
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                // If deadlock or record changed, wait random backoff and retry
                usleep(random_int(50000, 200000)); // 50ms - 200ms
            }
        }

        $this->error("Settlement failed after {$maxRetries} attempts: {$lastError}");
        return 1;
    }
}
