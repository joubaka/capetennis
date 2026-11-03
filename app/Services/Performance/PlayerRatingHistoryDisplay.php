<?php

namespace App\Services\Performance;

class PlayerRatingHistoryDisplay
{
    /** A small private display projection; no evidence lookup or rating calculation. */
    public static function snapshot(array $history): array
    {
        $stage = in_array($history['ball_stage'] ?? null, ['red', 'orange', 'standard'], true) ? $history['ball_stage'] : null;
        $result = ['points' => [], 'stage' => $stage, 'label' => $stage ? BallRatingModel::LABELS[$stage] : 'Overall CTR',
            'reason' => $history['reason'] ?? null, 'as_of' => $history['as_of'] ?? null, 'stale' => (bool) ($history['stale'] ?? false)];
        if ($result['reason']) return $result;
        $points = []; $duplicates = [];
        foreach (array_slice(is_array($history['points'] ?? null) ? $history['points'] : [], -12) as $point) {
            if (!is_array($point)) continue;
            $index = filter_var($point['index'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 11]]);
            $date = $point['date'] ?? null; $score = $point['score'] ?? null;
            if ($index === false || !is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))
                || ($result['as_of'] && $date > $result['as_of']) || !is_numeric($score) || !is_finite((float) $score) || $score < 0 || $score > 100) continue;
            $row = ['index' => $index, 'date' => $date, 'score' => (float) $score,
                'group' => is_string($point['group'] ?? null) ? substr($point['group'], 0, 128) : null,
                'connection_status' => in_array($point['connection_status'] ?? null, ['recent', 'historical', 'disconnected'], true) ? $point['connection_status'] : null,
                'confidence_label' => in_array($point['confidence_label'] ?? null, ['Low', 'Medium', 'High'], true) ? $point['confidence_label'] : 'Not recorded',
                'historical_connection' => (bool) ($point['historical_connection'] ?? false),
                'connection_reference_date' => is_string($point['connection_reference_date'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $point['connection_reference_date']) ? $point['connection_reference_date'] : null,
                'evidence_basis' => ($point['evidence_basis'] ?? null) === 'finish_only' ? 'finish_only' : null];
            if (isset($points[$index]) && $points[$index] !== $row) $duplicates[$index] = true;
            $points[$index] = $row;
        }
        $points = array_diff_key($points, $duplicates); ksort($points);
        $result['points'] = array_values($points);
        return $result;
    }

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
