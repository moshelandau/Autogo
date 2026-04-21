<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class VehicleViolation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vehicle_id', 'reservation_id', 'customer_id',
        'plate', 'plate_state',
        'type', 'jurisdiction', 'issuing_agency',
        'summons_number', 'citation_number', 'issue_number',
        'issued_at', 'due_date', 'location', 'borough_or_county',
        'fine_amount', 'late_fee', 'admin_fee', 'paid_amount', 'total_due',
        'status', 'photo_path', 'document_path', 'evidence',
        'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at'   => 'datetime',
            'due_date'    => 'date',
            'fine_amount' => 'decimal:2',
            'late_fee'    => 'decimal:2',
            'admin_fee'   => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'total_due'   => 'decimal:2',
            'evidence'    => 'array',
        ];
    }

    public const TYPES = [
        'parking'           => '🅿️ Parking',
        'red_light_camera'  => '🚦 Red-light camera',
        'speed_camera'      => '⏱ Speed camera',
        'bus_lane_camera'   => '🚌 Bus-lane camera',
        'school_bus_camera' => '🚸 School bus camera',
        'toll_evasion'      => '🛣 Toll evasion',
        'registration'      => '📋 Registration',
        'inspection'        => '🔍 Inspection',
        'moving_violation'  => '🚓 Moving violation',
        'other'             => '❓ Other',
    ];

    public const STATUSES = [
        'new'              => 'New',
        'received'         => 'Received (entered)',
        'renter_notified'  => 'Renter notified',
        'renter_billed'    => 'Billed to renter',
        'paid_by_renter'   => 'Paid by renter',
        'paid_by_us'       => 'Paid by us',
        'disputed'         => 'Disputed',
        'dismissed'        => 'Dismissed',
    ];

    public function vehicle(): BelongsTo     { return $this->belongsTo(Vehicle::class); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function customer(): BelongsTo    { return $this->belongsTo(Customer::class); }
    public function createdBy(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }

    /** Auto-link a violation to the rental that was active on the issued date. */
    public function autoLink(): void
    {
        if (!$this->plate || !$this->issued_at) return;
        $vehicle = Vehicle::whereRaw('UPPER(REPLACE(license_plate, \' \', \'\')) = ?',
            [strtoupper(preg_replace('/\s+/', '', $this->plate))])->first();
        if (!$vehicle) return;

        $reservation = Reservation::where('vehicle_id', $vehicle->id)
            ->where('pickup_date', '<=', $this->issued_at)
            ->where(function ($q) {
                $q->whereNull('actual_return_date')
                  ->orWhere('actual_return_date', '>=', $this->issued_at);
            })
            ->latest('pickup_date')->first();

        $this->vehicle_id = $vehicle->id;
        if ($reservation) {
            $this->reservation_id = $reservation->id;
            $this->customer_id    = $reservation->customer_id;
        }
    }

    /** Recalc total_due whenever amounts change. */
    public function recalcTotalDue(): void
    {
        $this->total_due = max(0,
            (float) $this->fine_amount + (float) $this->late_fee + (float) $this->admin_fee - (float) $this->paid_amount);
    }
}
