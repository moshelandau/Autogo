<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodClosing extends Model
{
    use HasFactory;

    protected $fillable = ['period_start', 'period_end', 'journal_entry_id', 'user_id', 'closed_at', 'notes'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'closed_at' => 'datetime'];
    }

    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
