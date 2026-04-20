<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Claim extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id', 'status',
        'story', 'accident_date', 'accident_location', 'customer_phone',
        'adjuster_name', 'adjuster_phone', 'adjuster_email',
        'appraiser_name', 'appraiser_phone', 'appraiser_email',
        'vehicle_year', 'vehicle_make', 'vehicle_model', 'vehicle_vin', 'vehicle_plate',
        'estimate_amount', 'supplement_amount', 'approved_amount', 'paid_amount',
        'towing_amount', 'rental_amount',
        'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'accident_date' => 'date',
            'estimate_amount' => 'decimal:2',
            'supplement_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'towing_amount' => 'decimal:2',
            'rental_amount' => 'decimal:2',
        ];
    }

    public const STEP_NAMES = [
        'Filed Claim',
        'Adjuster Assigned',
        'Appraiser Assigned',
        'Estimate Approved',
        'Received Estimate Payment',
        'Towing',
        'Towing Payment',
        'Rental Payment Request',
        'Received Rental Payment',
    ];

    public function getVehicleDisplayAttribute(): string
    {
        if (!$this->vehicle_make) return 'No vehicle info';
        return trim("{$this->vehicle_year} {$this->vehicle_make} {$this->vehicle_model}");
    }

    public function getCompletedStepsCountAttribute(): int
    {
        return $this->steps()->where('is_completed', true)->count();
    }

    public function getTotalStepsCountAttribute(): int
    {
        return $this->steps()->count();
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function createdByUser(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function insuranceEntries(): HasMany { return $this->hasMany(ClaimInsuranceEntry::class); }
    public function steps(): HasMany { return $this->hasMany(ClaimStep::class)->orderBy('sort_order'); }
    public function supplements(): HasMany { return $this->hasMany(ClaimSupplement::class)->orderByDesc('requested_date'); }
    public function documents(): HasMany { return $this->hasMany(ClaimDocument::class); }
    public function comments(): HasMany { return $this->hasMany(ClaimComment::class)->orderByDesc('created_at'); }

    public function scopeNew($query) { return $query->where('status', 'new'); }
    public function scopeFiled($query) { return $query->where('status', 'filed'); }
    public function scopeInProgress($query) { return $query->where('status', 'in_progress'); }
    public function scopeCompleted($query) { return $query->where('status', 'completed'); }

    /**
     * Generate the 9 standard steps for a new claim.
     */
    public function generateSteps(): void
    {
        foreach (self::STEP_NAMES as $i => $name) {
            $this->steps()->firstOrCreate(
                ['name' => $name],
                ['sort_order' => $i]
            );
        }
    }
}
