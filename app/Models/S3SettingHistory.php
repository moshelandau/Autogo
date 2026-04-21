<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class S3SettingHistory extends Model
{
    protected $table = 's3_settings_history';

    protected $fillable = [
        'bucket', 'region', 'access_key', 'secret_key',
        'test_passed', 'test_message', 'is_active', 'saved_by',
    ];

    protected function casts(): array
    {
        return [
            'test_passed' => 'boolean',
            'is_active'   => 'boolean',
            'secret_key'  => 'encrypted',
        ];
    }
}
