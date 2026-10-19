<?php

namespace App\Domain\Payments\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CheckoutExpiryPolicy
{
    public const MINUTES = 5;

    public static function cutoff(): Carbon
    {
        return now()->subMinutes(self::MINUTES);
    }

    public static function isExpired(Model $order, ?Carbon $cutoff = null): bool
    {
        if ($order instanceof \App\Models\RegistrationOrder && !$order->payfast_handed_off_at
            && \Illuminate\Support\Facades\Schema::hasTable('registration_payment_recoveries')) {
            $recovery = \App\Models\RegistrationPaymentRecovery::where('registration_order_id', $order->id)->first();
            if ($recovery && app(RegistrationPaymentRecoveryService::class)->hasValidCheckoutClock($recovery, $order)) {
                return $recovery->checkout_started_at
                    ? $recovery->checkout_started_at->lte($cutoff ?? self::cutoff())
                    : !$recovery->link_expires_at->isFuture();
            }
        }
        $startedAt = $order->payfast_handed_off_at ?? $order->created_at;
        return $startedAt !== null && $startedAt->lte($cutoff ?? self::cutoff());
    }
}
