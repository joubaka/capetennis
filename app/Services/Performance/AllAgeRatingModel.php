<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;

/** One played-singles scale; age and gender are evidence labels, never rating offsets. */
class AllAgeRatingModel
{
    public const VERSION = 6;
    public const REGULARIZATION = 0.015625;
    public const COHORT = 'all ages · singles';
    public const WINDOW_DAYS = 730;
    public const RECENT_WINDOW_DAYS = 365;
    public const HISTORICAL_CONFIDENCE_CAP = 39;

    public function format(string $category, ?string $ball): array
    {
        // Playing conditions do not classify or offset verified head-to-head results.
        return ['eligible' => true, 'assumed' => false, 'reason' => null];
    }

    /** Canonical, already validated match rows; never accepts ordinal or main-trial anchors. */
    public function fit(array $matches, CarbonImmutable $asOf, int $windowDays = self::WINDOW_DAYS): array
    {
        if (!in_array($windowDays, [self::RECENT_WINDOW_DAYS, self::WINDOW_DAYS], true)) { throw new \InvalidArgumentException('Unsupported all-age evidence window.'); }
        $asOf = $asOf->startOfDay();
        $players = []; $byEvent = []; $excluded = []; $seen = [];
        usort($matches, fn ($a, $b) => strcmp($a['source_id'], $b['source_id']));
        foreach ($matches as $match) {
            $source = $match['source_id'];
            if (isset($seen[$source])) { continue; }
            $seen[$source] = true;
            $format = $this->format($match['category'], $match['ball_type'] ?? null);
            $days = CarbonImmutable::parse($match['confidence_date'])->diffInDays($asOf, false);
            $reason = $match['format_reason'] ?? ($days < 0 || $days > $windowDays ? 'Outside the supported played-match window' : $format['reason']);
            if ($reason !== null) { $excluded[$reason] = ($excluded[$reason] ?? 0) + 1; continue; }
            $winner = (int) $match['winner']; $loser = (int) $match['loser'];
            if ($winner < 1 || $loser < 1 || $winner === $loser) { continue; }
            $edge = $match;
            $edge['kind'] = 'played';
            $edge['weight'] = pow(0.5, $days / 180);
            $byEvent[$match['event_id']][] = $edge;
            foreach ([$winner, $loser] as $id) {
                $node = &$players[$id];
                $node ??= ['played' => 0, 'inferred' => 0, 'last_played' => $match['confidence_date'], 'events' => [], 'age_groups' => [], 'standard_assumed_matches' => 0];
                $node['played']++;
                $node['last_played'] = max($node['last_played'], $match['confidence_date']);
                $node['events'][$match['event_id']] = ['id' => $match['event_id'], 'name' => $match['event_name'], 'date' => $match['confidence_date']];
                foreach ($match['age_groups'] ?? [$match['cohort']] as $group) { $node['age_groups'][$group] = $group; }
                $node['standard_assumed_matches'] += (int) $format['assumed'];
                $node['confidence_matches'][] = ['date' => $match['confidence_date'], 'basis' => $match['confidence_date_basis'], 'event' => $match['event_id'], 'opponent' => $id === $winner ? $loser : $winner];
                unset($node);
            }
        }
        $edges = [];
        foreach ($byEvent as $eventEdges) { array_push($edges, ...(new TrialBaselinePolicy)->budget($eventEdges)); }
        $fit = (new SharedAbilityModel)->fit($edges, self::COHORT, regularization: self::REGULARIZATION);
        $components = $fit['components'];
        $connections = (new AllAgeConnectionPolicy)->analyze($edges, $asOf);
        $backbone = $connections['recent_backbone'];
        $reference = $backbone ? $fit['ratings'][$backbone['members'][0]]['component'] : null;
        foreach ($components as &$component) {
            $groups = [];
            $assumed = 0;
            foreach ($component['members'] as $id) {
                $assumed += $players[$id]['standard_assumed_matches'];
                foreach ($players[$id]['age_groups'] as $group) { $groups[$group] = true; }
            }
            $component['standard_assumed_matches'] = (int) ($assumed / 2);
            $component['age_groups'] = array_keys($groups);
            sort($component['age_groups']);
        } unset($component);
        foreach ($fit['ratings'] as $id => &$rating) {
            $component = $components[$rating['component']];
            $comparable = $reference === $rating['component'] && $component['converged'];
            $connectionAge = $comparable ? ($connections['connection_ages'][$id] ?? null) : null;
            $historical = $connectionAge !== null && $connectionAge > self::RECENT_WINDOW_DAYS;
            $rating['local_score'] = $rating['score'];
            $rating += ['rating_scope' => 'all_age', 'cohort' => self::COHORT, 'comparison_group' => $rating['component'],
                'rating_status' => $comparable ? 'provisional' : 'provisional_local', 'comparable' => $comparable,
                'historical_connection' => $historical, 'connection_age_days' => $connectionAge,
                'connection_reference_date' => $connectionAge === null ? null : $asOf->subDays($connectionAge)->toDateString(),
                'connection_status' => !$comparable ? 'disconnected' : ($historical ? 'historical' : 'recent'),
                'reason' => !$component['converged'] ? 'The shared model did not converge.' : ($comparable ? null : 'Provisional within-group estimate; uncalibrated against other comparison groups. More connecting results are needed.'),
                'played' => $players[$id]['played'], 'last_played' => $players[$id]['last_played'], 'inferred' => 0,
                'component_players' => count($component['members']), 'component_played' => $component['played'],
                'component_inferred' => 0, 'bridge_count' => $component['bridge_count'], 'division_links' => 0,
                'age_groups' => array_values($players[$id]['age_groups']), 'standard_assumed_matches' => $players[$id]['standard_assumed_matches'],
                'component_age_groups' => $component['age_groups'],
                'component_standard_assumed_matches' => $component['standard_assumed_matches'],
                'baseline_status' => 'Recent shared played-match reference', 'baseline_source' => null,
                'comparators' => [], 'anchors' => []];
            $rating += (new SharedAbilityConfidencePolicy)->evaluate($players[$id], $component, $asOf);
            if ($historical || !$comparable) { $rating['confidence_index'] = min(self::HISTORICAL_CONFIDENCE_CAP, $rating['confidence_index']); }
            $rating['confidence'] = !$comparable ? 'Low — provisional within-group estimate, uncalibrated across groups' : ($historical ? 'Low — connection depends on older played results' : 'Provisional — evidence sufficiency is not calibrated accuracy');
            $rating = AbilityConfidenceDisplay::normalize($rating);
        }
        unset($rating);
        foreach ($players as &$node) { $node['age_groups'] = array_values($node['age_groups']); } unset($node);
        ksort($players, SORT_NUMERIC);
        ksort($excluded);
        return ['ratings' => $fit['ratings'], 'components' => $components, 'players' => $players, 'reference_component' => $reference, 'recent_backbone' => $backbone,
            'excluded' => $excluded, 'history_evidence' => ['matches' => $edges, 'fields' => []],
            'policy' => ['version' => self::VERSION, 'regularization' => self::REGULARIZATION, 'window_days' => $windowDays, 'recent_window_days' => self::RECENT_WINDOW_DAYS, 'as_of' => $asOf->toDateString(), 'scale' => '0–100; higher is stronger',
                'format' => 'Verified completed singles across all ball and court categories; no category-based rating offsets.',
                'status' => 'Provisional; historical links have low confidence and comparisons require the same connected reference group.']];
    }
}
