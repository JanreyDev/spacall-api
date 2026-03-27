<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public static function get(string $key, $default = null)
    {
        return Cache::remember("app_setting.{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            if (!$setting) {
                return $default;
            }

            return self::castValue($setting->value, $setting->type);
        });
    }

    public static function set(string $key, $value, string $type = 'string', ?string $description = null): void
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? $value : [$value],
                'type' => $type,
                'description' => $description,
            ]
        );

        Cache::forget("app_setting.{$key}");
    }

    protected static function castValue($value, string $type)
    {
        if (is_array($value) && count($value) === 1 && !in_array($type, ['json', 'array'])) {
            $value = $value[0];
        }

        return match ($type) {
            'number' => is_numeric($value) ? (float) $value : $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => $value,
            default => (string) $value,
        };
    }

    public static function getPublicSettings(): array
    {
        return Cache::remember('app_settings.public', 3600, function () {
            return self::where('is_public', true)
                ->get()
                ->mapWithKeys(function ($setting) {
                    return [$setting->key => self::castValue($setting->value, $setting->type)];
                })
                ->toArray();
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('app_settings.public');

        self::all()->each(function ($setting) {
            Cache::forget("app_setting.{$setting->key}");
        });
    }

    protected static function booted(): void
    {
        static::saved(function ($setting) {
            Cache::forget("app_setting.{$setting->key}");
            Cache::forget('app_settings.public');
        });

        static::deleted(function ($setting) {
            Cache::forget("app_setting.{$setting->key}");
            Cache::forget('app_settings.public');
        });
    }
}
