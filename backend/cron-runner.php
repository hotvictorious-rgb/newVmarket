<?php

/**
 * Victorious MARKET - cPanel Standalone Cron Runner
 * 
 * Usage in cPanel Cron Jobs (runs every minute):
 * * * * * * /usr/local/bin/php /home/username/path-to-core/cron-runner.php >> /dev/null 2>&1
 */

define('LARAVEL_START', microtime(true));

// Prevent concurrent overlapping runs
$lockFile = __DIR__ . '/storage/framework/cron_runner.lock';
if (file_exists($lockFile) && (time() - filemtime($lockFile) < 55)) {
    exit(0); // Another runner is actively working
}

touch($lockFile);

try {
    // Autoload dependencies
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    } elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }

    // Bootstrap Laravel application
    $appPath = file_exists(__DIR__ . '/bootstrap/app.php') 
        ? __DIR__ . '/bootstrap/app.php' 
        : __DIR__ . '/../bootstrap/app.php';

    /** @var \Illuminate\Foundation\Application $app */
    $app = require_once $appPath;
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

    // 1. Run scheduled tasks (7-day freshness triage, audit rotation)
    $kernel->call('schedule:run');

    // 2. Process pending database queue jobs (OTPs, notifications, settlements)
    $kernel->call('queue:work', [
        'connection' => 'database',
        '--stop-when-empty' => true,
        '--max-time' => 50,
        '--tries' => 3,
    ]);

} catch (\Throwable $e) {
    $logMsg = '[' . date('Y-m-d H:i:s') . '] CRON ERROR: ' . $e->getMessage() . PHP_EOL;
    @file_put_contents(__DIR__ . '/storage/logs/cron.log', $logMsg, FILE_APPEND);
} finally {
    @unlink($lockFile);
}
