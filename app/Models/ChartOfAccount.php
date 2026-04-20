<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'type', 'subtype', 'parent_id',
        'description', 'department', 'is_active', 'is_system',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean'];
    }

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function journalEntryLines(): HasMany { return $this->hasMany(JournalEntryLine::class, 'account_id'); }
    public function bankAccount(): HasMany { return $this->hasMany(BankAccount::class, 'chart_account_id'); }
}
