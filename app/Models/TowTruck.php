<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TowTruck extends Model
{
    protected $fillable = ['name','license_plate','type','capacity_vehicles','is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function jobs(): HasMany { return $this->hasMany(TowJob::class); }
}
