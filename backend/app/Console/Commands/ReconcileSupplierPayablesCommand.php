<?php

namespace App\Console\Commands;

use App\Services\FinancialReconciliationEngine;
use Illuminate\Console\Command;

class ReconcileSupplierPayablesCommand extends Command
{
    protected $signature = 'finance:reconcile-supplier-payables {--order= : Limit the audit to one retailer order ID}';
    protected $description = 'Read-only reconciliation of retailer supplier payable snapshots against the financial journal';

    public function handle(FinancialReconciliationEngine $engine): int
    {
        $orderOption = $this->option('order');
        $orderId = null;
        if ($orderOption !== null && $orderOption !== '') {
            $validatedId = filter_var($orderOption, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($validatedId === false) {
                $this->error('The --order option must be a positive order ID.');
                return self::INVALID;
            }
            $orderId = (int) $validatedId;
        }

        $result = $engine->reconcileRetailerSupplierPayables($orderId);
        $this->info("Retailer supplier payable audit complete: {$result['orders_checked']} orders checked; {$result['mismatches']} mismatches.");

        foreach ($result['errors'] as $error) {
            $this->error(json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return $result['mismatches'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
