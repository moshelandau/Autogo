<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'secondary_phone',
        'can_receive_sms', 'address', 'address_2', 'city', 'state', 'zip', 'country',
        'drivers_license_number', 'dl_expiration', 'dl_state', 'date_of_birth',
        'insurance_company', 'insurance_policy', 'credit_score',
        'store_credit_balance', 'cached_outstanding_balance',
        'notes', 'is_active', 'hq_rentals_id',
    ];

    protected function casts(): array
    {
        return [
            'can_receive_sms' => 'boolean',
            'is_active' => 'boolean',
            'dl_expiration' => 'date',
            'date_of_birth' => 'date',
            'store_credit_balance' => 'decimal:2',
            'credit_score' => 'integer',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->address_2,
            $this->city,
            $this->state ? "{$this->state} {$this->zip}" : $this->zip,
        ]);
        return implode(', ', $parts);
    }

    public function notificationLogs()
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function creditPulls(): HasMany
    {
        return $this->hasMany(CreditPull::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class)->latest();
    }

    // Business-history relationships
    public function deals(): HasMany           { return $this->hasMany(Deal::class)->latest(); }
    public function reservations(): HasMany    { return $this->hasMany(Reservation::class)->latest(); }
    public function claims(): HasMany          { return $this->hasMany(Claim::class)->latest(); }
    public function rentalClaims(): HasMany    { return $this->hasMany(RentalClaim::class)->latest(); }
    public function rentalPayments(): HasMany  { return $this->hasMany(RentalPayment::class)->latest(); }
    public function ezPassAccounts(): HasMany  { return $this->hasMany(EzPassAccount::class); }

    /**
     * Compute the live outstanding balance across all reservations.
     * Sum of (total_price - total_paid) for any non-cancelled reservation > 0.
     */
    public function computeOutstandingBalance(): float
    {
        return (float) $this->reservations()
            ->whereNotIn('status', ['cancelled'])
            ->get()
            ->sum(fn ($r) => max(0, (float)$r->total_price - (float)$r->total_paid));
    }

    /** Recompute and persist the cached outstanding balance. */
    public function refreshOutstandingBalance(): void
    {
        $this->update(['cached_outstanding_balance' => $this->computeOutstandingBalance()]);
    }

    public function getHasOutstandingBalanceAttribute(): bool
    {
        return (float) $this->cached_outstanding_balance > 0;
    }
}
