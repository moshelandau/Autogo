<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LenderProgram extends Model
{
    protected $fillable = [
        'lender_id', 'program_type', 'make', 'model', 'year', 'trim',
        'term', 'annual_mileage', 'residual_pct', 'money_factor', 'apr',
        'acquisition_fee', 'disposition_fee',
        'min_credit_score', 'credit_tier', 'max_msrp',
        'valid_from', 'valid_until', 'is_active', 'source', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'residual_pct' => 'decimal:2',
            'money_factor' => 'decimal:6',
            'apr' => 'decimal:3',
            'acquisition_fee' => 'decimal:2',
            'disposition_fee' => 'decimal:2',
            'max_msrp' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function lender(): BelongsTo { return $this->belongsTo(Lender::class); }
    public function createdByUser(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function getAprFromMoneyFactorAttribute(): ?float
    {
        return $this->money_factor ? round((float) $this->money_factor * 2400, 2) : null;
    }

    public function scopeActive($query) { return $query->where('is_active', true)->where('valid_until', '>=', today()); }
    public function scopeForVehicle($query, string $make, string $model, ?int $year = null)
    {
        return $query->where(function ($q) use ($make) {
            $q->where('make', $make)->orWhereNull('make');
        })->where(function ($q) use ($model) {
            $q->where('model', $model)->orWhereNull('model');
        });
    }
}
