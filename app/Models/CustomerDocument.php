<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CustomerDocument extends Model
{
    protected $fillable = [
        'customer_id', 'type', 'label', 'disk', 'path',
        'original_name', 'mime_type', 'size_bytes', 'expires_at', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'date'];
    }

    protected $appends = ['url', 'is_image'];

    public const TYPES = [
        'drivers_license_front' => "Driver's License (front)",
        'drivers_license_back'  => "Driver's License (back)",
        'passport'              => 'Passport',
        'insurance_card'        => 'Insurance Card',
        'registration'          => 'Vehicle Registration',
        'proof_of_residence'    => 'Proof of Residence',
        'paystub'               => 'Paystub',
        'w2'                    => 'W-2 / 1099',
        'utility_bill'          => 'Utility Bill',
        'lease_agreement'       => 'Lease Agreement',
        'other'                 => 'Other',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): ?string
    {
        return $this->path ? Storage::disk($this->disk)->url($this->path) : null;
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
