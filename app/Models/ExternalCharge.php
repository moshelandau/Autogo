<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalCharge extends Model
{
    protected $fillable = [
        'reservation_id', 'vehicle_id', 'label', 'amount', 'charge_date',
        'provider', 'reference', 'payment_status', 'notes',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'charge_date' => 'date'];
    }

    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
}
