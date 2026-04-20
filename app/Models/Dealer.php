<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dealer extends Model
{
    protected $fillable = [
        'name', 'contact_name', 'phone', 'email', 'website',
        'address', 'city', 'state', 'zip', 'makes_carried', 'notes', 'is_active',
    ];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function deals(): HasMany { return $this->hasMany(Deal::class); }

    public function scopeActive($query) { return $query->where('is_active', true)->orderBy('name'); }
}
