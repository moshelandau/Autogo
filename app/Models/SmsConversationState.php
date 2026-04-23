<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsConversationState extends Model
{
    protected $fillable = ['phone_last10', 'resolved_at', 'resolved_by', 'resolve_note'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }

    /** Normalize any phone string to its last 10 digits. */
    public static function normalize(string $phone): string
    {
        $d = preg_replace('/\D/', '', $phone);
        return strlen($d) >= 10 ? substr($d, -10) : $d;
    }
}
