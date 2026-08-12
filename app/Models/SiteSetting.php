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

    public const AUTOMATED_EMAIL_TOGGLES = [
        'player_email_on_registration',
        'player_email_on_admin_entry',
        'player_email_on_withdrawal',
        'player_email_on_move',
        'player_email_on_reinstatement',
        'player_email_on_team_registration',
        'player_email_on_team_withdrawal',
        'player_email_on_team_refund_request',
        'player_email_on_team_refund_completed',
        'player_email_on_wallet_refund',
        'player_email_on_payfast_refund',
        'player_email_on_bank_details_request',
        'player_email_on_bank_refund_reminder',
        'player_email_on_bank_refund_completed',
        'admin_email_on_daily_withdrawal_summary',
    ];

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

    public static function emailEnabled(string $key): bool
    {
        return static::get($key, '1') === '1';
    }

    /**
     * Calculate the withdrawal/refund fee.
     * Fixed at 10% of the gross amount, regardless of payment method or PayFast fee.
     *
     * @param float $gross  The gross amount paid
     */
    public static function calculateWithdrawalFee(float $gross): float
    {
        return round($gross * 0.10, 2);
    }
}
