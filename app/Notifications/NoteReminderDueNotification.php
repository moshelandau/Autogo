<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Note;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fired by `php artisan notes:reminders-notify` when a note's reminder
 * date is reached. Channel is `database` so it pings the bell — same
 * shape as MentionedInNoteNotification and OperationalReminder.
 */
class NoteReminderDueNotification extends Notification
{
    use Queueable;

    public function __construct(public Note $note) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'note_reminder',
            'note_id' => $this->note->id,
            'subject' => $this->note->subject,
            'preview' => mb_substr((string) $this->note->body, 0, 140),
            'url'     => $this->urlForNote(),
            'title'   => '⏰ Reminder: ' . ($this->note->subject ?: 'Note') ,
            'body'    => mb_substr((string) $this->note->body, 0, 100),
            'icon'    => '⏰',
        ];
    }

    private function urlForNote(): string
    {
        $type = (string) $this->note->notable_type;
        $id   = $this->note->notable_id;
        return match (true) {
            str_ends_with($type, 'Deal')     => "/leasing/deals/{$id}?tab=notes#note-{$this->note->id}",
            str_ends_with($type, 'Customer') => "/customers/{$id}?tab=notes#note-{$this->note->id}",
            default                          => '#note-' . $this->note->id,
        };
    }
}
