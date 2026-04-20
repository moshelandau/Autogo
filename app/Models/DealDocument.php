<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealDocument extends Model
{
    protected $fillable = ['deal_id', 'name', 'type', 'path', 'mime_type', 'file_size', 'uploaded_by'];

    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }
    public function uploadedByUser(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
