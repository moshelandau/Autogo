<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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

    /**
     * Reusable customer text search.
     *
     * Matches against first_name, last_name, email, phone, secondary_phone,
     * the concatenated "first_name last_name", and (when multi-word)
     * AND-across-tokens against name fields so word order doesn't matter.
     *
     * Usage:
     *     Customer::query()->search($q)->get()
     *     SomeModel::whereHas('customer', fn($q) => $q->search($search))
     */
    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') return $query;
        $tokens = preg_split('/\s+/', $term);

        return $query->where(function ($w) use ($term, $tokens) {
            $w->where('first_name', 'ilike', "%{$term}%")
              ->orWhere('last_name', 'ilike', "%{$term}%")
              ->orWhere('email', 'ilike', "%{$term}%")
              ->orWhere('phone', 'ilike', "%{$term}%")
              ->orWhere('secondary_phone', 'ilike', "%{$term}%")
              ->orWhereRaw("(coalesce(first_name,'') || ' ' || coalesce(last_name,'')) ilike ?", ["%{$term}%"]);
            if (count($tokens) > 1) {
                $w->orWhere(function ($ww) use ($tokens) {
                    foreach ($tokens as $t) {
                        $ww->where(function ($w3) use ($t) {
                            $w3->where('first_name', 'ilike', "%{$t}%")
                               ->orWhere('last_name', 'ilike', "%{$t}%");
                        });
                    }
                });
            }
        });
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
    public function cards(): HasMany           { return $this->hasMany(CustomerCard::class); }
    public function phones(): HasMany          { return $this->hasMany(CustomerPhone::class)->orderByDesc('is_primary')->orderBy('id'); }
    public function notes(): MorphMany         { return $this->morphMany(Note::class, 'notable')->orderByDesc('created_at'); }

    /**
     * Lookup by the last 10 digits of any phone tied to this customer
     * (primary `phone`, legacy `secondary_phone`, or any `customer_phones`
     * row). Used by the SMS webhook to attach inbound to the right person
     * even when they text from their non-primary number.
     */
    public static function findByAnyPhone(string $phone): ?Customer
    {
        $d = preg_replace('/\D/', '', $phone);
        if (strlen($d) < 10) return null;
        $last10 = substr($d, -10);

        $viaPhones = CustomerPhone::whereRaw("substring(regexp_replace(phone, '\\D', '', 'g') from '.{1,10}\$') = ?", [$last10])
            ->orderByDesc('is_primary')->first();
        if ($viaPhones) return $viaPhones->customer;

        return static::where('phone', 'ilike', "%{$last10}")
            ->orWhere('secondary_phone', 'ilike', "%{$last10}")->first();
    }

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
