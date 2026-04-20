<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TowDriver extends Model
{
    protected $fillable = ['name','phone','cdl_number','is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function jobs(): HasMany { return $this->hasMany(TowJob::class); }
}
