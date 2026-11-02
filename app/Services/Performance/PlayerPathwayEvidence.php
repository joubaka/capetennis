<?php

namespace App\Services\Performance;

use App\Models\Player;
use Carbon\CarbonImmutable;

/** Read-only personal participation in the same published evidence as the latest saved CTR. */
class PlayerPathwayEvidence
{
    public function fromHistory(array $saved, Player $player, array $points, bool $pending): array
    {
        $empty = ['player_id' => (int) $player->id, 'available' => false, 'score' => null, 'events' => null, 'stage_events' => null, 'unknown_stage_events' => null,
            'date' => null, 'comparable' => false, 'stale' => true, 'stage_ratings' => [],
            'reason' => 'Verified current rating evidence is not available yet.'];
        $latest = $points ? end($points) : null;
        $manifest = $saved['publication_manifest'] ?? null;
        if (!$latest || !is_array($manifest) || !is_array($manifest['events'] ?? null)
            || !is_array($manifest['draws'] ?? null) || !is_array($manifest['fixtures'] ?? null)
            || !is_array($manifest['team_fixtures'] ?? null)) return $empty;
        try {
            if (!app(PlayerAbilitySnapshotStore::class)->published($manifest)) return $empty;
        } catch (\Throwable) { return $empty; }

        $today = CarbonImmutable::today('Africa/Johannesburg');
        $date = $latest['date'] ?? null;
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > $today->toDateString()) return $empty;
        try {
            $asOf = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Africa/Johannesburg');
            if ($asOf->toDateString() !== $date) return $empty;
        } catch (\Throwable) { return $empty; }
        if (!is_int($latest['index'] ?? null) || $latest['index'] < 0 || $latest['index'] > 11) return $empty;
        $bit = 1 << $latest['index'];
        $cutoff = $asOf->subDays(AllAgeRatingModel::WINDOW_DAYS)->toDateString();
        $publicEvents = [];
        foreach ($manifest['events'] as $event) {
            if ((int) ($event['id'] ?? 0) > 0 && (int) ($event['published'] ?? 0) === 1) $publicEvents[(int) $event['id']] = true;
        }
        $draws = [];
        foreach ($manifest['draws'] ?? [] as $draw) {
            if ((int) ($draw['published'] ?? 0) === 1 && isset($publicEvents[(int) ($draw['event_id'] ?? 0)])) {
                $draws[(int) $draw['id']] = (int) $draw['event_id'];
            }
        }
        $sources = [];
        foreach (['fixtures' => 'match:', 'team_fixtures' => 'team-match:'] as $table => $prefix) {
            foreach ($manifest[$table] ?? [] as $fixture) {
                if (isset($draws[(int) ($fixture['draw_id'] ?? 0)])) $sources[$prefix.$fixture['id']] = $draws[(int) $fixture['draw_id']];
            }
        }
        $events = []; $stageEvents = ['red' => [], 'orange' => [], 'standard' => []]; $unknownStage = [];
        foreach ($saved['evidence'] ?? [] as $row) {
            $event = (int) ($row['event_id'] ?? 0);
            $source = $row['source_id'] ?? '';
            $players = $row['players'] ?? [];
            if (($row['kind'] ?? null) !== 'match' || ($row['cohort'] ?? null) !== AllAgeRatingModel::COHORT
                || !is_int($row['months'] ?? null) || !($row['months'] & $bit)
                || !is_array($players) || count($players) !== 2 || count(array_unique($players)) !== 2
                || count(array_filter($players, fn ($id) => is_int($id) && $id > 0)) !== 2
                || !in_array((int) $player->id, $players, true) || !in_array($row['winner_side'] ?? null, [1, 2], true)
                || !is_string($source) || !isset($sources[$source]) || $sources[$source] !== $event
                || !is_string($row['score'] ?? null) || !preg_match('/\d+\s*-\s*\d+/', $row['score'])
                || preg_match('/walkover|\bbye\b|\bw\s*\/?\s*o\b/i', $row['score'])
                || !is_string($row['date'] ?? null) || $row['date'] < $cutoff || $row['date'] > $date) continue;
            $events[$event] = true;
            $stage = BallRatingModel::forMatch($row);
            if (is_string($stage) && array_key_exists($stage, $stageEvents)) $stageEvents[$stage][$event] = true;
            else $unknownStage[$event] = true;
        }
        $score = $latest['score'] ?? null;
        $score = is_numeric($score) && is_finite((float) $score) && $score >= 0 && $score <= 100 ? (float) $score : null;
        $stale = $pending || ($saved['as_of'] ?? '') !== $today->toDateString() || $date !== ($saved['as_of'] ?? null);
        // Yesterday's unchanged saved build remains useful across the nightly refresh boundary.
        // A changed source, mismatched month or older build must still await recalculation.
        $assessmentAvailable = !$pending && $date === ($saved['as_of'] ?? null)
            && $date >= $today->subDay()->toDateString();
        $comparable = ($latest['connection_status'] ?? null) === 'recent' && empty($latest['historical_connection']);
        $stageRatings = [];
        $latestMonth = $saved['months'][$latest['index']] ?? [];
        foreach (BallRatingModel::COHORTS as $stage => $cohort) {
            $rating = $latestMonth['cohorts'][$cohort][$player->id] ?? null;
            $stageScore = $rating['score'] ?? null;
            $valid = is_array($rating) && is_numeric($stageScore) && is_finite((float) $stageScore) && $stageScore >= 0 && $stageScore <= 100;
            $stageRatings[$stage] = ['score' => $valid ? (float) $stageScore : null,
                'comparable' => $valid && ($rating['connection_status'] ?? null) === 'recent' && empty($rating['historical_connection']),
                'local' => $valid && ($rating['connection_status'] ?? null) === 'disconnected',
                'confidence_label' => $rating['confidence_label'] ?? null, 'date' => $date, 'stale' => $stale,
                'reason' => $latestMonth['ball_status'][$stage]['reason'] ?? null];
        }

        return ['player_id' => (int) $player->id, 'available' => true, 'score' => $score, 'events' => count($events),
            'stage_events' => array_map('count', $stageEvents), 'unknown_stage_events' => count($unknownStage),
            'stage_ratings' => $stageRatings,
            'date' => $date, 'comparable' => $comparable, 'stale' => $stale, 'assessment_available' => $assessmentAvailable, 'reason' => $stale
                ? 'Progress awaits a current saved rating update.'
                : (!$comparable ? 'Progress needs a current shared comparison reference.' : ($score === null ? 'No valid CTR is available.' : null))];
    }
}
