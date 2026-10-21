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

// Daily read-only supplier payable reconciliation; keep this full-ledger scan off the frequent gateway loop.
Schedule::command('finance:reconcile-supplier-payables')->dailyAt('02:30')->withoutOverlapping();

// Mature eligible cashback after the claim window + maturity buffer and expire lots 365 days after actual maturity.
Schedule::command('cashback:mature')->everyFifteenMinutes()->withoutOverlapping();

// Automated 7-Day Product Freshness Lifecycle Expiration (Hourly)
Schedule::command('products:expire-stale-listings')->hourly();
