<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class DisciplineSetting extends Model
{
    protected $table = 'discipline_settings';

    protected $fillable = ['key', 'value', 'label'];

    public static function get(string $key, $default = null)
    {
        $setting = Cache::remember("discipline_setting.{$key}", 60, function () use ($key) {
            return static::where('key', $key)->first();
        });

        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("discipline_setting.{$key}");
    }

    public static function suspensionThreshold(): int
    {
        return (int) static::get('suspension_threshold', 12);
    }

    public static function expiryDays(): int
    {
        return (int) static::get('expiry_days', 365);
    }

    public static function firstSuspensionMonths(): int
    {
        return (int) static::get('first_suspension_months', 3);
    }

    public static function secondSuspensionMonths(): int
    {
        return (int) static::get('second_suspension_months', 6);
    }
}
