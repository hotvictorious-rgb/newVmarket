<?php

namespace App\Console\Commands;

use App\Services\FinancialReconciliationEngine;
use Illuminate\Console\Command;

class ReconcileFinancialGatewayCommand extends Command
{
    protected $signature = 'finance:reconcile';
    protected $description = 'Authoritatively reconcile gateway timeouts, in-flight transfers, and stale webhook events';

    public function handle(FinancialReconciliationEngine $engine): int
    {
        $this->info('Starting Financial Gateway Reconciliation...');

        // 1. Reconcile Payouts
        $payoutResults = $engine->reconcilePayouts();
        $this->info("Payouts Reconciled: Vendor ({$payoutResults['vendor_reconciled']}), Logistics ({$payoutResults['logistics_reconciled']})");

        // 2. Reconcile Webhooks
        $staleWebhooks = $engine->reconcileStaleWebhooks();
        $this->info("Stale Webhook Events Reset for Retry: {$staleWebhooks}");

        // 3. Reconcile Customer Refunds (P1 Durability Remediation)
        $refundsReconciled = $engine->reconcileRefunds();
        $this->info("Customer Refunds Reconciled: {$refundsReconciled}");

        $this->info('Financial Gateway Reconciliation Complete.');
        return 0;
    }
}