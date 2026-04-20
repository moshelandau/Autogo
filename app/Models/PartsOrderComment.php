<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartsOrderComment extends Model
{
    protected $fillable = ['parts_order_id', 'body', 'user_id'];

    public function partsOrder(): BelongsTo { return $this->belongsTo(PartsOrder::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
