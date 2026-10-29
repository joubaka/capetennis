<?php

namespace App\Services\Performance;

use App\Models\Player;
use Carbon\CarbonImmutable;

class PlayerRatingHistoryService
{
    public function __construct(private readonly PlayerSharedAbilityService $ability, private readonly PlayerRatingHistoryStore $store) {}

    /** Page reads never calculate, enqueue, or update ratings. */
    public function forPlayer(Player $player, array $filters = []): array
    {
        $saved = $this->store->read();
        if ($saved === null) return ['series' => [], 'reason' => 'Rating history is awaiting its first saved calculation. Updates run nightly at 03:00 SAST.'];
        $pending = $saved['source_fingerprint'] !== $this->ability->fingerprint();
        if ($pending && !$this->canRetainSavedHistory($saved)) {
            return ['series' => [], 'reason' => 'Rating history is awaiting the next nightly update because its source results or publication changed.'];
        }
        $series = [];
        $hasSharedProjection = false;
        foreach ($saved['months'] as $index => $snapshot) {
            $hasSharedProjection = $hasSharedProjection || array_key_exists(AllAgeRatingModel::COHORT, $snapshot['cohorts']);
            foreach ($snapshot['cohorts'] as $cohort => $data) {
                $rating = $data[$player->id] ?? null;
                if (!$rating || !is_numeric($rating['score']) || !is_finite((float) $rating['score'])) continue;
                $series[$cohort][] = ['index' => $index, 'date' => $snapshot['date'], 'score' => $rating['score'],
                    'group' => $rating['group'], 'component' => $rating['component'], 'last_activity' => $rating['last_activity'] ?? $snapshot['date']]
                    + (isset($rating['connection_status']) ? ['connection_status' => $rating['connection_status'],
                        'historical_connection' => (bool) ($rating['historical_connection'] ?? false),
                        'connection_age_days' => $rating['connection_age_days'] ?? null,
                        'connection_reference_date' => $rating['connection_reference_date'] ?? null] : []);
            }
        }
        if ($hasSharedProjection) { $series[AllAgeRatingModel::COHORT] ??= []; }
        $cohorts = array_keys($series);
        usort($cohorts, fn ($a, $b) => strcmp((end($series[$b]) ?: [])['last_activity'] ?? '', (end($series[$a]) ?: [])['last_activity'] ?? '') ?: strcmp($a, $b));
        $cohort = isset($series[$filters['cohort'] ?? '']) ? $filters['cohort'] : (isset($series[AllAgeRatingModel::COHORT]) ? AllAgeRatingModel::COHORT : ($cohorts[0] ?? null));
        $month = max(0, min(11, (int) ($filters['month'] ?? 11)));
        $scope = ($filters['scope'] ?? 'new') === 'all' ? 'all' : 'new';
        $points = $cohort ? $series[$cohort] : [];
        $point = collect($points)->firstWhere('index', $month);
        $previous = collect($points)->firstWhere('index', $month - 1);
        $evidence = $this->evidence($saved['evidence'], $player, $cohort, $month, $scope, (int) ($filters['page'] ?? 1), $point !== null);
        return ['series' => $series, 'reason' => null, 'as_of' => $saved['as_of'], 'built_at' => $saved['built_at'],
            'stale' => $pending || $saved['as_of'] < CarbonImmutable::today('Africa/Johannesburg')->toDateString(),
            'cohort' => $cohort, 'rating_scope' => $cohort === AllAgeRatingModel::COHORT ? 'all_age' : 'cohort', 'shared_available' => $hasSharedProjection, 'points' => $points, 'month' => $month, 'months' => $saved['months'], 'scope' => $scope,
            'point' => $point, 'previous' => $previous, 'evidence' => $evidence,
            'scale' => ($filters['scale'] ?? '') === 'full' ? 'full' : 'focused',
            'axis' => PlayerRatingHistoryDisplay::axis($points, ($filters['scale'] ?? '') === 'full' ? 'full' : 'focused')];
    }

    private function canRetainSavedHistory(array $saved): bool
    {
        if (array_key_exists('publication_manifest', $saved)) {
            try {
                return is_array($saved['publication_manifest']) && app(PlayerAbilitySnapshotStore::class)->published($saved['publication_manifest']);
            } catch (\Throwable) { return false; }
        }
        // Older builds have no captured context. Only the existing private preview
        // audience may retain those builds after a source change.
        return auth()->user()?->hasRole('super-user') === true;
    }

    private function evidence(array $registry, Player $player, ?string $cohort, int $month, string $scope, int $page, bool $rated): array
    {
        $rows = []; $included = 0; $new = 0; $matches = 0; $wins = 0; $finishes = 0; $bit = 1 << $month; $previousBit = $month > 0 ? 1 << ($month - 1) : 0;
        foreach ($rated ? $registry : [] as $row) {
            if ($row['cohort'] !== $cohort || !($row['months'] & $bit)) continue;
            $side = array_search((int) $player->id, $row['players'], true);
            if ($side === false) continue;
            $included++;
            if ($row['kind'] === 'match') { $matches++; if ($row['winner_side'] === $side + 1) $wins++; }
            else $finishes++;
            $isNew = !($row['months'] & $previousBit);
            if ($isNew) $new++;
            if ($scope === 'new' && !$isNew) continue;
            $row['new'] = $isNew;
            if ($row['kind'] === 'match') {
                $row['opponent'] = $row['names'][$side === 0 ? 1 : 0];
                $row['won'] = $row['winner_side'] === $side + 1;
                if ($side === 1) $row['score'] = preg_replace_callback('/(\d+)-(\d+)/', fn ($pair) => $pair[2].'-'.$pair[1], $row['score']);
                $row['role'] = ($row['baseline_months'] & $bit) ? 'Main-trial baseline result' : 'Played match';
            } else {
                $row['position'] = $side + 1;
                $row['field_size'] = count($row['players']);
                $row['role'] = 'Finishing-order evidence';
            }
            unset($row['names'], $row['players'], $row['months'], $row['baseline_months']);
            $rows[] = $row;
        }
        usort($rows, fn ($a, $b) => strcmp($b['date'], $a['date']) ?: $b['event_id'] <=> $a['event_id'] ?: strcmp($a['source_id'], $b['source_id']));
        $total = count($rows); $pages = max(1, (int) ceil($total / 20)); $page = max(1, min($page, $pages));
        return ['rows' => array_slice($rows, ($page - 1) * 20, 20), 'total' => $total, 'included' => $included, 'new' => $new, 'page' => $page, 'pages' => $pages,
            'matches' => $matches, 'wins' => $wins, 'losses' => $matches - $wins, 'finishes' => $finishes];
    }

    /** Explicit background calculation; completed-event evidence is validated once for all months. */
    public function calculate(CarbonImmutable $today): array
    {
        $fingerprint = $this->ability->fingerprint();
        $months = []; $sources = []; $evidence = new PlayerRatingEvidenceBuilder;
        $sourceEvents = []; $sourceMatches = []; $shared = false;
        for ($offset = 11; $offset >= 0; $offset--) {
            $date = $offset === 0 ? $today : $today->startOfMonth()->subMonths($offset)->endOfMonth()->startOfDay();
            $snapshot = $this->ability->historySnapshot($date, $sources);
            if ($snapshot['reason']) throw new \RuntimeException('Monthly history calculation withheld.');
            $sourceEvents = array_values(array_unique(array_merge($sourceEvents, $snapshot['source_events'] ?? [])));
            $sourceMatches = array_values(array_unique(array_merge($sourceMatches, $snapshot['source_matches'] ?? [])));
            $shared = $shared || isset($snapshot['all_age']);
            $evidence->append($snapshot, $sources, 11 - $offset);
            $compact = [];
            $projections = $snapshot['cohorts'];
            if (isset($snapshot['all_age'])) { $projections[AllAgeRatingModel::COHORT] = $snapshot['all_age']; }
            foreach ($projections as $cohort => $data) {
                $compact[$cohort] = [];
                $groups = [];
                foreach ($data['components'] as $id => $component) {
                    if (!$component['converged']) throw new \RuntimeException('Monthly history model did not converge.');
                    $members = $component['members']; sort($members);
                    $groups[$id] = hash('sha256', json_encode([$members, $data['baseline']['metadata']['event_id'] ?? null]));
                }
                foreach ($data['ratings'] as $id => $rating) {
                    $compact[$cohort][$id] = ['score' => $rating['score'], 'component' => $rating['component'], 'group' => $groups[$rating['component']], 'last_activity' => $data['players'][$id]['last_played'] ?? $date->toDateString()];
                    if ($cohort === AllAgeRatingModel::COHORT && isset($rating['connection_status'])) {
                        $compact[$cohort][$id]['group'] = hash('sha256', json_encode([$groups[$rating['component']], $data['recent_backbone']['members'] ?? [], $rating['connection_status']], JSON_THROW_ON_ERROR));
                        $compact[$cohort][$id] += ['connection_status' => $rating['connection_status'],
                            'historical_connection' => (bool) ($rating['historical_connection'] ?? false),
                            'connection_age_days' => $rating['connection_age_days'] ?? null,
                            'connection_reference_date' => $rating['connection_reference_date'] ?? null];
                    }
                }
            }
            $months[] = ['date' => $date->toDateString(), 'cohorts' => $compact];
        }
        $sourceEvents = array_values(array_unique(array_merge($sourceEvents, array_column($evidence->all(), 'event_id'))));
        $context = ['source_events' => $sourceEvents, 'source_matches' => $sourceMatches];
        if ($shared) { $context['all_age'] = []; }
        $manifest = $sourceEvents ? app(PlayerAbilitySnapshotStore::class)->manifest($context) : [];
        if ($fingerprint !== $this->ability->fingerprint()) throw new \RuntimeException('History sources changed during calculation.');
        return ['months' => $months, 'evidence' => $evidence->all(), 'as_of' => $today->toDateString(), 'built_at' => CarbonImmutable::now('Africa/Johannesburg')->toIso8601String(),
            'source_fingerprint' => $fingerprint, 'publication_manifest' => $manifest, 'policy_hash' => $this->store->policyHash(), 'version' => PlayerRatingHistoryStore::VERSION];
    }
}
