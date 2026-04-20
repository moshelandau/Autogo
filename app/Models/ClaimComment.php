<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimComment extends Model
{
    protected $fillable = ['claim_id', 'body', 'user_id'];

    public function claim(): BelongsTo { return $this->belongsTo(Claim::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
