<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionTypePage extends Model
{
    protected $fillable = [
        'permission_type_id', 'page_key',
        'can_view', 'can_create', 'can_edit', 'can_delete',
    ];

    protected function casts(): array
    {
        return [
            'can_view' => 'boolean',
            'can_create' => 'boolean',
            'can_edit' => 'boolean',
            'can_delete' => 'boolean',
        ];
    }

    public function permissionType(): BelongsTo
    {
        return $this->belongsTo(PermissionType::class);
    }
}
