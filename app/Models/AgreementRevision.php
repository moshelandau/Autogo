<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgreementRevision extends Model
{
    public const UPDATED_AT = null; // append-only — no updates

    protected $fillable = [
        'reservation_id', 'document_type', 'action', 'pdf_path',
        'sha256', 'prev_sha256', 'snapshot',
        'created_by', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function createdBy(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }

    // Guard against updates — append-only
    protected static function booted(): void
    {
        static::updating(fn () => throw new \RuntimeException('AgreementRevisions are append-only.'));
        static::deleting(fn () => throw new \RuntimeException('AgreementRevisions cannot be deleted.'));
    }
}
