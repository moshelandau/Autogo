<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TowJob extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'job_number', 'customer_id', 'claim_id', 'tow_truck_id', 'tow_driver_id',
        'caller_name', 'caller_phone', 'insurance_company', 'reference_number',
        'vehicle_year', 'vehicle_make', 'vehicle_model', 'vehicle_color', 'vehicle_plate', 'vehicle_vin',
        'pickup_address', 'pickup_city', 'pickup_state', 'pickup_zip', 'pickup_lat', 'pickup_lng',
        'dropoff_address', 'dropoff_city', 'dropoff_state', 'dropoff_zip',
        'status', 'priority', 'reason',
        'quoted_amount', 'billed_amount', 'paid_amount',
        'requested_at', 'dispatched_at', 'on_scene_at', 'completed_at',
        'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_at'   => 'datetime',
            'dispatched_at'  => 'datetime',
            'on_scene_at'    => 'datetime',
            'completed_at'   => 'datetime',
            'pickup_lat'     => 'decimal:7',
            'pickup_lng'     => 'decimal:7',
            'quoted_amount'  => 'decimal:2',
            'billed_amount'  => 'decimal:2',
            'paid_amount'    => 'decimal:2',
        ];
    }

    public const STATUSES = ['pending','dispatched','en_route','on_scene','in_transit','completed','cancelled'];
    public const REASONS  = ['accident','breakdown','repo','illegal_parking','transport','other'];

    public function customer(): BelongsTo  { return $this->belongsTo(Customer::class); }
    public function claim(): BelongsTo     { return $this->belongsTo(Claim::class); }
    public function truck(): BelongsTo     { return $this->belongsTo(TowTruck::class, 'tow_truck_id'); }
    public function driver(): BelongsTo    { return $this->belongsTo(TowDriver::class, 'tow_driver_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function getVehicleDisplayAttribute(): string
    {
        return trim("{$this->vehicle_year} {$this->vehicle_make} {$this->vehicle_model}") ?: '—';
    }
}
