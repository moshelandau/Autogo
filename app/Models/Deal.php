<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
        'insurer_id', 'dealer_id',
        'notes', 'deal_start_date', 'deal_expiration_date',
        'won_at', 'lost_at', 'lost_reason',
        // Workflow structured fields (see migration ..._add_preferences_to_deals_table)
        'preferences', 'co_signer_customer_id',
        'insurance_status', 'plate_transfer',
        'delivery_scheduled_at',
        'down_collected_at_delivery', 'paperwork_tracking_number',
        'bd_payment_received_at', 'bd_payment_amount',
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
            'preferences' => 'array',
            'plate_transfer' => 'boolean',
            'delivery_scheduled_at' => 'datetime',
            'down_collected_at_delivery' => 'decimal:2',
            'bd_payment_received_at' => 'date',
            'bd_payment_amount' => 'decimal:2',
        ];
    }

    public function coSigner(): BelongsTo { return $this->belongsTo(Customer::class, 'co_signer_customer_id'); }

    public const STAGES = ['lead', 'quote', 'application', 'submission', 'pending', 'finalize', 'outstanding', 'complete', 'lost'];

    /**
     * Tasks auto-created when a deal enters each stage. Names prefixed
     * "Optional:" are visible but skippable — staff can mark them
     * complete or leave them open without blocking stage advancement.
     *
     * Lead-stage capture fields (style/budget/miles/passengers/color/brand)
     * live on the deal form, not as tasks — tracked here as a single
     * "Capture preferences" task that staff check off after filling them in.
     */
    public const STAGE_TASKS = [
        'lead' => [
            'Find vehicle match & send quote',
        ],
        'quote' => [
            'Follow up for acceptance',
        ],
        'application' => [
            'Receive full application + driver\'s license (front + back)',
            'Co-signer license front + back (if applicable)',
            'Optional: Run soft credit pull',
        ],
        'submission' => [
            'Send application to dealer',
        ],
        'pending' => [
            'Work on insurance',
            'Optional: Collect conquest and rebate documentation',
            'Follow up with dealer — insurance / rebates / registration / plate transfer',
            'Receive approval',
        ],
        'finalize' => [
            'Schedule delivery & send pickup details to customer (confirm car is ready)',
        ],
        'outstanding' => [
            'Collect down payment at delivery',
            'Get paperwork signed same day',
            'Express signed paperwork to dealer',
            'Enter tracking number and send to dealer',
        ],
        'complete' => [
            'Collect Bird Dog payment from dealer (~1 month after delivery)',
        ],
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
    public function insurer(): BelongsTo { return $this->belongsTo(Insurer::class); }
    public function quotes(): HasMany { return $this->hasMany(DealQuote::class); }
    public function tasks(): HasMany { return $this->hasMany(DealTask::class)->orderBy('sort_order'); }
    public function documents(): HasMany { return $this->hasMany(DealDocument::class); }
    /**
     * Polymorphic note thread (mentions/assignees/replies/reminders).
     * Named `noteThread` rather than `notes` to avoid colliding with the
     * existing `deals.notes` text column — both would serialize to the
     * same JSON key and the relation would silently overwrite the column.
     */
    public function noteThread(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->orderByDesc('created_at');
    }

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
                ['sort_order' => $i, 'due_date' => now()->addDays(self::defaultDueDaysFor($taskName))]
            );
        }
    }

    /**
     * Default number of days from now for a freshly-generated task.
     * The Bird Dog payment is collected ~1 month after delivery, so it
     * gets a 30-day default rather than the usual 3.
     */
    public static function defaultDueDaysFor(string $taskName): int
    {
        if (stripos($taskName, 'bird dog') !== false) return 30;
        return 3;
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
