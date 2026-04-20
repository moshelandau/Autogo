<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealQuote extends Model
{
    protected $fillable = [
        'deal_id', 'lender_id', 'payment_type', 'term', 'mileage_per_year',
        'monthly_payment', 'das', 'sell_price', 'msrp', 'rebates',
        'acquisition_fee', 'acquisition_fee_type', 'residual_value',
        'money_factor', 'apr', 'tax_breakdown', 'is_selected', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'monthly_payment' => 'decimal:2', 'das' => 'decimal:2', 'sell_price' => 'decimal:2',
            'msrp' => 'decimal:2', 'rebates' => 'decimal:2', 'acquisition_fee' => 'decimal:2',
            'residual_value' => 'decimal:2', 'money_factor' => 'decimal:6', 'apr' => 'decimal:3',
            'tax_breakdown' => 'array', 'is_selected' => 'boolean',
        ];
    }

    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }
    public function lender(): BelongsTo { return $this->belongsTo(Lender::class); }
    public function createdByUser(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
