<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    use HasFactory;

    protected $table = 'system_settings';

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    /**
     * Retrieve a setting by key with memory/cache acceleration
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::remember("sys_setting_{$key}", 300, function () use ($key) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : null;
        });

        if (is_null($value)) {
            return $default;
        }

        // Try decoding if JSON
        $decoded = json_decode($value, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
    }

    /**
     * Store or update a setting by key
     */
    public static function set(string $key, mixed $value, string $group = 'general'): self
    {
        $serialized = is_array($value) ? json_encode($value) : (string)$value;

        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => $serialized, 'group' => $group]
        );

        Cache::forget("sys_setting_{$key}");

        return $setting;
    }

    /**
     * Get all settings in a specific group as an associative array
     */
    public static function getGroup(string $group): array
    {
        $settings = static::where('group', $group)->get();
        $result = [];

        foreach ($settings as $setting) {
            $decoded = json_decode($setting->value, true);
            $result[$setting->key] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $setting->value;
        }

        return $result;
    }
}
