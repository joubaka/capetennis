<?php

namespace App\Services\Performance;

/** Presentation only; saved evidence indices and calculation policy are unchanged. */
class AbilityConfidenceDisplay
{
    public static function label(int $index): string
    {
        return $index < 40 ? 'Low' : ($index < 70 ? 'Medium' : 'High');
    }
}
