<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleReturn extends Model
{
    protected $fillable = [
        'deal_id', 'return_type',
        'vin', 'year', 'make', 'model', 'trim', 'color', 'odometer',
        'condition',
        'payoff_amount', 'allowance', 'acv',
        'payoff_to', 'payoff_good_through',
        'current_plate', 'plate_transfer',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'payoff_amount' => 'decimal:2',
            'allowance'     => 'decimal:2',
            'acv'           => 'decimal:2',
            'plate_transfer'=> 'boolean',
            'payoff_good_through' => 'date',
        ];
    }

    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }
}
