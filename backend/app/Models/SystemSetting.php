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
     * In-memory request cache for 0ms repeated lookups
     */
    protected static ?array $runtimeCache = null;

    /**
     * Retrieve a setting by key with 0ms memory cache acceleration
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (static::$runtimeCache === null) {
            try {
                static::$runtimeCache = Cache::remember('vmarket_all_system_settings_dict', 3600, function () {
                    return static::pluck('value', 'key')->toArray();
                }) ?? [];
            } catch (\Throwable $e) {
                static::$runtimeCache = [];
            }
        }

        if (array_key_exists($key, static::$runtimeCache)) {
            $value = static::$runtimeCache[$key];
            if ($value === null) {
                return $default;
            }
            $decoded = json_decode($value, true);
            return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
        }

        return $default;
    }

    /**
     * Read a safety-critical setting directly from the database, bypassing the
     * long-lived dictionary cache. Payment-method activation and receiving-account
     * snapshots must observe the committed single-row configuration promptly.
     */
    public static function getFresh(string $key, mixed $default = null): mixed
    {
        $value = static::query()->where('key', $key)->value('value');
        if ($value === null) {
            return $default;
        }

        $decoded = json_decode((string) $value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
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

        static::$runtimeCache = null;
        Cache::forget('vmarket_all_system_settings_dict');

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