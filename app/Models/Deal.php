<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'deal_number', 'customer_id', 'salesperson_id',
        'vehicle_vin', 'vehicle_year', 'vehicle_make', 'vehicle_model',
        'vehicle_trim', 'vehicle_color', 'vehicle_odometer',
        'payment_type', 'stage', 'priority',
        'msrp', 'invoice_price', 'sell_price', 'cost', 'profit',
        'monthly_payment', 'term', 'mileage_per_year', 'drive_off',
        'trade_allowance', 'trade_acv', 'trade_payoff', 'trade_is_leased',
        'credit_score', 'customer_zip',
        'lender_id', 'lender_status', 'lender_notes',
        'notes', 'deal_start_date', 'deal_expiration_date',
        'won_at', 'lost_at', 'lost_reason',
    ];

    protected function casts(): array
    {
        return [
            'msrp' => 'decimal:2', 'invoice_price' => 'decimal:2', 'sell_price' => 'decimal:2',
            'cost' => 'decimal:2', 'profit' => 'decimal:2', 'monthly_payment' => 'decimal:2',
            'drive_off' => 'decimal:2', 'trade_allowance' => 'decimal:2', 'trade_acv' => 'decimal:2',
            'trade_payoff' => 'decimal:2', 'trade_is_leased' => 'boolean',
            'deal_start_date' => 'datetime', 'deal_expiration_date' => 'datetime',
            'won_at' => 'datetime', 'lost_at' => 'datetime',
        ];
    }

    public const STAGES = ['lead', 'quote', 'application', 'submission', 'pending', 'finalize', 'outstanding', 'complete', 'lost'];

    public const STAGE_TASKS = [
        'lead' => ['Desk Deal', 'Send Quote'],
        'quote' => ['Follow Up For Acceptance'],
        'application' => ['Send Application', 'Receive Application', 'Receive Driver\'s License'],
        'submission' => ['Submit Application'],
        'pending' => ['Get Approval'],
        'finalize' => ['Collect Insurance', 'Transfer Registration', 'Loyalty/Conquest', 'Rebate Documentation'],
        'outstanding' => ['Schedule Delivery', 'Collect COD', 'Collect Bird Dog'],
        'complete' => ['Collect Lease Agreement'],
    ];

    public static function generateDealNumber(): int
    {
        $latest = self::withTrashed()->orderByDesc('deal_number')->first();
        return $latest ? $latest->deal_number + 1 : 100;
    }

    public function getVehicleDisplayAttribute(): string
    {
        if (!$this->vehicle_make) return 'No vehicle yet';
        return trim("{$this->vehicle_year} {$this->vehicle_make} {$this->vehicle_model}" . ($this->vehicle_trim ? " {$this->vehicle_trim}" : ''));
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function salesperson(): BelongsTo { return $this->belongsTo(User::class, 'salesperson_id'); }
    public function lender(): BelongsTo { return $this->belongsTo(Lender::class); }
    public function quotes(): HasMany { return $this->hasMany(DealQuote::class); }
    public function tasks(): HasMany { return $this->hasMany(DealTask::class)->orderBy('sort_order'); }
    public function documents(): HasMany { return $this->hasMany(DealDocument::class); }
    public function dealNotes(): HasMany { return $this->hasMany(DealNote::class)->orderByDesc('created_at'); }

    public function incompleteTasks(): HasMany
    {
        return $this->hasMany(DealTask::class)->where('is_completed', false);
    }

    public function scopeByStage($query, string $stage) { return $query->where('stage', $stage); }
    public function scopeActive($query) { return $query->whereNotIn('stage', ['complete', 'lost']); }
    public function scopeStale($query, int $days = 14)
    {
        return $query->active()->where('updated_at', '<', now()->subDays($days));
    }

    public function generateTasksForStage(?string $stage = null): void
    {
        $stage = $stage ?? $this->stage;
        $tasks = self::STAGE_TASKS[$stage] ?? [];

        foreach ($tasks as $i => $taskName) {
            $this->tasks()->firstOrCreate(
                ['name' => $taskName, 'stage' => $stage],
                ['sort_order' => $i, 'due_date' => now()->addDays(3)]
            );
        }
    }

    public function transitionTo(string $newStage): void
    {
        $this->update(['stage' => $newStage]);
        $this->generateTasksForStage($newStage);

        if ($newStage === 'complete') {
            $this->update(['won_at' => now()]);
        } elseif ($newStage === 'lost') {
            $this->update(['lost_at' => now()]);
        }
    }
}
