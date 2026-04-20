<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimInsuranceEntry extends Model
{
    protected $fillable = [
        'claim_id', 'insurance_company', 'claim_number', 'policy_number',
        'contact_name', 'contact_phone', 'contact_email', 'notes',
    ];

    public function claim(): BelongsTo { return $this->belongsTo(Claim::class); }
}
