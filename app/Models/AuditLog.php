<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'user_name', 'method', 'path',
        'subject_type', 'subject_id', 'action',
        'changes', 'params',
        'ip_address', 'user_agent', 'source',
        'status_code', 'duration_ms',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array', 'params' => 'array'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function subject(): MorphTo { return $this->morphTo(); }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \RuntimeException('AuditLog is append-only.'));
        static::deleting(fn () => throw new \RuntimeException('AuditLog cannot be deleted.'));
    }
}
