<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealActionItem extends Model
{
    protected $fillable = [
        'deal_id', 'title',
        'is_completed', 'completed_at', 'completed_by',
        'due_date', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
            'due_date'     => 'date',
        ];
    }

    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }
    public function completedBy(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
