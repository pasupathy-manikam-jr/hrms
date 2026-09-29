<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Company-wide key/value settings, read through a forever cache that every write clears.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * Keys and their values before anything is saved (mirrors the WorkDo demo defaults).
     */
    public const DEFAULTS = [
        // System
        'defaultLanguage' => 'en',
        'dateFormat' => 'd/m/Y',
        'timeFormat' => 'h:i A',
        'calendarStartDay' => 'monday',
        'defaultTimezone' => 'Asia/Kuala_Lumpur',
        'landingPageEnabled' => true,
        'ipRestrictionEnabled' => false,
        // Brand
        'titleText' => 'HRM',
        'footerText' => '© 2026 HRM Sdn Bhd',
        'themeColor' => 'indigo',
        'customColor' => '#3b82f6',
        // Currency
        'defaultCurrency' => 'MYR',
        'decimalFormat' => 2,
        'decimalSeparator' => '.',
        'thousandsSeparator' => ',',
        'currencySymbolPosition' => 'before',
        'currencySymbolSpace' => true,
        // Email
        'mailDriver' => 'smtp',
        'mailHost' => '',
        'mailPort' => 587,
        'mailUsername' => '',
        'mailPassword' => '',
        'mailEncryption' => 'tls',
        'mailFromAddress' => '',
        'mailFromName' => 'HRM',
        // Working days (0 = Sunday … 6 = Saturday)
        'workingDays' => [1, 2, 3, 4, 5],
    ];

    /** Preset brand colours; "custom" uses customColor. */
    public const THEME_COLORS = [
        'indigo' => '#4f46e5',
        'blue' => '#3b82f6',
        'green' => '#10b981',
        'purple' => '#8b5cf6',
        'orange' => '#f97316',
        'red' => '#ef4444',
    ];

    /** Stored encrypted; never sent to the browser. */
    public const SECRETS = ['mailPassword'];

    private const CACHE_KEY = 'settings';

    /**
     * Every setting, saved values over defaults.
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        $saved = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        $values = self::DEFAULTS;

        foreach ($saved as $key => $json) {
            if (array_key_exists($key, self::DEFAULTS)) {
                $value = json_decode($json, true);
                $values[$key] = in_array($key, self::SECRETS, true) && $value !== '' ? Crypt::decryptString($value) : $value;
            }
        }

        return $values;
    }

    public static function get(string $key): mixed
    {
        return static::values()[$key];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            // Keep each value the same type as its default (form input arrives as strings).
            $value = match (gettype(self::DEFAULTS[$key])) {
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'integer' => (int) $value,
                default => $value,
            };

            if (in_array($key, self::SECRETS, true) && $value !== '') {
                $value = Crypt::encryptString($value);
            }

            static::query()->updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public static function primaryColor(): string
    {
        $values = static::values();

        return self::THEME_COLORS[$values['themeColor']] ?? $values['customColor'];
    }

    /**
     * Settings safe to share with every page (no secrets).
     *
     * @return array<string, mixed>
     */
    public static function public(): array
    {
        return array_diff_key(static::values(), array_flip(self::SECRETS));
    }
}
