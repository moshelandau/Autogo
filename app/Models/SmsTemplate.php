<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    protected $fillable = ['label', 'body', 'category', 'is_active', 'created_by'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
}
