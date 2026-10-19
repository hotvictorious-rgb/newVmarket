<?php

namespace App\Console\Commands;

use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireStaleProductListingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:expire-stale-listings';

    /**
     * Aliases for the command.
     *
     * @var array
     */
    protected $aliases = ['marketplace:expire-stale-listings'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically transitions product listings older than 7 days without merchant re-confirmation to unlisted status.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();
        $sevenDaysAgo = $now->copy()->subDays(7);

        $this->info("Scanning for stale product listings unconfirmed since {$sevenDaysAgo->toDateTimeString()}...");

        $expiredCount = DB::transaction(function () use ($now, $sevenDaysAgo) {
            return Product::where('listing_status', 'listed')
                ->where(function ($q) use ($now, $sevenDaysAgo) {
                    $q->whereNull('listing_confirmed_at')
                      ->orWhere('listing_confirmed_at', '<=', $sevenDaysAgo)
                      ->orWhere('listing_confirmed_at', '>', $now);
                })
                ->update([
                    'listing_status' => 'unlisted',
                ]);
        });

        $this->info("Successfully transitioned {$expiredCount} stale product listings to 'unlisted' status.");
        Log::info("products:expire-stale-listings completed. Expired count: {$expiredCount}");

        return self::SUCCESS;
    }
}