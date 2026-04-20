<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BodyshopLift extends Model
{
    protected $fillable = ['name','type','position','color','is_active','notes'];
    protected function casts(): array { return ['is_active' => 'boolean', 'position' => 'integer']; }
    public const TYPES = ['lift','bay','spray_booth','frame_machine','detail_bay'];

    public function slots(): HasMany { return $this->hasMany(BodyshopSlot::class); }
    public function activeSlot(): HasOne {
        return $this->hasOne(BodyshopSlot::class)
            ->whereIn('status', ['in_progress','paused','scheduled'])
            ->latest('started_at');
    }
}
