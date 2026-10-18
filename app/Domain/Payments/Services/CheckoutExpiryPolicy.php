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
        $startedAt = $order->payfast_handed_off_at ?? $order->created_at;
        return $startedAt !== null && $startedAt->lte($cutoff ?? self::cutoff());
    }
}
