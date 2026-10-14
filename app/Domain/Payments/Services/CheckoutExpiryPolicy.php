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
        return $order->created_at !== null && $order->created_at->lte($cutoff ?? self::cutoff());
    }
}
