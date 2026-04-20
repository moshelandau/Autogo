<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturerRebate extends Model
{
    protected $fillable = [
        'name', 'rebate_type', 'amount', 'make', 'model', 'year',
        'eligibility_notes', 'valid_from', 'valid_until',
        'is_active', 'stackable', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
            'stackable' => 'boolean',
        ];
    }

    public function createdByUser(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeActive($query) { return $query->where('is_active', true)->where('valid_until', '>=', today()); }
}
