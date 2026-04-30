<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic note that can attach to any model (Deal, Customer, etc.) and
 * carry a thread of comments, an activity log, optional assignees, and an
 * optional reminder_date that turns it into a TODO.
 */
class Note extends Model
{
    protected $fillable = [
        'notable_type', 'notable_id',
        'subject', 'body',
        'reminder_date', 'is_resolved',
        'user_id', 'assigned_to',
    ];

    protected $casts = [
        'reminder_date' => 'date',
        'is_resolved'   => 'boolean',
    ];

    public function notable(): MorphTo { return $this->morphTo(); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'note_user')->withPivot('email_sent')->withTimestamps();
    }
    public function comments(): HasMany { return $this->hasMany(NoteComment::class)->orderBy('created_at'); }
    public function activities(): HasMany { return $this->hasMany(NoteActivity::class)->orderBy('created_at'); }

    /**
     * Notes whose reminder is due (today or earlier) and not yet resolved.
     * Visible to the given user if they are an assignee, OR if no one is
     * assigned (un-owned reminders ping everyone).
     */
    public function scopePendingReminders(Builder $q, int $userId): Builder
    {
        return $q->whereNotNull('reminder_date')
            ->whereDate('reminder_date', '<=', now()->toDateString())
            ->where('is_resolved', false)
            ->where(function (Builder $w) use ($userId) {
                $w->whereHas('assignedUsers', fn (Builder $sq) => $sq->where('users.id', $userId))
                  ->orWhereDoesntHave('assignedUsers');
            });
    }
}
