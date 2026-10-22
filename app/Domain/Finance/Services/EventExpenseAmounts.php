<?php

namespace App\Domain\Finance\Services;

class EventExpenseAmounts
{
    /** Convert validated nonnegative two-decimal inputs without floating point arithmetic. */
    public static function cents(string|int|float $amount): int
    {
        $parts = explode('.', (string) $amount, 2);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public static function lineTotal(string $quantity, string $unitPrice): int
    {
        return intdiv(self::cents($quantity) * self::cents($unitPrice) + 50, 100);
    }

    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').sprintf('%d.%02d', intdiv(abs($cents), 100), abs($cents) % 100);
    }
}
