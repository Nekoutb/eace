<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Refresh the shared DGI taxpayer register twice a week — Mondays and Thursdays
// at 03:00. Downloaded once for everyone; every workspace queries the same file.
Schedule::command('taxpayer-file:fetch')
    ->days([1, 4]) // 1 = Monday, 4 = Thursday
    ->at('03:00')
    ->withoutOverlapping()
    ->runInBackground();

// Retention: purge expired audit evidence monthly (no-op unless NIU_RETENTION_MONTHS is set).
Schedule::command('audit:archive-expired')
    ->monthlyOn(1, '04:00')
    ->withoutOverlapping();

// Drain the database queue without a long-running daemon: every minute the
// scheduler runs a worker that processes pending jobs (e.g. an on-demand
// "Update register" refresh) and exits when the queue is empty.
Schedule::command('queue:work --stop-when-empty --max-time=280 --tries=1')
    ->everyMinute()
    ->withoutOverlapping();

// Finish any RFC-3161 evidence seals a Timestamp Authority outage left pending
// (no-op unless trusted timestamping is enabled).
Schedule::command('evidence:timestamp-pending')
    ->hourly()
    ->withoutOverlapping();
