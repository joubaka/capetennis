<?php

namespace App\Services\Performance;

use App\Models\Event;
use Carbon\CarbonImmutable;

/** Original played outcomes only. No saved rating, inferred finish or account identity input. */
class CohortTransitionEvidenceService
{
    public const COHORTS = ['u11 boys', 'u12 boys', 'u12 girls'];
    public const WINDOW_DAYS = 730;

    public function collect(CarbonImmutable $holdoutStart, int $holdoutId): array
    {
        $asOf = $holdoutStart->startOfDay()->subDay();
        $shared = app(PlayerSharedAbilityService::class);
        $history = app(PlayerPerformanceHistoryService::class);
        $pilot = app(PlayerPerformancePilotService::class);
        $rows = [];
        $seen = [];
        $excluded = [];
        $events = Event::query()->with('eventTypeModel')->where('id', '!=', $holdoutId)
            ->where('published', true)->whereNotNull('end_date')->whereColumn('end_date', '>=', 'start_date')
            ->whereDate('end_date', '<', $holdoutStart->toDateString())
            ->whereDate('end_date', '>=', $asOf->subDays(self::WINDOW_DAYS)->toDateString());
        foreach ($events->lazyById(25) as $event) {
            $matches = (function () use ($history, $event, $pilot, $asOf) {
                yield from $history->individualMatches($event, null, $pilot, $asOf, true);
                yield from $history->teamMatches($event, null, $asOf);
            })();
            foreach ($matches as $match) {
                if (count($seen) >= 50000) { throw new \RuntimeException('Transition source limit exceeded.'); }
                $source = $match['source'].':'.$match['fixture_id'];
                if (isset($seen[$source])) { continue; }
                $seen[$source] = true;
                $cohort = $match['source'] === 'Team match' ? ($match['shared_cohort'] ?? '') : $shared->cohort($match['cohort']);
                $reason = $match['reason'];
                if ($reason !== null || $match['discipline'] !== 'singles') { $excluded['canonical_rejection'] = ($excluded['canonical_rejection'] ?? 0) + 1; continue; }
                if (!preg_match('/^u(10|11) (boys|girls)$/', $cohort)) { continue; }
                $date = $match['confidence_date'];
                if ($date > $asOf->toDateString() || $date < $asOf->subDays(self::WINDOW_DAYS)->toDateString()) { continue; }
                $winner = $match['winner_side'] === 1 ? $match['player1_id'] : $match['player2_id'];
                $loser = $match['winner_side'] === 1 ? $match['player2_id'] : $match['player1_id'];
                if (!$winner || !$loser || $winner === $loser) { continue; }
                $rows[] = ['source_id' => $source, 'event_id' => (int) $event->id, 'cohort' => $cohort,
                    'winner' => (int) $winner, 'loser' => (int) $loser, 'date' => $date,
                    'date_basis' => $match['confidence_date_basis'], 'outcome_target' => $match['outcome_target'] ?? 1.0,
                    // Canonical historical readers do not attest ball/court compatibility.
                    'context_verified' => false];
            }
        }
        return ['rows' => $rows, 'excluded' => $excluded, 'as_of' => $asOf->toDateString()];
    }

    public static function eligible(array $row, string $target, CarbonImmutable $asOf, bool $assumeLegacyContext): bool
    {
        if (!in_array($target, self::COHORTS, true) || !preg_match('/^u(\d+) (boys|girls)$/', $row['cohort'] ?? '', $old)
            || !preg_match('/^u(\d+) (boys|girls)$/', $target, $new)) { return false; }
        return $old[2] === $new[2] && (int) $old[1] >= 10 && (int) $old[1] < (int) $new[1]
            && (int) $new[1] - (int) $old[1] <= 2 && ($row['winner'] ?? 0) > 0 && ($row['loser'] ?? 0) > 0
            && $row['winner'] !== $row['loser'] && isset($row['date']) && $row['date'] <= $asOf->toDateString()
            && $row['date'] >= $asOf->subDays(self::WINDOW_DAYS)->toDateString()
            && (($row['context_verified'] ?? false) || $assumeLegacyContext);
    }
}
