<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vin', 'year', 'make', 'model', 'trim', 'color', 'license_plate',
        'vehicle_class', 'status', 'location_id', 'odometer', 'fuel_level',
        'daily_rate', 'weekly_rate', 'monthly_rate',
        'purchase_date', 'purchase_price', 'image_path', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'daily_rate' => 'decimal:2',
            'weekly_rate' => 'decimal:2',
            'monthly_rate' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'purchase_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->year} {$this->make} {$this->model}" . ($this->trim ? " {$this->trim}" : '');
    }

    public function getDisplayWithPlateAttribute(): string
    {
        return "{$this->display_name}" . ($this->license_plate ? " - {$this->license_plate}" : '');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(VehicleImage::class)->orderBy('sort_order');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class);
    }

    public function externalCharges(): HasMany
    {
        return $this->hasMany(ExternalCharge::class);
    }

    public function activeReservation()
    {
        return $this->hasOne(Reservation::class)
            ->where('status', 'rental')
            ->latest('pickup_date');
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available')->where('is_active', true);
    }

    public function scopeByClass($query, string $class)
    {
        return $query->where('vehicle_class', $class);
    }

    public function scopeAtLocation($query, int $locationId)
    {
        return $query->where('location_id', $locationId);
    }
}
