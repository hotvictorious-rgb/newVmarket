<?php

namespace App\Console\Commands;

use App\Services\OrderSettlementService;
use Illuminate\Console\Command;

class ExpireUnpaidOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:expire-unpaid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancels abandoned pending orders whose payment expiration deadline has elapsed and restores inventory.';

    /**
     * Execute the console command.
     */
    public function handle(OrderSettlementService $settlementService): int
    {
        $this->info('Scanning for abandoned pending orders past payment expiration deadline...');
        $count = $settlementService->expireAbandonedPendingOrders();
        $this->info("Successfully cancelled and restored inventory for {$count} expired order(s).");
        return Command::SUCCESS;
    }
}
