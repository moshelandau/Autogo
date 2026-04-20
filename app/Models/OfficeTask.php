<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title', 'description', 'section', 'priority',
        'assigned_to', 'created_by', 'due_date',
        'is_completed', 'completed_at',
        'is_recurring', 'recurring_frequency', 'recurring_next_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
            'is_recurring' => 'boolean',
            'due_date' => 'date',
            'recurring_next_date' => 'date',
        ];
    }

    public function assignedToUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function createdByUser(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function comments(): HasMany { return $this->hasMany(OfficeTaskComment::class)->orderByDesc('created_at'); }

    public function markComplete(): void
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
            'section' => 'completed',
        ]);
    }

    public function markIncomplete(): void
    {
        $this->update([
            'is_completed' => false,
            'completed_at' => null,
            'section' => 'todo',
        ]);
    }

    public function moveToToday(): void { $this->update(['section' => 'today']); }
    public function moveToTodo(): void { $this->update(['section' => 'todo']); }

    public function scopeToday($query) { return $query->where('section', 'today')->where('is_completed', false); }
    public function scopeTodo($query) { return $query->where('section', 'todo')->where('is_completed', false); }
    public function scopeRecurring($query) { return $query->where('is_recurring', true); }
    public function scopeCompleted($query) { return $query->where('is_completed', true); }
    public function scopeForUser($query, int $userId) { return $query->where('assigned_to', $userId); }
}
