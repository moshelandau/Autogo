<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationAddon extends Model
{
    protected $fillable = ['reservation_id', 'name', 'type', 'rate', 'quantity', 'total'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
}
