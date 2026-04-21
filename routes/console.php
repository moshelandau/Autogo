<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduled syncs ────────────────────────────────────
Schedule::command('sync:towbook')->hourly()->withoutOverlapping()->runInBackground();

// Twice-a-week: Mon + Thu @ 9 AM, open the EZ Pass + NYC violations check task
Schedule::command('tasks:open-violations-check')
    ->cron('0 9 * * 1,4')         // 9:00 AM on Monday and Thursday
    ->timezone('America/New_York')
    ->withoutOverlapping();
