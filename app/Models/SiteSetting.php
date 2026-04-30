<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    protected $table = 'site_settings';

    protected $fillable = ['key', 'value', 'label', 'group'];

    public const GROUP_GENERAL      = 'general';
    public const GROUP_PAYFAST      = 'payfast';
    public const GROUP_EMAIL        = 'email';
    public const GROUP_REGISTRATION = 'registration';

    /**
     * PayFast payment method mapping.
     * Keys = what PayFast sends in ITN, Values = our setting key suffix.
     */
    public const PAYMENT_METHODS = [
        'cc'  => 'credit_card',
        'dc'  => 'debit_card',
        'eft' => 'eft',
        'ap'  => 'apple_pay',
        'sp'  => 'samsung_pay',
        'zp'  => 'zapper',
    ];

    public const PAYMENT_METHOD_LABELS = [
        'credit_card' => 'Credit Card',
        'debit_card'  => 'Debit Card',
        'eft'         => 'EFT',
        'apple_pay'   => 'Apple Pay',
        'samsung_pay' => 'Samsung Pay',
        'zapper'      => 'Zapper',
    ];

    /**
     * Get a setting value by key, with an optional default.
     */
    public static function get(string $key, $default = null)
    {
        $setting = Cache::remember("site_setting.{$key}", 60, function () use ($key) {
            return static::where('key', $key)->first();
        });

        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value by key, optionally specifying the group.
     * When the record already exists its group is preserved; when it is
     * created for the first time the supplied group is stored.
     */
    public static function set(string $key, $value, ?string $group = null): void
    {
        $data = ['value' => $value];
        if ($group !== null) {
            $data['group'] = $group;
        }

        static::updateOrCreate(['key' => $key], $data);

        Cache::forget("site_setting.{$key}");
    }

    /**
     * Resolve the fee percentage for a given payment method.
     *
     * @param string|null $paymentMethod  PayFast code (cc, dc, eft, ap, sp, zp) or our key (credit_card, etc.)
     */
    public static function getPayfastFeePercentage(?string $paymentMethod = null): float
    {
        if ($paymentMethod) {
            // Normalise: if a PayFast code was passed, map it to our key
            $methodKey = self::PAYMENT_METHODS[$paymentMethod] ?? $paymentMethod;

            $perMethod = static::get("payfast_fee_pct_{$methodKey}");
            if ($perMethod !== null) {
                return (float) $perMethod;
            }
        }

        // Fall back to the default percentage
        return (float) static::get('payfast_fee_percentage', 3.2);
    }

    /**
     * Calculate the PayFast fee for a given amount using stored settings.
     *
     * Formula: ((amount × percentage / 100) + flat_fee) × (1 + vat / 100)
     *
     * @param float       $amount
     * @param string|null $paymentMethod  PayFast code (cc, dc, eft …) or our key (credit_card …)
     */
    public static function calculatePayfastFee(float $amount, ?string $paymentMethod = null): float
    {
        $percentage = static::getPayfastFeePercentage($paymentMethod);
        $flatFee    = (float) static::get('payfast_fee_flat', 2.00);
        $vatRate    = (float) static::get('payfast_vat_rate', 14);

        return round((($amount * $percentage / 100) + $flatFee) * (1 + $vatRate / 100), 2);
    }
}
