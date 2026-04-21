# Recurring Violations-Check Task (Mon + Thu)

> **Status:** ✅ verified — scheduled and confirmed via `php artisan schedule:list` on production.

## What it does

Every Monday and Thursday at 9:00 AM (America/New_York), the scheduler runs `php artisan tasks:open-violations-check`, which:

1. Creates an `OfficeTask` titled **"Check EZ Pass + NYC violations (camera/parking/school bus)"**
2. Marks priority `high`, due tomorrow
3. Sends a database notification (`OperationalReminder`) to every user whose email ends in `@autogoco.com`
4. Each notification carries: title · body ("Bi-weekly check — EZ Pass + NYC violations.") · 🚓 icon · URL `/office-tasks` · `office_task_id` payload

The bell-icon dropdown in the top nav shows the unread count (red badge). Click bell → list of pending notifications → click one to mark it read and jump to its URL.

## Auto-clear when task is completed

When an operator clicks "Complete" on the task (in `OfficeTaskController@complete`), we also run:

```php
\DB::table('notifications')
    ->whereJsonContains('data->office_task_id', $officeTask->id)
    ->update(['read_at' => now()]);
```

This clears the bell badge for **every user** who got the reminder. Verified: the JSON-contains query works on PostgreSQL where the `data` column is JSONB.

## Schedule (verified on production)

```
0 9 * * 1,4   php artisan tasks:open-violations-check   timezone: America/New_York
```

Confirmed by `ssh root@217.216.91.93 'cd /var/www/autogo && php artisan schedule:list'` after deploy.

## Trigger manually

```bash
ssh root@217.216.91.93 'cd /var/www/autogo && php artisan tasks:open-violations-check --force'
```

`--force` skips the once-per-day duplicate guard.

## Files

- `app/Console/Commands/OpenViolationsCheckTask.php`
- `app/Notifications/OperationalReminder.php`
- `app/Http/Controllers/NotificationController.php` (mark-read endpoints)
- `routes/console.php` (schedule entry)
- `app/Http/Middleware/HandleInertiaRequests.php` (shares `notifications` to every page so the bell renders)
- `resources/js/Layouts/AppLayout.vue` (bell + dropdown)
