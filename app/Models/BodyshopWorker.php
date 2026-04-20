<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BodyshopWorker extends Model
{
    protected $fillable = ['name','phone','email','role','color','hourly_rate','hire_date','is_active'];
    protected function casts(): array { return ['hire_date' => 'date', 'is_active' => 'boolean', 'hourly_rate' => 'decimal:2']; }
    public const ROLES = ['tech','painter','detailer','estimator','manager','helper'];
    public function slots(): HasMany { return $this->hasMany(BodyshopSlot::class); }
    public function activeSlot() { return $this->hasOne(BodyshopSlot::class)->whereIn('status', ['in_progress','paused']); }
}
