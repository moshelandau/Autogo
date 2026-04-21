<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Generic operational reminder shown in the bell-icon notification center.
 * Tied to an OfficeTask — when the task is completed, the notification clears.
 */
class OperationalReminder extends Notification
{
    use Queueable;

    public function __construct(
        public int $officeTaskId,
        public string $title,
        public string $body = '',
        public string $url = '#',
        public string $icon = '🔔',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'office_task_id' => $this->officeTaskId,
            'title'          => $this->title,
            'body'           => $this->body,
            'url'            => $this->url,
            'icon'           => $this->icon,
        ];
    }
}
