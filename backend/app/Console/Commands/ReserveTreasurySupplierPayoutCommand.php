<?php

namespace App\Console\Commands;

use App\Services\RetailerSupplierPayoutService;
use Illuminate\Console\Command;

class ReserveTreasurySupplierPayoutCommand extends Command
{
    protected $signature = 'treasury:reserve-supplier-payout {payoutId}';
    protected $description = 'Atomically reserve treasury capacity without dispatching a transfer.';

    public function handle(RetailerSupplierPayoutService $service): int
    {
        $result = $service->reserveTreasuryBankCreditBatch((int) $this->argument('payoutId'));
        $this->line(json_encode([
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? null,
            'batch_id' => data_get($result, 'batch.id'),
            'payout_id' => (int) $this->argument('payoutId'),
        ], JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
