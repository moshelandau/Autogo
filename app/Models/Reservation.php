<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reservation_number', 'customer_id', 'vehicle_id', 'vehicle_class',
        'pickup_location_id', 'return_location_id',
        'pickup_date', 'return_date', 'actual_pickup_date', 'actual_return_date',
        'daily_rate', 'total_days', 'subtotal', 'tax_amount', 'discount_amount',
        'addons_total', 'total_price', 'total_paid', 'total_refunded',
        'security_deposit', 'outstanding_balance', 'status',
        'odometer_out', 'odometer_in', 'fuel_out', 'fuel_in',
        'pickup_notes', 'return_notes', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'pickup_date' => 'datetime',
            'return_date' => 'datetime',
            'actual_pickup_date' => 'datetime',
            'actual_return_date' => 'datetime',
            'daily_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'addons_total' => 'decimal:2',
            'total_price' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'total_refunded' => 'decimal:2',
            'security_deposit' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
        ];
    }

    public static function generateReservationNumber(): string
    {
        $latest = self::withTrashed()->orderByDesc('id')->first();
        $next = $latest ? $latest->id + 1 : 1;
        return str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function pickupLocation(): BelongsTo { return $this->belongsTo(Location::class, 'pickup_location_id'); }
    public function returnLocation(): BelongsTo { return $this->belongsTo(Location::class, 'return_location_id'); }
    public function createdByUser(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function addons(): HasMany { return $this->hasMany(ReservationAddon::class); }
    public function payments(): HasMany { return $this->hasMany(RentalPayment::class); }
    public function externalCharges(): HasMany { return $this->hasMany(ExternalCharge::class); }
    public function inspections(): HasMany { return $this->hasMany(ReservationInspection::class); }
    public function holds(): HasMany { return $this->hasMany(ReservationHold::class)->latest(); }
    public function additionalDrivers(): HasMany { return $this->hasMany(ReservationAdditionalDriver::class); }
    public function agreementRevisions(): HasMany { return $this->hasMany(AgreementRevision::class)->latest('id'); }
    public function signatures() {
        return $this->morphMany(Signature::class, 'signable')->latest('signed_at');
    }
    public function activeHold() { return $this->hasOne(ReservationHold::class)->where('status', 'authorized')->latest(); }

    public function pickupInspections(): HasMany { return $this->hasMany(ReservationInspection::class)->where('type', 'pickup'); }

    public function returnInspections(): HasMany { return $this->hasMany(ReservationInspection::class)->where('type', 'return'); }

    public function scopeOnRent($query) { return $query->where('status', 'rental'); }
    public function scopeOpen($query) { return $query->where('status', 'open'); }

    public function scopeTodaysPickups($query)
    {
        return $query->whereDate('pickup_date', today())
            ->whereIn('status', ['open', 'rental']);
    }

    public function scopeTodaysReturns($query)
    {
        return $query->whereDate('return_date', today())
            ->where('status', 'rental');
    }

    public function recalculateTotals(): void
    {
        $this->total_days = max(1, (int) $this->pickup_date->diffInDays($this->return_date));
        $this->subtotal = $this->daily_rate * $this->total_days;
        $this->addons_total = $this->addons()->sum('total');
        $this->total_price = $this->subtotal + $this->addons_total + $this->tax_amount - $this->discount_amount;
        $this->outstanding_balance = $this->total_price - $this->total_paid + $this->total_refunded;
        $this->save();
    }
}
