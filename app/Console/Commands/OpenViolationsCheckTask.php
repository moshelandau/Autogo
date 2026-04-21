<?php

namespace App\Console\Commands;

use App\Models\OfficeTask;
use App\Models\User;
use App\Notifications\OperationalReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Twice-a-week reminder to check EZ Pass + NYC violations and enter them.
 *
 * Schedule (in routes/console.php):
 *   Mon  9:00 AM
 *   Thu  9:00 AM
 *
 * Creates an OfficeTask + sends a notification to the rental team. The task
 * has a "Mark complete" button — completing it dismisses the notification.
 */
class OpenViolationsCheckTask extends Command
{
    protected $signature = 'tasks:open-violations-check {--force : Create even if a pending one already exists today}';
    protected $description = 'Open the twice-weekly EZ Pass + NYC violations check task and notify the rental team.';

    public function handle(): int
    {
        $title = 'Check EZ Pass + NYC violations (camera/parking/school bus)';

        // Avoid duplicates if cron fires twice the same day
        if (!$this->option('force')) {
            $existing = OfficeTask::where('title', $title)
                ->whereDate('created_at', today())
                ->where('is_completed', false)
                ->first();
            if ($existing) {
                $this->info("Already open: task #{$existing->id}");
                return 0;
            }
        }

        $task = OfficeTask::create([
            'title'        => $title,
            'description'  => "Bi-weekly review (Mondays + Thursdays):\n"
                            . "1. Log into NY E-ZPass Business Center → Statements → download CSV → upload at /ezpass/import\n"
                            . "2. Check mailbox / NYC Violations Center for new camera tickets, parking, school bus tickets\n"
                            . "3. Enter each violation against the rental that was active that day\n"
                            . "4. Charge the renter's card on file (per rental agreement §8) + admin fee\n"
                            . "5. Click ✓ Complete on this task to clear the notification.",
            'section'      => 'rental',
            'priority'     => 'high',
            'due_date'     => Carbon::tomorrow(),
            'is_recurring' => true,
            'recurring_frequency'  => 'weekly_mon_thu',
            'recurring_next_date'  => $this->nextRunDate(),
        ]);

        // Notify everyone in the office (rental team)
        $recipients = User::where('email', 'like', '%@autogoco.com')->get();
        foreach ($recipients as $u) {
            $u->notify(new OperationalReminder(
                officeTaskId: $task->id,
                title: $title,
                body: 'Bi-weekly check — EZ Pass + NYC violations.',
                url: '/office-tasks',
                icon: '🚓',
            ));
        }

        $this->info("Opened task #{$task->id} · notified {$recipients->count()} users");
        return 0;
    }

    private function nextRunDate(): Carbon
    {
        $today = Carbon::today();
        // Mon=1, Thu=4
        $next = $today->copy();
        do { $next->addDay(); } while (!in_array($next->dayOfWeek, [Carbon::MONDAY, Carbon::THURSDAY], true));
        return $next;
    }
}
