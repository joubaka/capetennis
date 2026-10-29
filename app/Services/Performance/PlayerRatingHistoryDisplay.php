<?php

namespace App\Services\Performance;

class PlayerRatingHistoryDisplay
{
    public static function axis(array $points, string $scale): array
    {
        $min = 0; $max = 100; $step = 25;
        if ($scale !== 'full' && $points) {
            $scores = array_column($points, 'score');
            $min = max(0, (int) (floor(min($scores) / 5) - 1) * 5);
            $max = min(100, (int) (ceil(max($scores) / 5) + 1) * 5);
            while ($max - $min < 20) {
                if ($min > 0) $min = max(0, $min - 5);
                if ($max - $min < 20 && $max < 100) $max = min(100, $max + 5);
            }
            $step = $max - $min > 50 ? 10 : 5;
            $min = (int) floor($min / $step) * $step;
            $max = (int) ceil($max / $step) * $step;
        }
        return ['min' => $min, 'max' => $max, 'step' => $step, 'ticks' => range($min, $max, $step)];
    }
}
