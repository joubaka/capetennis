<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;

/** Explicit private within-group view; never promotes a disconnected shared score. */
class PlayerRatingGroupProjection
{
    public function project(array $snapshot, string $comparisonGroup): array
    {
        if ($comparisonGroup === '' || ($snapshot['reason'] ?? null) !== null) { return []; }
        $graph = $snapshot['all_age'] ?? [];
        $component = $graph['components'][$comparisonGroup] ?? null;
        if (!$component || empty($component['converged']) || $comparisonGroup === ($graph['reference_component'] ?? null)) { return []; }
        $members = $component['members'] ?? [];
        if (!$members || count($members) !== count(array_unique($members))) { return []; }
        $date = $graph['policy']['as_of'] ?? null;
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { return []; }
        try { $asOf = CarbonImmutable::parse($date); } catch (\Throwable) { return []; }
        if ($asOf->toDateString() !== $date) { return []; }
        $membership = array_fill_keys($members, true);
        $edges = array_values(array_filter($graph['history_evidence']['matches'] ?? [],
            fn ($edge) => isset($membership[$edge['winner'] ?? 0], $membership[$edge['loser'] ?? 0])));
        if (!$edges) { return []; }
        foreach ($edges as $edge) {
            if (!is_string($edge['confidence_date'] ?? null)) { return []; }
        }
        try { $connections = (new AllAgeConnectionPolicy)->analyze($edges, $asOf); } catch (\Throwable) { return []; }
        $projected = [];
        foreach ($members as $id) {
            $rating = $graph['ratings'][$id] ?? [];
            $local = $rating['local_score'] ?? null;
            if (($rating['component'] ?? null) !== $comparisonGroup || !is_numeric($local)
                || !is_finite((float) $local) || $local < 0 || $local > 100
                || ($rating['score'] ?? null) !== null || !empty($rating['comparable'])) { return []; }
            $projected[$id] = array_intersect_key($rating, array_flip([
                'score', 'comparable', 'component', 'comparison_group', 'cohort', 'last_played',
                'confidence_label', 'confidence_explanation', 'standard_assumed_matches', 'played',
                'direct_opponents',
            ])) + ['score' => null, 'comparable' => false, 'last_played' => '', 'cohort' => AllAgeRatingModel::COHORT];
            $projected[$id]['within_group_score'] = (float) $local;
            $projected[$id]['rating_scope'] = 'group_local';
            $projected[$id]['reason'] = 'Within-group estimate only. Not comparable with ratings in another comparison group.';
            $age = $connections['connection_ages'][$id] ?? null;
            $historical = $age === null || $age > AllAgeRatingModel::RECENT_WINDOW_DAYS;
            $projected[$id]['historical_connection'] = $historical;
            $projected[$id]['connection_age_days'] = $age;
            $projected[$id]['connection_reference_date'] = $age === null ? null : $asOf->subDays($age)->toDateString();
            // The missing shared reference must never become a stronger evidence claim.
            $projected[$id]['confidence_label'] = $historical ? 'Low' : (($rating['confidence_label'] ?? 'Low') === 'High' ? 'Medium' : ($rating['confidence_label'] ?? 'Low'));
        }
        return $projected;
    }
}
