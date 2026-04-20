<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_name', 'logo_path', 'routing_number', 'account_number',
        'check_start_number', 'next_check_number', 'account_holder_name',
        'account_holder_address', 'is_active', 'chart_account_id',
    ];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function printedChecks(): HasMany { return $this->hasMany(PrintedCheck::class); }
    public function chartAccount(): BelongsTo { return $this->belongsTo(ChartOfAccount::class, 'chart_account_id'); }

    public function getNextCheckNumberAndIncrement(): int
    {
        $number = $this->next_check_number;
        $this->increment('next_check_number');
        return $number;
    }
}
