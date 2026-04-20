<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalClaimDocument extends Model
{
    protected $fillable = ['rental_claim_id', 'name', 'type', 'path', 'uploaded_by'];

    public function rentalClaim(): BelongsTo { return $this->belongsTo(RentalClaim::class); }
    public function uploadedByUser(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
