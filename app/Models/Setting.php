<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::$cache ??= self::query()->pluck('value', 'key')->all();

        return array_key_exists($key, self::$cache) && self::$cache[$key] !== null
            ? self::$cache[$key]
            : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        self::flushCache();
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }

    /** Lokasi outlet [lat, lng] atau null bila belum diatur. */
    public static function outletLocation(): ?array
    {
        $lat = self::get('outlet_lat');
        $lng = self::get('outlet_lng');

        return ($lat !== null && $lng !== null && $lat !== '' && $lng !== '')
            ? [(float) $lat, (float) $lng]
            : null;
    }
}
