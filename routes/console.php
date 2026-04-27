<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cancel P24 orders left pending for more than 24h (matches P24 session lifetime).
// Run hourly — costs nothing when there's nothing to cancel.
Schedule::command('orders:cancel-stale-p24')->hourly();

// Full PIM sync from backoffice — fail-safe when individual webhook signals are missed.
// Webhook is the primary path (real-time); this nightly run reconciles anything
// that slipped. Product data rarely changes, so once a day at 03:00 is enough.
Schedule::command('pim:sync-full')->dailyAt('03:00');
