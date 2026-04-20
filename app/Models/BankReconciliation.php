<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    use HasFactory;

    protected $fillable = ['bank_account_id', 'statement_date', 'statement_balance', 'adjusted_balance', 'status', 'user_id', 'completed_at'];

    protected function casts(): array
    {
        return ['statement_date' => 'date', 'statement_balance' => 'decimal:2', 'adjusted_balance' => 'decimal:2', 'completed_at' => 'datetime'];
    }

    public function bankAccount(): BelongsTo { return $this->belongsTo(BankAccount::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(BankReconciliationItem::class); }
}
