<?php

namespace SportStack\Assistant;

/** Small empirical quantile baseline, using verified observations only. */
final class DurationEstimator
{
    public function estimate(array $observations, string $format, string $ageGroup, int $fallback = 60): array
    {
        $samples = [];
        foreach ($observations as $row) {
            if (($row['verified'] ?? false) !== true || ($row['interrupted'] ?? false)
                || ($row['walkover'] ?? false) || ($row['retired'] ?? false)
                || ($row['format'] ?? null) !== $format || ($row['age_group'] ?? null) !== $ageGroup) {
                continue;
            }
            $minutes = $row['actual_minutes'] ?? null;
            if (is_numeric($minutes) && $minutes >= 5 && $minutes <= 360) {
                $samples[] = (float) $minutes;
            }
        }
        sort($samples, SORT_NUMERIC);
        $count = count($samples);
        // A minimum is a pilot guard, not evidence of generalization.
        if ($count < 20) {
            return ['minutes' => $fallback, 'samples' => $count, 'source' => 'configured_default', 'quantile' => null];
        }

        return ['minutes' => (int) (ceil($samples[(int) ceil($count * .8) - 1] / 5) * 5),
            'samples' => $count, 'source' => 'verified_observations', 'quantile' => .8];
    }
}
