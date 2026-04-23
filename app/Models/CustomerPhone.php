<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPhone extends Model
{
    protected $fillable = ['customer_id', 'phone', 'label', 'is_primary', 'is_sms_capable', 'notes'];
    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'is_sms_capable' => 'boolean'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }

    /** Last-10 digits for matching inbound calls/SMS. */
    public function getLast10Attribute(): string
    {
        $d = preg_replace('/\D/', '', (string) $this->phone);
        return strlen($d) >= 10 ? substr($d, -10) : $d;
    }
}
