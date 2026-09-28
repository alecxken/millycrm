<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** Tiny key/value store for agency-wide settings (e.g. the theme). */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting.$key", function () use ($key, $default) {
            if (! Schema::hasTable('settings')) {
                return $default;
            }

            return static::find($key)?->value ?? $default;
        });
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting.$key");
    }
}
