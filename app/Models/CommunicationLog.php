<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommunicationLog extends Model
{
    protected $fillable = [
        'subject_type', 'subject_id', 'customer_id', 'user_id', 'assigned_to',
        'channel', 'direction', 'from', 'to', 'subject', 'body',
        'attachments', 'external_ref', 'status', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['attachments' => 'array', 'sent_at' => 'datetime'];
    }

    public function subject(): MorphTo     { return $this->morphTo(); }
    public function customer(): BelongsTo  { return $this->belongsTo(Customer::class); }
    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function assignee(): BelongsTo  { return $this->belongsTo(User::class, 'assigned_to'); }
}
