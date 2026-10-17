<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:expire-unpaid')->everyTenMinutes();

// Automated Nightly Disaster Recovery Backup to Google Drive (02:00 AM WAT)
Schedule::command('backup:google-drive')->dailyAt('02:00');

// Automated Gateway Reconciliation for Timeouts and In-Flight Transfers
Schedule::command('finance:reconcile')->everyFifteenMinutes();
