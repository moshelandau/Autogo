<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintedCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'check_account_id', 'check_number', 'vendor_payment_id', 'vendor_id',
        'expense_id', 'amount', 'payee_name', 'memo', 'check_date',
        'printed_at', 'voided_at', 'user_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'check_date' => 'date', 'printed_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function checkAccount(): BelongsTo { return $this->belongsTo(CheckAccount::class); }
    public function expense(): BelongsTo { return $this->belongsTo(Expense::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function isVoided(): bool { return $this->voided_at !== null; }
}
