<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduled syncs ────────────────────────────────────
Schedule::command('sync:towbook')->hourly()->withoutOverlapping()->runInBackground();

// Pull anything webhooks may have missed from Telebroad (SMS + calls)
// every 10 minutes. Safe to re-run — deduped by external_ref.
Schedule::command('telebroad:sync')->everyTenMinutes()->withoutOverlapping()->runInBackground();

// Hourly: nudge bot intake sessions that have gone quiet 4h+
// mid-flow. Sends ONE follow-up per session and gives up after 3 days.
Schedule::command('bot:chase-stalled')->hourly()->withoutOverlapping()->runInBackground();

// Twice-a-week: Mon + Thu @ 9 AM, open the EZ Pass + NYC violations check task
Schedule::command('tasks:open-violations-check')
    ->cron('0 9 * * 1,4')         // 9:00 AM on Monday and Thursday
    ->timezone('America/New_York')
    ->withoutOverlapping();

// Every 15 minutes: ping the bell for any due note reminders. Idempotent —
// each (note, user) pivot row has email_sent that flips true once notified.
Schedule::command('notes:reminders-notify')
    ->everyFifteenMinutes()
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->runInBackground();
