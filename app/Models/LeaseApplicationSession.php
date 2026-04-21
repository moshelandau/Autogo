<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseApplicationSession extends Model
{
    protected $fillable = [
        'phone', 'flow', 'current_step', 'collected',
        'customer_id', 'deal_id',
        'last_inbound_at', 'completed_at', 'aborted_at',
    ];

    protected function casts(): array
    {
        return [
            'collected'       => 'array',
            'last_inbound_at' => 'datetime',
            'completed_at'    => 'datetime',
            'aborted_at'      => 'datetime',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function deal(): BelongsTo     { return $this->belongsTo(Deal::class); }

    public function isActive(): bool
    {
        return $this->completed_at === null && $this->aborted_at === null;
    }
}
