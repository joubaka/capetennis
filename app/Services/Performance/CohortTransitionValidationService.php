<?php

namespace App\Services\Performance;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Private aggregate evaluation. No snapshot, cache, audit command or filesystem writer. */
class CohortTransitionValidationService
{
    public function run(Event $event, bool $assumeContext = false): array
    {
        $start = CarbonImmutable::parse($event->getRawOriginal('start_date'));
        $end = CarbonImmutable::parse($event->getRawOriginal('end_date'));
        if (!$event->getRawOriginal('start_date') || !$event->getRawOriginal('end_date') || !$event->published
            || $end->lessThan($start) || $end->greaterThan(CarbonImmutable::today('Africa/Johannesburg'))) {
            throw new \InvalidArgumentException('Choose a completed published event.');
        }
        $asOf = $start->subDay();
        $shared = app(PlayerSharedAbilityService::class);
        $fingerprint = $shared->fingerprint();
        $native = $shared->validationSnapshot($start, (int) $event->id);
        if ($native['reason']) { throw new \RuntimeException('Native training calculation withheld.'); }
        foreach ($native['cohorts'] as $c) { foreach ($c['components'] as $component) { if (!$component['converged']) { throw new \RuntimeException('Native training calculation did not converge.'); } } }
        $evidence = app(CohortTransitionEvidenceService::class)->collect($start, (int) $event->id);
        $history = app(PlayerPerformanceHistoryService::class);
        $pilot = app(PlayerPerformancePilotService::class);
        $heldout = (function () use ($history, $pilot, $event, $end) {
            yield from $history->individualMatches($event, null, $pilot, $end, true);
            yield from $history->teamMatches($event, null, $end);
        })();
        $rows = [];
        foreach ($heldout as $r) { $cohort = $r['source'] === 'Team match' ? ($r['shared_cohort'] ?? '') : $shared->cohort($r['cohort']); if (in_array($cohort, CohortTransitionEvidenceService::COHORTS, true)) { $rows[$cohort][] = $r; } }
        // Identity routing only: every resolved participant is included, even
        // from rejected/unfinished rows. Winners, scores and rejection reasons
        // do not choose candidates, references, components or fit parameters.
        $predictions = $this->predictions($native, $evidence['rows'], $asOf, $assumeContext, $this->participantRoutes($rows));
        $cohorts = [];
        foreach (CohortTransitionEvidenceService::COHORTS as $cohort) {
            $cohorts[$cohort] = $this->evaluate($native['cohorts'][$cohort] ?? [], $rows[$cohort] ?? [], $predictions[$cohort] ?? []);
        }
        if ($fingerprint !== $shared->fingerprint()) { throw new \RuntimeException('Source fingerprint changed.'); }
        return ['preview_only' => true, 'model' => CohortTransitionAbilityModel::VERSION, 'event_id' => (int) $event->id,
            'event_name' => $event->name, 'event_start' => $start->toDateString(), 'training_as_of' => $asOf->toDateString(),
            'source_fingerprint' => $fingerprint, 'assumed_legacy_context' => $assumeContext,
            'policy' => ['window_days' => 730, 'continuity_scales' => [0.5, 1.0, 2.0], 'minimum_reference_peers' => 2,
                'robust_minimum_source_events' => 2, 'maximum_sensitivity_strength_range' => 0.5,
                'native_predictions_fixed' => true, 'new_current_match_count' => 0],
            'context_caveat' => 'Historical ball/court compatibility is not attested by canonical readers. Legacy context assumption is research only; no estimate is approved for display.',
            'historical_source_count' => count($evidence['rows']), 'historical_exclusions' => $evidence['excluded'], 'cohorts' => $cohorts,
            'interpretation' => 'Current publication/identity records are retrospective. Repeated players/events are dependent. Existing pairs and newly covered pairs are scored separately. No confidence or saved rating is changed.'];
    }

    /** Pure identity routing; deliberately does not inspect match outcomes or validity. */
    public function participantRoutes(array $rowsByCohort): array
    {
        $routes = [];
        foreach (CohortTransitionEvidenceService::COHORTS as $cohort) {
            foreach ($rowsByCohort[$cohort] ?? [] as $row) {
                foreach ([$row['player1_id'] ?? null, $row['player2_id'] ?? null] as $id) {
                    if ((is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id > 0) { $routes[$cohort][(int) $id] = true; }
                }
            }
        }
        return $routes;
    }

    /** Optional routing skips independent predictions that cannot enter the report. */
    public function predictions(array $native, array $historicalRows, CarbonImmutable $asOf, bool $assumeContext = false, ?array $routes = null): array
    {
        $predictions = [];
        foreach (CohortTransitionEvidenceService::COHORTS as $cohort) {
            $candidateIds = [];
            foreach ($historicalRows as $r) {
                if (!CohortTransitionEvidenceService::eligible($r, $cohort, $asOf, true)) { continue; }
                $candidateIds[$r['winner']] = true; $candidateIds[$r['loser']] = true;
            }
            ksort($candidateIds);
            foreach (array_keys($candidateIds) as $id) {
                if (isset($native['cohorts'][$cohort]['ratings'][$id]) || ($routes !== null && !isset($routes[$cohort][$id]))) { continue; }
                // All historical matches and native reference players remain
                // available to each fit; routing never prunes its evidence.
                $predictions[$cohort][$id] = app(CohortTransitionAbilityModel::class)->estimate($id, $cohort, $historicalRows, $native['cohorts'][$cohort] ?? [], $asOf, $assumeContext);
            }
        }
        return $predictions;
    }

    public function evaluate(array $native, array $rows, array $predictions): array
    {
        $base = []; $new = ['robust' => [], 'exploratory' => []]; $combined = ['robust' => [], 'exploratory' => []];
        $eligible = 0; $excluded = []; $seen = []; $missing = []; $newPlayers = ['robust' => [], 'exploratory' => []]; $reasons = []; $support = [];
        foreach ($rows as $r) {
            $id = $r['source'].':'.$r['fixture_id']; if (isset($seen[$id])) { continue; } $seen[$id] = true;
            if ($r['reason'] !== null || $r['discipline'] !== 'singles') { $excluded['canonical_rejection'] = ($excluded['canonical_rejection'] ?? 0) + 1; continue; }
            $eligible++;
            $first = $native['ratings'][$r['player1_id']] ?? null; $second = $native['ratings'][$r['player2_id']] ?? null;
            $nativePair = $this->probability($first, $second, $r['winner_side']);
            if ($nativePair !== null) { $base[] = $nativePair; foreach (array_keys($combined) as $profile) { $combined[$profile][] = $nativePair; } continue; }
            foreach ([$r['player1_id'], $r['player2_id']] as $player) {
                if (isset($native['ratings'][$player])) { continue; }
                $missing[$player] = true;
                $prediction = $predictions[$player] ?? ['available' => false, 'reason' => 'no_eligible_history'];
                $reasons[$player] = $prediction['reason'] ?? 'robust_candidate';
                if (isset($prediction['support'])) { $support[$player] = $prediction; }
            }
            foreach (array_keys($combined) as $profile) {
                $a = $first ?? $this->candidate($predictions[$r['player1_id']] ?? [], $profile);
                $b = $second ?? $this->candidate($predictions[$r['player2_id']] ?? [], $profile);
                $p = $this->probability($a, $b, $r['winner_side']);
                if ($p === null) { continue; }
                $new[$profile][] = $p; $combined[$profile][] = $p;
                foreach ([$r['player1_id'], $r['player2_id']] as $player) { if (!isset($native['ratings'][$player])) { $newPlayers[$profile][$player] = true; } }
            }
        }
        $reasonCounts = [];
        foreach ($reasons as $reason) { $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1; }
        $sensitivity = ['candidate_players' => 0, 'historical_matches' => 0, 'historical_events_player_incidents' => 0,
            'supporting_players_tested' => 0, 'leave_player_out_abstentions' => 0,
            'leave_peer_out_abstentions' => 0, 'leave_nonreference_player_out_abstentions' => 0,
            'leave_event_out_abstentions' => 0, 'maximum_strength_range' => null];
        foreach ($support as $p) {
            if (!($p['available'] ?? false)) { continue; }
            $sensitivity['candidate_players']++; $sensitivity['historical_matches'] += $p['support']['historical_matches'];
            $sensitivity['historical_events_player_incidents'] += $p['support']['historical_events'];
            $sensitivity['supporting_players_tested'] += $p['sensitivity']['supporting_players_tested'];
            $sensitivity['leave_player_out_abstentions'] += $p['sensitivity']['leave_player_out_abstentions'];
            $sensitivity['leave_peer_out_abstentions'] += $p['sensitivity']['leave_peer_out_abstentions'];
            $sensitivity['leave_nonreference_player_out_abstentions'] += $p['sensitivity']['leave_nonreference_player_out_abstentions'];
            $sensitivity['leave_event_out_abstentions'] += $p['sensitivity']['leave_event_out_abstentions'];
            $sensitivity['maximum_strength_range'] = max($sensitivity['maximum_strength_range'] ?? 0, $p['sensitivity']['strength_range']);
        }
        return ['eligible_matches' => $eligible, 'native' => $this->metrics($base), 'native_coverage' => $eligible ? count($base) / $eligible : null,
            'same_pairs_experiment' => $this->metrics($base), 'same_pairs_predictions_identical' => true,
            'robust' => ['new_pairs' => $this->metrics($new['robust']), 'combined' => $this->metrics($combined['robust']), 'coverage' => $eligible ? count($combined['robust']) / $eligible : null, 'new_players' => count($newPlayers['robust'])],
            'exploratory_only' => ['new_pairs' => $this->metrics($new['exploratory']), 'combined' => $this->metrics($combined['exploratory']), 'coverage' => $eligible ? count($combined['exploratory']) / $eligible : null, 'new_players' => count($newPlayers['exploratory'])],
            'neutral_reference_on_new_pairs' => ['robust' => $this->metrics(array_fill(0, count($new['robust']), 0.5)), 'exploratory' => $this->metrics(array_fill(0, count($new['exploratory']), 0.5))],
            'missing_native_players' => count($missing), 'transition_reasons_by_missing_player' => $reasonCounts, 'sensitivity' => $sensitivity, 'excluded' => $excluded];
    }

    public static function savedDigest(): string
    {
        return hash('sha256', json_encode(DB::table('player_ability_snapshots')->orderBy('snapshot_key')->get()->all(), JSON_THROW_ON_ERROR));
    }

    private function candidate(array $p, string $profile): ?array
    {
        if (!($p['available'] ?? false) || ($profile === 'robust' && !($p['robust'] ?? false))) { return null; }
        return ['strength' => $p['strength'], 'component' => $p['component'], 'score' => 0.0];
    }

    private function probability(?array $first, ?array $second, int $winner): ?float
    {
        if (!$first || !$second || ($first['score'] ?? null) === null || ($second['score'] ?? null) === null
            || $first['component'] !== $second['component'] || !is_finite($first['strength']) || !is_finite($second['strength'])) { return null; }
        $p = 1 / (1 + exp(-max(-40, min(40, $first['strength'] - $second['strength']))));
        return $winner === 1 ? $p : 1 - $p;
    }

    private function metrics(array $probabilities): array
    {
        $wins = 0; $ties = 0; $brier = 0; $loss = 0;
        foreach ($probabilities as $p) { if (abs($p - 0.5) < 1e-12) { $ties++; } elseif ($p > 0.5) { $wins++; } $brier += (1 - $p) ** 2; $loss -= log(max(1e-15, $p)); }
        $n = count($probabilities);
        return ['evaluated_matches' => $n, 'correct_higher_rated' => $wins, 'equal_rating_pairs' => $ties,
            'higher_rated_win_rate' => $n > $ties ? $wins / ($n - $ties) : null, 'brier' => $n ? $brier / $n : null, 'log_loss' => $n ? $loss / $n : null];
    }
}
