<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type', 'trigger_event', 'body', 'voice_script', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function logs(): HasMany { return $this->hasMany(NotificationLog::class); }

    public function renderBody(array $variables): string
    {
        $body = $this->body;
        foreach ($variables as $key => $value) {
            $body = str_replace("{{$key}}", (string) $value, $body);
        }
        return $body;
    }
}
