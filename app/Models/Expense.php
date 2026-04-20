<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category', 'amount', 'date', 'payee', 'payee_id', 'description',
        'department', 'source_account_id', 'check_to_print', 'user_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'date' => 'date', 'check_to_print' => 'boolean'];
    }

    public function sourceAccount(): BelongsTo { return $this->belongsTo(ChartOfAccount::class, 'source_account_id'); }
    public function printedCheck(): HasOne { return $this->hasOne(PrintedCheck::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
