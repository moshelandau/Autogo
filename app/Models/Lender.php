<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lender extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'contact_name', 'phone', 'email', 'website', 'programs_notes', 'is_active', 'sort_order'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function deals(): HasMany { return $this->hasMany(Deal::class); }
    public function quotes(): HasMany { return $this->hasMany(DealQuote::class); }

    public function scopeActive($query) { return $query->where('is_active', true)->orderBy('name'); }
}
