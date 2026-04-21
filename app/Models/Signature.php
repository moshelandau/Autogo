<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Signature extends Model
{
    protected $fillable = [
        'signable_type', 'signable_id', 'customer_id',
        'signer_name', 'signature_data_url',
        'ip_address', 'user_agent', 'device_info',
        'geo_lat', 'geo_lng', 'sha256', 'signed_at',
    ];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime', 'geo_lat' => 'decimal:7', 'geo_lng' => 'decimal:7'];
    }

    public function signable(): MorphTo  { return $this->morphTo(); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \RuntimeException('Signatures cannot be deleted — evidence record.'));
    }
}
