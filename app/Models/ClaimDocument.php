<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimDocument extends Model
{
    protected $fillable = [
        'claim_id', 'name', 'type', 'path', 'mime_type', 'file_size', 'uploaded_by',
    ];

    public function claim(): BelongsTo { return $this->belongsTo(Claim::class); }
    public function uploadedByUser(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
