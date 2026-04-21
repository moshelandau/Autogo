<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EzPassTransaction extends Model
{
    protected $fillable = [
        'ez_pass_account_id', 'vehicle_id', 'reservation_id', 'customer_id',
        'tag_number', 'plate', 'plate_state',
        'posted_at', 'agency', 'plaza', 'lane',
        'amount', 'type', 'source_file', 'external_ref', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'amount'    => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo     { return $this->belongsTo(Vehicle::class); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function customer(): BelongsTo    { return $this->belongsTo(Customer::class); }
    public function account(): BelongsTo     { return $this->belongsTo(EzPassAccount::class, 'ez_pass_account_id'); }
}
