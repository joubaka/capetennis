<?php

namespace App\Support;

final class StandingsPoints
{
    public static function format(int|float $points): string
    {
        return rtrim(rtrim(number_format($points, 4, '.', ''), '0'), '.') ?: '0';
    }
}
