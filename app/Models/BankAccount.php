<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'bank_name', 'account_number_last4', 'chart_account_id', 'current_balance', 'is_active'];

    protected function casts(): array
    {
        return ['current_balance' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function chartAccount(): BelongsTo { return $this->belongsTo(ChartOfAccount::class, 'chart_account_id'); }
    public function reconciliations(): HasMany { return $this->hasMany(BankReconciliation::class); }
}
