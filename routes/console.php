<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Watchdog: every minute during market hours (Mon-Fri 09:25–16:05 ET) ──
Schedule::command('watchdog:scan')
    ->everyMinute()
    ->weekdays()
    ->between('09:25', '16:05')
    ->withoutOverlapping(2)      // skip if previous run still going
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/watchdog.log'));

// ── Weekend / pre-market lighter scan every 15 min ──
Schedule::command('watchdog:scan')
    ->everyFifteenMinutes()
    ->unlessBetween('09:25', '16:05')
    ->withoutOverlapping(10)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/watchdog.log'));

// ── Forecast model refresh during market session ───────────────────────────
Schedule::command('watchdog:forecast --horizon=7')
    ->hourly()
    ->weekdays()
    ->between('09:35', '16:10')
    ->withoutOverlapping(10)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/watchdog_forecast.log'));

// ── Evaluate matured forecasts for model governance ───────────────────────
Schedule::command('watchdog:forecast:evaluate --horizon=7')
    ->dailyAt('16:20')
    ->weekdays()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/watchdog_forecast_eval.log'));

