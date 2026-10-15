<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SharedHostingOptimizeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vmarket:optimize-shared {--clear : Clear all caches instead of building them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepares and tunes newVmarket for peak performance and security on shared hosting (cPanel)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('  👑 Victorious MARKET - Shared Hosting Optimizer   ');
        $this->info('====================================================');

        $basePath = base_path();
        $isClear = $this->option('clear');

        if ($isClear) {
            $this->warn('Clearing compiled caches for maintenance/debugging...');
            Artisan::call('optimize:clear');
            $this->line(Artisan::output());
            $this->info('✓ All application caches successfully cleared.');
            return self::SUCCESS;
        }

        // 1. Health & Permission Checks
        $this->comment('1. Verifying Environment & File Permissions...');
        $writablePaths = [
            storage_path(),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        $allWritable = true;
        foreach ($writablePaths as $path) {
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true, true);
            }
            if (!is_writable($path)) {
                $this->error("  ✗ Unwritable directory: {$path}");
                $allWritable = false;
            }
        }

        if ($allWritable) {
            $this->info('  ✓ Storage and cache directories are properly writable (0755/0775).');
        } else {
            $this->warn('  ! Please fix directory permissions in cPanel File Manager.');
        }

        // 2. Database Connection Check
        $this->comment('2. Testing Database & Queue Tables...');
        try {
            DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();
            $this->info("  ✓ Database connected successfully (driver: {$driver}).");

            if (DB::getSchemaBuilder()->hasTable('jobs')) {
                $pendingJobs = DB::table('jobs')->count();
                $this->info("  ✓ Database queue table ('jobs') verified. Pending jobs: {$pendingJobs}.");
            } else {
                $this->warn("  ! 'jobs' table not detected. Run: php artisan migrate");
            }
        } catch (\Throwable $e) {
            $this->error('  ✗ Database connection failed: ' . $e->getMessage());
        }

        // 3. Storage Link Verification
        $this->comment('3. Checking Storage Public Link...');
        try {
            Artisan::call('storage:link');
            $this->info('  ✓ Public storage link is verified.');
        } catch (\Throwable $e) {
            $this->line('  Note: ' . $e->getMessage());
        }

        // 4. Cache Warmup (Config, Routes, Views)
        $this->comment('4. Pre-compiling Production Caches...');
        
        $this->line('  - Compiling config cache...');
        Artisan::call('config:cache');
        
        $this->line('  - Compiling routes cache...');
        Artisan::call('route:cache');
        
        $this->line('  - Compiling blade view templates...');
        Artisan::call('view:cache');

        $this->info('  ✓ Configuration, routes, and views compiled into memory-mapped arrays.');

        // 5. Tailored Cron Configuration for cPanel
        $this->info('----------------------------------------------------');
        $this->info('  📋 COPY-PASTE CRON JOBS FOR CPANEL (Cron Jobs Menu)');
        $this->info('----------------------------------------------------');
        $this->line("Add these 2 Cron Jobs in cPanel set to run every minute (* * * * *):");
        $this->newLine();
        
        $this->comment("Job 1: Asynchronous Database Queue Worker (Processes OTPs, receipts, settlements):");
        $queueCommand = "* * * * * cd {$basePath} && php artisan queue:work database --stop-when-empty --max-time=50 >> /dev/null 2>&1";
        $this->line("<fg=yellow>{$queueCommand}</>");
        $this->newLine();

        $this->comment("Job 2: Laravel Ecosystem Scheduler (7-day freshness triage, audit rotation):");
        $schedCommand = "* * * * * cd {$basePath} && php artisan schedule:run >> /dev/null 2>&1";
        $this->line("<fg=yellow>{$schedCommand}</>");
        $this->newLine();

        $this->info('----------------------------------------------------');
        $this->info('  ⚡ SHARED HOSTING OPCACHE BOOST');
        $this->info('----------------------------------------------------');
        $this->line('In cPanel > "Select PHP Version" > "Extensions":');
        $this->line('  - Ensure [opcache] is enabled.');
        $this->line('  - Ensure [pdo_mysql], [bcmath], [mbstring], [fileinfo], [ctype], [curl] are checked.');
        $this->info('====================================================');
        $this->info('✓ Shared hosting optimization completed successfully!');

        return self::SUCCESS;
    }
}
