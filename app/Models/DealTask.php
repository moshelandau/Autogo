<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealTask extends Model
{
    protected $fillable = [
        'deal_id', 'name', 'stage', 'sort_order',
        'is_completed', 'completed_at', 'completed_by', 'due_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
            'due_date' => 'date',
        ];
    }

    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }
    public function completedByUser(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }

    public function markComplete(?int $userId = null): void
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
            'completed_by' => $userId ?? auth()->id(),
        ]);
    }

    public function scopeOverdue($query) { return $query->where('is_completed', false)->where('due_date', '<', today()); }
    public function scopeIncomplete($query) { return $query->where('is_completed', false); }
}
