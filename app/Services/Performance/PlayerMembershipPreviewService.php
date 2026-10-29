<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;

/** Bounded private presentation experiment; saved calculations are read, never refreshed. */
class PlayerMembershipPreviewService
{
    public const COHORTS = ['u10 boys' => 'U10 boys', 'u10 girls' => 'U10 girls', 'u11 boys' => 'U11 boys', 'u11 girls' => 'U11 girls',
        'u12 boys' => 'U12 boys', 'u12 girls' => 'U12 girls', 'u13 boys' => 'U13 boys', 'u13 girls' => 'U13 girls'];
    private const ROW_CAP = 4000;

    public function __construct(private PlayerAbilitySnapshotStore $store) {}

    public function build(string $cohort = '', string $search = ''): array
    {
        $snapshot = $this->store->current();
        $data = ['rows' => [], 'active' => [], 'total_count' => 0, 'available_count' => 0,
            'provisional_count' => 0, 'active_count' => 0, 'established_count' => 0, 'inactive_count' => 0, 'unrated_count' => 0, 'unavailable_count' => 0, 'cohort_summary' => [],
            'cohorts' => self::COHORTS, 'selected' => $cohort, 'search' => $search, 'as_of' => null, 'built_at' => null, 'snapshot_version' => null,
            'reason' => null, 'criteria' => ['matches' => 8, 'events' => 2, 'opponents' => 4, 'window_days' => 365, 'recent_days' => 90, 'leaderboard_calendar_months' => 24, 'profile_calendar_months' => 6]];
        if (!array_key_exists('reason', $snapshot) || $snapshot['reason'] !== null) {
            $data['reason'] = 'Saved ratings are unavailable or their source publication has changed. This preview is withheld.';
            return $data;
        }
        $today = CarbonImmutable::today('Africa/Johannesburg');
        $asOf = $this->date($snapshot['snapshot_as_of'] ?? null);
        if (!$asOf || $asOf->greaterThan($today) || ($snapshot['snapshot_stale'] ?? true) || !$asOf->isSameDay($today)) {
            $data['reason'] = 'The saved rating snapshot is out of date. Player cards and the active leaderboard are withheld until a current saved snapshot is available.';
            return $data;
        }
        $total = 0;
        foreach (self::COHORTS as $key => $label) {
            $ratings = $snapshot['cohorts'][$key]['ratings'] ?? [];
            if (!is_array($ratings) || ($total += count($ratings)) > self::ROW_CAP) {
                $data['reason'] = 'The saved preview exceeds its safe processing limit or has invalid cohort data. No partial directory or leaderboard is displayed.';
                return $data;
            }
        }
        $data['total_count'] = $total;
        $data['as_of'] = $asOf->toDateString();
        $data['snapshot_version'] = $snapshot['snapshot_version'] ?? null;
        $data['built_at'] = $snapshot['built_at'] ?? null;
        $rows = [];
        foreach (self::COHORTS as $key => $label) {
            $group = $snapshot['cohorts'][$key] ?? [];
            foreach ($group['ratings'] ?? [] as $id => $rating) {
                if (!(is_int($id) || (is_string($id) && ctype_digit($id))) || (int) $id <= 0) { continue; }
                $id = (int) $id;
                $own = $group['players'][$id] ?? null;
                $name = $snapshot['names'][$id] ?? null;
                if (!is_array($rating) || !is_array($own) || !is_string($name) || trim($name) === '' || mb_strlen($name) > 150
                    || !is_numeric($rating['score'] ?? null) || !is_numeric($rating['strength'] ?? null)
                    || !is_finite((float) $rating['score']) || !is_finite((float) $rating['strength'])
                    || $rating['score'] < 0 || $rating['score'] > 100 || !is_string($rating['component'] ?? null)) { continue; }
                $component = $group['components'][$rating['component']] ?? [];
                if (!($component['converged'] ?? false)) { continue; }
                $matches = $own['confidence_matches'] ?? [];
                $playedCount = $own['played'] ?? null;
                if (!(is_int($playedCount) || (is_string($playedCount) && ctype_digit($playedCount))) || (int) $playedCount < 0
                    || !is_array($matches) || count($matches) > 500 || count($matches) !== (int) $playedCount) { continue; }
                $recent = []; $valid = true; $last = null;
                foreach ($matches as $match) {
                    $date = $this->date($match['date'] ?? null);
                    if (!$date || $date->greaterThan($asOf) || !is_string($match['basis'] ?? null) || !is_numeric($match['event'] ?? null) || (int) $match['event'] <= 0
                        || !is_numeric($match['opponent'] ?? null) || (int) $match['opponent'] <= 0) { $valid = false; break; }
                    $last = $last === null || $date->greaterThan($last) ? $date : $last;
                    if ($date->diffInDays($asOf, false) <= 365) { $recent[] = $match; }
                }
                if (!$valid) { continue; }
                $played = count($recent); $events = count(array_unique(array_column($recent, 'event')));
                $opponents = count(array_unique(array_column($recent, 'opponent')));
                $fresh = $last !== null && $last->diffInDays($asOf, false) <= 90;
                $reference = (bool) ($component['trial_anchored'] ?? false);
                $eligible = $last !== null && $last->greaterThanOrEqualTo($asOf->subMonthsNoOverflow(24));
                $established = $eligible && $fresh && $played >= 8 && $events >= 2 && $opponents >= 4 && $reference;
                $unrated = !$matches;
                $status = $established ? 'Established evidence' : ($unrated ? 'Unrated' : (!$eligible ? 'Inactive' : 'Provisional'));
                $needs = [];
                if ($played < 8) { $needs[] = (8 - $played).' more recorded singles match'.(8 - $played === 1 ? '' : 'es'); }
                if ($events < 2) { $needs[] = 'results from another event'; }
                if ($opponents < 4) { $needs[] = 'more distinct opponents'; }
                if (!$fresh) { $needs[] = 'a recorded match within 90 days'; }
                if (!$reference) { $needs[] = 'a connected trial reference'; }
                $confidenceOwn = ['confidence_matches' => $matches, 'last_finish' => $this->date($own['last_finish'] ?? null)?->toDateString()];
                $confidence = app(SharedAbilityConfidencePolicy::class)->evaluate($confidenceOwn, $component, $asOf);
                $rows[] = ['id' => $id, 'name' => trim($name), 'cohort' => $key, 'cohort_label' => self::COHORTS[$key],
                    'score' => $unrated ? null : round((float) $rating['score'], 1), 'strength' => $unrated ? null : (float) $rating['strength'], 'component' => $rating['component'],
                    'played' => $played, 'events' => $events, 'opponents' => $opponents, 'last_match' => $last?->toDateString(),
                    'eligible' => $eligible, 'established' => $established, 'status' => $status, 'position' => null, 'needs' => $needs, 'played_all_time' => count($matches),
                    'own_inferred' => (int) ($own['inferred'] ?? 0), 'component_inferred' => (int) ($component['inferred'] ?? 0),
                    'baseline_status' => isset($group['baseline']['anchors'][$id]) ? 'Direct trial reference' : ($reference ? 'Linked to trial reference' : 'No connected trial reference'),
                    'confidence' => $unrated ? 'Unavailable' : AbilityConfidenceDisplay::label($confidence['confidence_index'], ['played' => $played,
                        'component_inferred' => $component['inferred'] ?? null, 'bridge_count' => $component['bridge_count'] ?? null,
                        'baseline_status' => isset($group['baseline']['anchors'][$id]) ? 'Direct main-trial baseline' : ($reference ? 'Linked to main-trial baseline' : 'No connected main-trial baseline')])];
            }
        }
        usort($rows, fn ($a, $b) => strcmp($a['cohort'], $b['cohort']) ?: strcmp($a['component'], $b['component'])
            ?: ($b['score'] ?? -1) <=> ($a['score'] ?? -1) ?: strcasecmp($a['name'], $b['name']) ?: $a['id'] <=> $b['id']);
        $positions = [];
        foreach ($rows as &$row) {
            if (!$row['eligible']) { continue; }
            $key = $row['cohort'].'|'.$row['component'];
            $positions[$key]['seen'] = ($positions[$key]['seen'] ?? 0) + 1;
            $score = (int) round($row['score'] * 10);
            if (!isset($positions[$key]['score']) || $positions[$key]['score'] !== $score) { $positions[$key]['rank'] = $positions[$key]['seen']; }
            $positions[$key]['score'] = $score;
            $row['position'] = $positions[$key]['rank'];
        }
        unset($row);
        $data['available_count'] = count($rows);
        $data['active_count'] = count(array_filter($rows, fn ($row) => $row['eligible']));
        $data['established_count'] = count(array_filter($rows, fn ($row) => $row['established']));
        foreach (['Provisional' => 'provisional_count', 'Inactive' => 'inactive_count', 'Unrated' => 'unrated_count'] as $status => $field) {
            $data[$field] = count(array_filter($rows, fn ($row) => $row['status'] === $status));
        }
        $data['unavailable_count'] = $data['total_count'] - count($rows);
        foreach (self::COHORTS as $key => $label) {
            $ownRows = array_values(array_filter($rows, fn ($row) => $row['cohort'] === $key));
            $data['cohort_summary'][$key] = ['label' => $label, 'saved' => count($snapshot['cohorts'][$key]['ratings'] ?? []), 'available' => count($ownRows),
                'unavailable' => count($snapshot['cohorts'][$key]['ratings'] ?? []) - count($ownRows),
                'active' => count(array_filter($ownRows, fn ($row) => $row['eligible'])), 'established' => count(array_filter($ownRows, fn ($row) => $row['established'])), 'provisional' => count(array_filter($ownRows, fn ($row) => $row['status'] === 'Provisional')),
                'inactive' => count(array_filter($ownRows, fn ($row) => $row['status'] === 'Inactive')), 'unrated' => count(array_filter($ownRows, fn ($row) => $row['status'] === 'Unrated'))];
        }
        $filtered = array_values(array_filter($rows, fn ($row) => ($cohort === '' || $row['cohort'] === $cohort)
            && ($search === '' || mb_stripos($row['name'], $search) !== false)));
        $directory = $filtered;
        usort($directory, fn ($a, $b) => strcasecmp($a['name'], $b['name']) ?: strcmp($a['cohort'], $b['cohort']) ?: $a['id'] <=> $b['id']);
        $data['rows'] = $directory;
        $data['active'] = array_values(array_filter($filtered, fn ($row) => $row['eligible']));
        return $data;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) { return null; }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Africa/Johannesburg');
            return $date && $date->toDateString() === $value ? $date : null;
        } catch (\Throwable) { return null; }
    }
}
