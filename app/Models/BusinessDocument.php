<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDocument extends Model
{
    protected $fillable = [
        'name', 'category', 'document_number', 'issue_date', 'expiration_date',
        'status', 'file_path', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'expiration_date' => 'date'];
    }

    public function createdByUser(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiration_date && $this->expiration_date->isPast();
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->expiration_date && $this->expiration_date->isBetween(now(), now()->addDays(30));
    }

    public function scopeByCategory($query, string $cat) { return $query->where('category', $cat); }
    public function scopeExpired($query) { return $query->whereDate('expiration_date', '<', today()); }
    public function scopeExpiringSoon($query) { return $query->whereDate('expiration_date', '<=', today()->addDays(30))->whereDate('expiration_date', '>=', today()); }
}
