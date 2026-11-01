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
        if (isset($saved['publication_manifest']['teamsheet_context']) && !app(TeamSheetTennisEvidence::class)->published($saved['publication_manifest']['teamsheet_context'])) {
            return ['series' => [], 'reason' => 'Rating history is awaiting an update because its TeamSheet source is stale or changed.'];
        }
        $pending = $saved['source_fingerprint'] !== $this->ability->fingerprint();
        if ($pending && !$this->canRetainSavedHistory($saved)) {
            return ['series' => [], 'reason' => 'Rating history is awaiting the next nightly update because its source results or publication changed.'];
        }
        $series = [];
        $hasSharedProjection = false;
        foreach ($saved['months'] as $index => $snapshot) {
            $hasSharedProjection = $hasSharedProjection || array_key_exists(AllAgeRatingModel::COHORT, $snapshot['cohorts']);
            foreach ($snapshot['cohorts'] as $cohort => $data) {
                if ($cohort !== AllAgeRatingModel::COHORT && BallRatingModel::stage($cohort) === null) continue;
                $rating = $data[$player->id] ?? null;
                if (!$rating || !is_numeric($rating['score']) || !is_finite((float) $rating['score'])) continue;
                $series[$cohort][] = ['index' => $index, 'date' => $snapshot['date'], 'score' => $rating['score'],
                    'group' => $rating['group'], 'component' => $rating['component'], 'last_activity' => $rating['last_activity'] ?? $snapshot['date']]
                    + array_intersect_key($rating, array_flip(['confidence_label', 'last_direct_match', 'recent_played', 'direct_opponents', 'played_events', 'proxy_dated_matches', 'evidence_basis', 'finish_events', 'inferred']))
                    + (isset($rating['connection_status']) ? ['connection_status' => $rating['connection_status'],
                        'historical_connection' => (bool) ($rating['historical_connection'] ?? false),
                        'connection_age_days' => $rating['connection_age_days'] ?? null,
                        'connection_reference_date' => $rating['connection_reference_date'] ?? null] : []);
            }
        }
        if ($hasSharedProjection) { $series[AllAgeRatingModel::COHORT] ??= []; }
        $latestMonth = $saved['months'] ? $saved['months'][array_key_last($saved['months'])] : [];
        $stageRatings = [];
        foreach (BallRatingModel::COHORTS as $stage => $stageCohort) $stageRatings[$stage] = $latestMonth['cohorts'][$stageCohort][$player->id] ?? [];
        $currentStage = BallRatingModel::currentStage($stageRatings);
        // Older months remain in the player's current saved rating group.
        $cohort = $currentStage ? BallRatingModel::COHORTS[$currentStage] : '';
        $month = max(0, min(11, (int) ($filters['month'] ?? 11)));
        $scope = ($filters['scope'] ?? 'new') === 'all' ? 'all' : 'new';
        $points = $series[$cohort] ?? [];
        $point = collect($points)->firstWhere('index', $month);
        $previous = collect($points)->firstWhere('index', $month - 1);
        $evidence = $this->evidence($saved['evidence'], $player, $cohort, $month, $scope, (int) ($filters['page'] ?? 1), $point !== null);
        $pathwayMetrics = app(PlayerPathwayEvidence::class)->fromHistory($saved, $player, $series[AllAgeRatingModel::COHORT] ?? [], $pending);
        return ['series' => $series, 'reason' => null, 'as_of' => $saved['as_of'], 'built_at' => $saved['built_at'],
            'stale' => $pending || $saved['as_of'] < CarbonImmutable::today('Africa/Johannesburg')->toDateString(),
            'cohort' => $cohort, 'current_group_label' => $currentStage ? BallRatingModel::LABELS[$currentStage] : 'Not rated yet',
            'ball_stage' => $currentStage, 'rating_scope' => 'ball', 'shared_available' => $hasSharedProjection,
            'ball_series' => array_intersect_key($series, array_flip(array_values(BallRatingModel::COHORTS))), 'points' => $points, 'month' => $month, 'months' => $saved['months'], 'scope' => $scope,
            'point' => $point, 'previous' => $previous, 'evidence' => $evidence,
            'pathway_metrics' => $pathwayMetrics,
            'scale' => 'full',
            'axis' => PlayerRatingHistoryDisplay::axis($points, 'full')];
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
        $rows = []; $included = 0; $new = 0; $matches = 0; $wins = 0; $finishes = 0; $restricted = 0; $bit = 1 << $month; $previousBit = $month > 0 ? 1 << ($month - 1) : 0;
        foreach ($rated ? $registry : [] as $row) {
            $ball = BallRatingModel::stage($cohort ?? '');
            if ($row['cohort'] !== $cohort || !($row['months'] & $bit)) continue;
            if ($ball !== null && BallRatingModel::forMatch($row) !== $ball) continue;
            $side = array_search((int) $player->id, $row['players'], true);
            if ($side === false) continue;
            $included++;
            if ($row['kind'] === 'match') { $matches++; if ($row['winner_side'] === $side + 1) $wins++; }
            else $finishes++;
            $isNew = !($row['months'] & $previousBit);
            if ($isNew) $new++;
            if ($scope === 'new' && !$isNew) continue;
            if (str_starts_with($row['source_id'], 'teamsheet:') && !auth()->user()?->hasRole('super-user')) { $restricted++; continue; }
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
            'matches' => $matches, 'wins' => $wins, 'losses' => $matches - $wins, 'finishes' => $finishes, 'restricted' => $restricted];
    }

    /** Explicit background calculation; source evidence is validated once for all monthly cutoffs. */
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
            foreach (PlayerRatingEvidenceBuilder::projections($snapshot) as $cohort => $data) {
                $compact[$cohort] = [];
                $groups = [];
                foreach ($data['components'] as $id => $component) {
                    if (!$component['converged']) throw new \RuntimeException('Monthly history model did not converge for '.$cohort.' at '.$date->toDateString().'.');
                    $members = $component['members']; sort($members);
                    $groups[$id] = hash('sha256', json_encode([$members, $data['baseline']['metadata']['event_id'] ?? null]));
                }
                foreach ($data['ratings'] as $id => $rating) {
                    $compact[$cohort][$id] = ['score' => $rating['score'], 'component' => $rating['component'], 'group' => $groups[$rating['component']],
                        'last_activity' => max($data['players'][$id]['last_played'] ?? '', $data['players'][$id]['last_finish'] ?? '') ?: $date->toDateString()];
                    $compact[$cohort][$id] += array_intersect_key($rating, array_flip(['confidence_label', 'last_direct_match', 'recent_played', 'direct_opponents', 'played_events', 'proxy_dated_matches', 'evidence_basis', 'finish_events', 'inferred']));
                    if (($cohort === AllAgeRatingModel::COHORT || BallRatingModel::stage($cohort) !== null) && isset($rating['connection_status'])) {
                        $compact[$cohort][$id]['group'] = hash('sha256', json_encode([$groups[$rating['component']], $data['recent_backbone']['members'] ?? [], $rating['connection_status']], JSON_THROW_ON_ERROR));
                        $compact[$cohort][$id] += ['connection_status' => $rating['connection_status'],
                            'historical_connection' => (bool) ($rating['historical_connection'] ?? false),
                            'connection_age_days' => $rating['connection_age_days'] ?? null,
                            'connection_reference_date' => $rating['connection_reference_date'] ?? null];
                    }
                }
            }
            $ballStatus = [];
            foreach ($snapshot['ball_ratings'] ?? [] as $stage => $graph) $ballStatus[$stage] = ['reason' => $graph['unrated_reason'] ?? null,
                'excluded' => (int) ($graph['excluded']['Ball-level model did not converge'] ?? 0)];
            $months[] = ['date' => $date->toDateString(), 'cohorts' => $compact, 'ball_status' => $ballStatus];
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
