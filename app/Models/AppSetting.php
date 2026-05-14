<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value', 'label', 'group'];

    /**
     * Get a setting value by key, with optional default.
     * Results cached for 10 minutes.
     */
    public static function get(string $key, $default = null): ?string
    {
        $settings = Cache::remember('app_settings_all', 600, function () {
            return self::all()->pluck('value', 'key')->toArray();
        });

        return $settings[$key] ?? $default;
    }

    /**
     * Set/update a setting value and bust the cache.
     */
    public static function set(string $key, string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('app_settings_all');
    }

    /**
     * Get all settings for a group.
     */
    public static function getGroup(string $group): array
    {
        $settings = Cache::remember('app_settings_all', 600, function () {
            return self::all()->pluck('value', 'key')->toArray();
        });

        return $settings;
    }
}
