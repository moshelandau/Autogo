<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationItem extends Model
{
    use HasFactory;

    protected $fillable = ['bank_reconciliation_id', 'journal_entry_line_id', 'is_cleared'];

    protected function casts(): array { return ['is_cleared' => 'boolean']; }

    public function reconciliation(): BelongsTo { return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id'); }
    public function journalEntryLine(): BelongsTo { return $this->belongsTo(JournalEntryLine::class); }
}
