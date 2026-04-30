<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Insurer extends Model
{
    protected $fillable = [
        'name', 'contact_name', 'first_name', 'last_name',
        'phone', 'email', 'website',
        'claims_phone', 'claims_email', 'address',
        'notes', 'is_active',
    ];

    public static function search(?string $term)
    {
        $q = static::query()->active();
        if ($term) {
            $like = '%' . trim($term) . '%';
            $q->where(function ($w) use ($like) {
                $w->where('name', 'ilike', $like)
                    ->orWhere('first_name', 'ilike', $like)
                    ->orWhere('last_name', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like)
                    ->orWhere('phone', 'ilike', $like);
            });
        }
        return $q;
    }

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function deals(): HasMany { return $this->hasMany(Deal::class); }

    public function scopeActive($query) { return $query->where('is_active', true)->orderBy('name'); }
}
