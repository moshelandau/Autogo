<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;

class SettingService
{
    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::getValue($key, $default);
    }

    public function set(string $key, mixed $value, string $group = 'general'): void
    {
        Setting::setValue($key, $value, $group);
    }

    public function getByGroup(string $group): Collection
    {
        return Setting::where('group', $group)->pluck('value', 'key');
    }

    public function getAll(): Collection
    {
        return Setting::all()->pluck('value', 'key');
    }
}
