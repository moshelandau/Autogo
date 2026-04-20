<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Insurer extends Model
{
    protected $fillable = [
        'name', 'contact_name', 'phone', 'email', 'website',
        'claims_phone', 'claims_email', 'notes', 'is_active',
    ];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function deals(): HasMany { return $this->hasMany(Deal::class); }

    public function scopeActive($query) { return $query->where('is_active', true)->orderBy('name'); }
}
