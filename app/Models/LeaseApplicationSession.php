<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseApplicationSession extends Model
{
    protected $fillable = [
        'phone', 'flow', 'current_step', 'collected', 'approvals',
        'customer_id', 'deal_id',
        'last_inbound_at', 'completed_at', 'aborted_at',
    ];

    protected function casts(): array
    {
        return [
            'collected'       => 'array',
            'approvals'       => 'array',
            'last_inbound_at' => 'datetime',
            'completed_at'    => 'datetime',
            'aborted_at'      => 'datetime',
        ];
    }

    /** Fields that staff must approve before the application can be emailed to a dealer. */
    public const APPROVAL_REQUIRED = ['applicant_annual_income', 'applicant_monthly_housing', 'applicant_years_at_addr'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function deal(): BelongsTo     { return $this->belongsTo(Deal::class); }

    public function isActive(): bool
    {
        return $this->completed_at === null && $this->aborted_at === null;
    }
}
