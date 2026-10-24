<?php

namespace App\Console\Commands;

use App\Services\SupplierReassignmentRecoveryService;
use Illuminate\Console\Command;

class AttemptAutomaticSupplierFailoverCommand extends Command
{
    protected $signature = 'suppliers:attempt-automatic-failover {orderId} {assignmentId}';
    protected $description = 'Execute one bounded, idempotent automatic supplier failover attempt for an eligible paid order.';

    public function handle(SupplierReassignmentRecoveryService $service): int
    {
        $result = $service->attemptAutomaticFailover((int) $this->argument('orderId'), (int) $this->argument('assignmentId'));
        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
