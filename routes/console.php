<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Refresh the offline NIU-existence source weekly (~52 extractions a year).
Schedule::command('taxpayer-file:fetch')
    ->weeklyOn(1, '03:00') // Mondays at 03:00
    ->withoutOverlapping()
    ->runInBackground();

// Retention: purge expired audit evidence monthly (no-op unless NIU_RETENTION_MONTHS is set).
Schedule::command('audit:archive-expired')
    ->monthlyOn(1, '04:00')
    ->withoutOverlapping();
