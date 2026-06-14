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
