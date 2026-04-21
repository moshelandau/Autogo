<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationHold extends Model
{
    protected $fillable = [
        'reservation_id', 'amount',
        'card_brand', 'card_last4', 'card_exp', 'card_token',
        'sola_authorization_id',
        'status', 'placed_at', 'released_at', 'captured_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'placed_at'    => 'datetime',
            'released_at'  => 'datetime',
            'captured_at'  => 'datetime',
        ];
    }

    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
}
