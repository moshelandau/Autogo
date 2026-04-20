<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_number', 'date', 'description',
        'reference_type', 'reference_id', 'user_id', 'is_reconciled',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'is_reconciled' => 'boolean'];
    }

    public static function generateEntryNumber(): string
    {
        $prefix = 'JE-' . date('Ym');
        $latest = self::where('entry_number', 'like', $prefix . '%')
            ->orderByDesc('entry_number')->first();
        $seq = $latest ? (int) substr($latest->entry_number, -4) + 1 : 1;
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function lines(): HasMany { return $this->hasMany(JournalEntryLine::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reference(): MorphTo { return $this->morphTo(); }
}
