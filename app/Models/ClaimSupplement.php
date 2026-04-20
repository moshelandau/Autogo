<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimSupplement extends Model
{
    protected $fillable = [
        'claim_id', 'amount', 'requested_date', 'approved_date',
        'status', 'description', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_date' => 'date',
            'approved_date' => 'date',
        ];
    }

    public function claim(): BelongsTo { return $this->belongsTo(Claim::class); }
}
