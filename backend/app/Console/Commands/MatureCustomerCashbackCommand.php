<?php

namespace App\Console\Commands;

use App\Services\CashbackMaturityService;
use Illuminate\Console\Command;

class MatureCustomerCashbackCommand extends Command
{
    protected $signature = 'cashback:mature';
    protected $description = 'Mature eligible customer cashback after the claim and maturity windows, apply spent-cashback recovery offsets, and expire lots.';

    public function handle(CashbackMaturityService $service): int
    {
        $result = $service->processDueLots();
        $this->info(
            'Cashback lifecycle complete: '
            . $result['matured'] . ' matured, '
            . $result['recovered'] . ' fully offset, '
            . $result['expired'] . ' expired, '
            . $result['blocked'] . ' blocked pending review.'
        );

        return self::SUCCESS;
    }
}
