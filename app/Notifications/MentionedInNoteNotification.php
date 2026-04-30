<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Note;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Database-channel notification fired when a user is @-mentioned in a note
 * body or in a comment reply. Shape matches the bell dropdown's expected
 * keys (title/body/icon/url) so it slots into HandleInertiaRequests
 * without any extra adapter.
 */
class MentionedInNoteNotification extends Notification
{
    use Queueable;

    public function __construct(public Note $note, public User $mentionedBy) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'         => 'note_mention',
            'note_id'      => $this->note->id,
            'mentioned_by' => $this->mentionedBy->name,
            'subject'      => $this->note->subject,
            'preview'      => mb_substr((string) $this->note->body, 0, 140),
            'url'          => $this->urlForNote(),
            'title'        => "{$this->mentionedBy->name} mentioned you",
            'body'         => $this->note->subject ?: mb_substr((string) $this->note->body, 0, 100),
            'icon'         => '💬',
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
