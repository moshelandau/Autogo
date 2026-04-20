<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartsOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vehicle_description', 'claim_id', 'status', 'assigned_to',
        'parts_list', 'vendor', 'estimated_cost', 'actual_cost',
        'order_date', 'expected_date', 'received_date', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:2', 'actual_cost' => 'decimal:2',
            'order_date' => 'date', 'expected_date' => 'date', 'received_date' => 'date',
        ];
    }

    public function claim(): BelongsTo { return $this->belongsTo(Claim::class); }
    public function assignedToUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function createdByUser(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function comments(): HasMany { return $this->hasMany(PartsOrderComment::class)->orderByDesc('created_at'); }

    public function scopePending($query) { return $query->where('status', 'pending'); }
    public function scopeOut($query) { return $query->where('status', 'out'); }
}
