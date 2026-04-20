<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealNote extends Model
{
    protected $fillable = ['deal_id', 'body', 'user_id'];

    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
