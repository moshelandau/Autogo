<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Note;
use App\Models\User;
use App\Notifications\NoteReminderDueNotification;
use Illuminate\Console\Command;

/**
 * Walks the notes table for any reminders that are due (today or earlier),
 * not yet resolved, and whose pivot rows still have email_sent=false. For
 * each such note we fire a database notification to every assignee, then
 * flip their pivot to email_sent=true so we don't keep re-pinging.
 *
 * If a note has no assignees at all, every active user with the
 * `can_view_notes` page permission gets the ping (un-owned reminders are
 * everyone's problem).
 */
class NoteRemindersNotifyCommand extends Command
{
    protected $signature = 'notes:reminders-notify';
    protected $description = 'Send bell notifications for any due note reminders.';

    public function handle(): int
    {
        $today = now()->toDateString();

        $notes = Note::query()
            ->whereNotNull('reminder_date')
            ->whereDate('reminder_date', '<=', $today)
            ->where('is_resolved', false)
            ->get();

        $sent = 0;
        foreach ($notes as $note) {
            $assignees = $note->assignedUsers()->wherePivot('email_sent', false)->get();
            if ($assignees->isEmpty() && $note->assignedUsers()->count() === 0) {
                // Un-owned reminder → ping every active user once.
                $alreadyPingedKey = '_unassigned_reminder_pinged_' . $note->id;
                if (cache()->has($alreadyPingedKey)) continue;
                foreach (User::all() as $u) {
                    $u->notify(new NoteReminderDueNotification($note));
                    $sent++;
                }
                cache()->forever($alreadyPingedKey, true);
                continue;
            }
            foreach ($assignees as $u) {
                $u->notify(new NoteReminderDueNotification($note));
                $note->assignedUsers()->updateExistingPivot($u->id, ['email_sent' => true]);
                $sent++;
            }
        }

        $this->info("Sent {$sent} reminder notification(s).");
        return self::SUCCESS;
    }
}
