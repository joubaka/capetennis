<?php

namespace SportStack\Assistant;

/** Chronological event holdout for the duration baseline; no participant fields. */
final class DurationEvaluation
{
    public function evaluate(array $observations, int $fallback = 60): array
    {
        $valid = array_values(array_filter($observations, fn ($row) => is_array($row)
            && ($row['verified'] ?? false) === true && ! ($row['interrupted'] ?? false)
            && ! ($row['walkover'] ?? false) && ! ($row['retired'] ?? false)
            && isset($row['event_id'], $row['event_date'], $row['format'], $row['age_group'], $row['actual_minutes'])
            && is_numeric($row['actual_minutes']) && $row['actual_minutes'] >= 5 && $row['actual_minutes'] <= 360));
        $dates = [];
        foreach ($valid as $row) {
            $id = (string) $row['event_id'];
            // An event is a single holdout unit, even across several playing days.
            $dates[$id] = min($dates[$id] ?? $row['event_date'], $row['event_date']);
        }
        asort($dates);
        if (count($dates) < 3) {
            return ['status' => 'insufficient_events', 'events' => count($dates), 'evaluated_matches' => 0];
        }
        $events = array_keys($dates);
        $split = max(1, (int) floor(count($events) * .8));
        $trainIds = array_slice($events, 0, $split);
        $testIds = array_slice($events, $split);
        $holdoutStart = $dates[$testIds[0]];
        $training = array_values(array_filter($valid, fn ($row) => in_array((string) $row['event_id'], array_map('strval', $trainIds), true)
            && $row['event_date'] < $holdoutStart));
        $test = array_values(array_filter($valid, fn ($row) => in_array((string) $row['event_id'], array_map('strval', $testIds), true)));
        $error = $defaultError = $under = $learned = 0;
        foreach ($test as $row) {
            $prediction = (new DurationEstimator)->estimate($training, $row['format'], $row['age_group'], $fallback);
            $error += abs($prediction['minutes'] - $row['actual_minutes']);
            $defaultError += abs($fallback - $row['actual_minutes']);
            $under += $prediction['minutes'] < $row['actual_minutes'] ? 1 : 0;
            $learned += $prediction['source'] === 'verified_observations' ? 1 : 0;
        }
        $count = count($test);

        return ['status' => 'evaluated', 'training_events' => count($trainIds), 'held_out_events' => count($testIds),
            'evaluated_matches' => $count, 'learned_predictions' => $learned,
            'mae_minutes' => round($error / max(1, $count), 2), 'default_mae_minutes' => round($defaultError / max(1, $count), 2),
            'underbooking_rate' => round($under / max(1, $count), 4), 'automatically_enabled' => false];
    }
}
