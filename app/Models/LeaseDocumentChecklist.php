<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaseDocumentChecklist extends Model
{
    protected $fillable = ['customer_id', 'deal_id', 'status', 'notes', 'created_by'];

    public const REQUIRED_DOCUMENTS = [
        'Application',
        'Driver License',
        'Lease Agreement',
        'Window Sticker',
        'Insurance',
        'Damage Waiver',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }
    public function createdByUser(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany { return $this->hasMany(LeaseDocumentItem::class)->orderBy('sort_order'); }

    public function getCollectedCountAttribute(): int
    {
        return $this->items()->where('is_collected', true)->count();
    }

    public function getTotalCountAttribute(): int
    {
        return $this->items()->count();
    }

    public function getIsCompleteAttribute(): bool
    {
        return $this->collected_count === $this->total_count && $this->total_count > 0;
    }

    public function generateItems(): void
    {
        foreach (self::REQUIRED_DOCUMENTS as $i => $name) {
            $this->items()->firstOrCreate(['name' => $name], ['sort_order' => $i]);
        }
    }

    public function checkCompletion(): void
    {
        if ($this->is_complete) {
            $this->update(['status' => 'complete']);
        }
    }
}
