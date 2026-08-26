<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    protected $fillable = [
        'key',
        'values',
        'secret_values',
    ];

    protected function casts(): array
    {
        return [
            'values' => 'array',
            'secret_values' => 'encrypted:array',
        ];
    }

    /** @return array<string, mixed> */
    public static function valuesFor(string $key): array
    {
        return Cache::remember(
            self::cacheKey($key, 'values'),
            now()->addHour(),
            fn (): array => static::query()->where('key', $key)->first()?->values ?? [],
        );
    }

    /** @return array<string, mixed> */
    public static function secretsFor(string $key): array
    {
        return Cache::remember(
            self::cacheKey($key, 'secrets'),
            now()->addHour(),
            fn (): array => static::query()->where('key', $key)->first()?->secret_values ?? [],
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $secretUpdates
     */
    public static function put(string $key, array $values, array $secretUpdates = []): self
    {
        $setting = static::query()->firstOrNew(['key' => $key]);
        $setting->values = $values;
        $setting->secret_values = [
            ...($setting->secret_values ?? []),
            ...$secretUpdates,
        ];
        $setting->save();

        static::forget($key);

        return $setting;
    }

    public static function forget(string $key): void
    {
        Cache::forget(self::cacheKey($key, 'values'));
        Cache::forget(self::cacheKey($key, 'secrets'));
    }

    private static function cacheKey(string $key, string $type): string
    {
        return "site-setting:{$key}:{$type}";
    }
}
