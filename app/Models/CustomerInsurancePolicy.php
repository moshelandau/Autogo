<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInsurancePolicy extends Model
{
    protected $fillable = [
        'customer_id', 'carrier', 'policy_number', 'naic', 'named_insured',
        'effective_date', 'expiration_date', 'image_path', 'ocr',
        'verified_at', 'verified_by', 'is_current', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_date'  => 'date',
            'expiration_date' => 'date',
            'verified_at'     => 'datetime',
            'is_current'      => 'boolean',
            'ocr'             => 'array',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function verifiedBy(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }

    public function isExpired(): bool
    {
        return $this->expiration_date && $this->expiration_date->isPast();
    }
}
