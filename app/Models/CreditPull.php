<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditPull extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id', 'deal_id', 'type', 'status', 'provider',
        'first_name', 'last_name', 'date_of_birth', 'ssn_last4',
        'address', 'city', 'state', 'zip',
        'credit_score', 'credit_score_model', 'credit_tier', 'bureau',
        'full_report', 'report_pdf_path',
        'pulled_by', 'permissible_purpose', 'ip_address',
        'customer_consent', 'consent_at', 'expires_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'full_report' => 'array',
            'customer_consent' => 'boolean',
            'consent_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }
    public function pulledByUser(): BelongsTo { return $this->belongsTo(User::class, 'pulled_by'); }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsValidAttribute(): bool
    {
        return $this->status === 'completed' && !$this->is_expired;
    }

    public function getTierLabelAttribute(): string
    {
        return match ($this->credit_tier) {
            'tier_1' => 'Tier 1 (Excellent 720+)',
            'tier_2' => 'Tier 2 (Good 680-719)',
            'tier_3' => 'Tier 3 (Fair 640-679)',
            'tier_4' => 'Tier 4 (Subprime <640)',
            default => 'Unknown',
        };
    }

    public static function tierFromScore(?int $score): ?string
    {
        if ($score === null) return null;
        if ($score >= 720) return 'tier_1';
        if ($score >= 680) return 'tier_2';
        if ($score >= 640) return 'tier_3';
        return 'tier_4';
    }

    public function scopeValid($query) { return $query->where('status', 'completed')->where('expires_at', '>=', now()); }
    public function scopeSoft($query) { return $query->where('type', 'soft'); }
    public function scopeHard($query) { return $query->where('type', 'hard'); }
}
