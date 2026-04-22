<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCard extends Model
{
    protected $fillable = [
        'customer_id', 'account', 'x_token', 'brand', 'last4', 'exp',
        'cardholder', 'label', 'is_default', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'x_token'    => 'encrypted',  // double-protect at rest
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }

    /** Display string for UI dropdowns. */
    public function getDisplayAttribute(): string
    {
        return strtoupper($this->brand ?? 'CARD') . ' •••• ' . ($this->last4 ?? '????') . ' (exp ' . ($this->exp ?? '??/??') . ')';
    }
}
