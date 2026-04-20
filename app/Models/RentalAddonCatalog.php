<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalAddonCatalog extends Model
{
    protected $table = 'rental_addons_catalog';

    protected $fillable = ['name', 'category', 'charge_type', 'rate', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
