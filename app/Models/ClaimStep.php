<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimStep extends Model
{
    protected $fillable = [
        'claim_id', 'name', 'sort_order',
        'is_completed', 'completed_at', 'completed_by', 'notes',
    ];

    protected function casts(): array
    {
        return ['is_completed' => 'boolean', 'completed_at' => 'datetime'];
    }

    public function claim(): BelongsTo { return $this->belongsTo(Claim::class); }
    public function completedByUser(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }

    public function markComplete(?int $userId = null): void
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
            'completed_by' => $userId ?? auth()->id(),
        ]);
    }

    public function markIncomplete(): void
    {
        $this->update([
            'is_completed' => false,
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }
}
