<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalClaim extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id', 'reservation_id', 'vehicle_id',
        'status', 'priority', 'brand',
        'damage_description', 'incident_date', 'damage_amount', 'deductible_amount', 'collected_amount',
        'insurance_company', 'insurance_claim_number', 'insurance_contact', 'insurance_phone',
        'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'damage_amount' => 'decimal:2',
            'deductible_amount' => 'decimal:2',
            'collected_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function createdByUser(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function documents(): HasMany { return $this->hasMany(RentalClaimDocument::class); }
    public function comments(): HasMany { return $this->hasMany(RentalClaimComment::class)->orderByDesc('created_at'); }

    public function scopeNew($query) { return $query->where('status', 'new'); }
    public function scopePendingDocuments($query) { return $query->where('status', 'pending_documents'); }
    public function scopeCompleted($query) { return $query->where('status', 'completed'); }
    public function scopeBrand($query, string $brand) { return $query->where('brand', $brand); }
}
