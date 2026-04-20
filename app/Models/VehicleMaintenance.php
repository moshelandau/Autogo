<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMaintenance extends Model
{
    protected $fillable = [
        'vehicle_id', 'type', 'description', 'scheduled_date', 'completed_date',
        'odometer_at_service', 'cost', 'vendor', 'status', 'notes', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'completed_date' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
