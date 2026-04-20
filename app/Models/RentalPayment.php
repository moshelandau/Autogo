<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalPayment extends Model
{
    protected $fillable = [
        'reservation_id', 'customer_id', 'payment_method', 'amount',
        'reference', 'status', 'type', 'sola_transaction_data', 'processed_by', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'sola_transaction_data' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function processedByUser(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }
}
