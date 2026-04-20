<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalClaimComment extends Model
{
    protected $fillable = ['rental_claim_id', 'body', 'user_id'];

    public function rentalClaim(): BelongsTo { return $this->belongsTo(RentalClaim::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
