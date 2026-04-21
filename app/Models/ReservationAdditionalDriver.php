<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationAdditionalDriver extends Model
{
    protected $fillable = [
        'reservation_id', 'name', 'phone', 'email',
        'dl_number', 'dl_state', 'dl_expiration', 'dl_image_path',
        'is_primary_contact',
        'cc_brand', 'cc_last4', 'cc_exp', 'cc_token',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary_contact' => 'boolean',
            'dl_expiration'      => 'date',
        ];
    }

    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
}
