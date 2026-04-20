<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodyshopSlot extends Model
{
    protected $fillable = [
        'bodyshop_lift_id', 'bodyshop_worker_id', 'claim_id', 'customer_id',
        'vehicle_label', 'vehicle_plate', 'repair_phase', 'status',
        'started_at', 'paused_at', 'completed_at', 'estimated_completion', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at'           => 'datetime',
            'paused_at'            => 'datetime',
            'completed_at'         => 'datetime',
            'estimated_completion' => 'date',
        ];
    }

    public const PHASES = ['disassembly','body','paint','reassembly','detail','ready'];

    public function lift(): BelongsTo     { return $this->belongsTo(BodyshopLift::class, 'bodyshop_lift_id'); }
    public function worker(): BelongsTo   { return $this->belongsTo(BodyshopWorker::class, 'bodyshop_worker_id'); }
    public function claim(): BelongsTo    { return $this->belongsTo(Claim::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
