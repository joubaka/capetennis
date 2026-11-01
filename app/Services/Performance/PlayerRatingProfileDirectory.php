<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Current profile filters do not change the scope of saved rating evidence. */
class PlayerRatingProfileDirectory
{
    public const AGE_GROUPS = ['u7' => 'U7 (7 and younger)', 'u8' => 'U8 (8)', 'u9' => 'U9 (9)', 'u10' => 'U10 (10)', 'u11' => 'U11 (11)', 'u12' => 'U12 (12)', 'u13' => 'U13 (13)', 'u18' => 'U18 (14–18)', 'unknown' => 'Missing / invalid birth date', 'other' => 'Over 18'];
    private const AGE_ORDER = ['u7' => 0, 'u8' => 1, 'u9' => 2, 'u10' => 3, 'u11' => 4, 'u12' => 5, 'u13' => 6, 'u18' => 7, 'unknown' => 8, 'other' => 9];
    public const GENDERS = ['boys' => 'Boys', 'girls' => 'Girls', 'unknown' => 'Missing / unrecognised gender'];

    public static function birthYearLabels(int $year): array
    {
        return [
            'u7' => 'U7 · born '.($year - 7).' or later',
            'u8' => 'U8 · born '.($year - 8),
            'u9' => 'U9 · born '.($year - 9),
            'u10' => 'U10 · born '.($year - 10),
            'u11' => 'U11 · born '.($year - 11),
            'u12' => 'U12 · born '.($year - 12),
            'u13' => 'U13 · born '.($year - 13),
            'u18' => 'U18 · born '.($year - 18).'–'.($year - 14),
            'unknown' => self::AGE_GROUPS['unknown'],
            'other' => 'Over 18 · born '.($year - 19).' or earlier',
        ];
    }

    public function build(string $ageGroup, string $gender, string $search, string $comparisonGroup = '', string $sort = 'grouped', ?int $perPage = null, string $ageScope = 'group'): array
    {
        $today = CarbonImmutable::today('Africa/Johannesburg');
        $year = $today->year;
        if (!in_array($ageScope, ['group', 'younger'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['age_scope' => 'Choose a supported age selection.']);
        }
        $includeYounger = $ageScope === 'younger' && in_array($ageGroup, ['u7', 'u8', 'u9', 'u10', 'u11', 'u12', 'u13', 'u18'], true);
        $ageScope = $includeYounger ? 'younger' : 'group';
        $ageScopeLabel = $includeYounger ? strtoupper($ageGroup).' and younger · born '.($year - (int) substr($ageGroup, 1)).' or later' : null;
        $query = DB::table('players')->select(['id', 'name', 'surname', 'dateOfBirth', 'gender']);
        $validBirth = fn ($q) => $q->where('dateOfBirth', '>=', '1000-01-01')->where('dateOfBirth', '<=', $today->toDateString());
        if ($ageGroup === 'unknown') {
            $query->where(fn ($q) => $q->whereNull('dateOfBirth')->orWhere('dateOfBirth', '<', '1000-01-01')->orWhere('dateOfBirth', '>', $today->toDateString()));
        } elseif ($ageGroup !== '') {
            $validBirth($query);
            if ($ageGroup === 'other') {
                $query->where('dateOfBirth', '<', ($year - 18).'-01-01');
            } else {
                [$min, $max] = match ($ageGroup) { 'u7' => [0, 7], 'u8' => [8, 8], 'u9' => [9, 9], 'u10' => [10, 10], 'u11' => [11, 11], 'u12' => [12, 12], 'u13' => [13, 13], 'u18' => [14, 18] };
                if ($includeYounger) $min = 0;
                $query->whereBetween('dateOfBirth', [($year - $max).'-01-01', ($year - $min).'-12-31']);
            }
        }
        $boys = ['1', 'male', 'boy', 'boys', 'm'];
        $girls = ['2', 'female', 'girl', 'girls', 'f'];
        if ($gender === 'unknown') {
            $query->where(fn ($q) => $q->whereNull('gender')->orWhereNotIn(DB::raw('LOWER(TRIM(gender))'), [...$boys, ...$girls]));
        } elseif ($gender !== '') {
            $query->whereIn(DB::raw('LOWER(TRIM(gender))'), $gender === 'boys' ? $boys : $girls);
        }
        $snapshot = app(PlayerAbilitySnapshotStore::class)->current();
        $unified = array_key_exists('all_age', $snapshot);
        // Explicit group pages always retain their within-group positions.
        $sort = $comparisonGroup === '' ? $sort : 'shared';
        $groupedOrder = $sort === 'grouped';
        $perPage = in_array($perPage, [10, 25, 50, 100, 500], true) ? $perPage : ($groupedOrder ? 10 : 25);
        $scoreOrder = $sort === 'score';
        $diagnosticOrder = in_array($sort, ['score', 'age'], true);
        $ageOrder = $sort === 'age' && !$snapshot['reason'];
        // Missing or invalidated snapshots still show one searchable directory.
        // Only available legacy snapshots retain their historical group layout.
        $combined = $comparisonGroup === '' && !$ageOrder && ($diagnosticOrder || $unified || $snapshot['reason']);
        $withinGroup = false;
        if ($comparisonGroup !== '') {
            $component = $snapshot['all_age']['components'][$comparisonGroup] ?? null;
            if ($snapshot['reason'] || !$component) {
                throw \Illuminate\Validation\ValidationException::withMessages(['comparison_group' => 'Choose an available saved comparison group.']);
            }
            $query->whereIn('id', $component['members']);
            $withinGroup = $comparisonGroup !== ($snapshot['all_age']['reference_component'] ?? null);
        }
        $connectionPlan = $unified ? app(AllAgeConnectivityService::class)->summarize($snapshot['all_age']) : null;
        $sharedCoverage = $unified ? ['eligible' => count($snapshot['all_age']['ratings'] ?? []),
            'connected' => count(array_filter($snapshot['all_age']['ratings'] ?? [], fn ($rating) => !empty($rating['comparable'])))] : null;
        $ratings = $snapshot['reason'] ? [] : ($unified
            ? array_map(fn ($rating) => [$rating], app(PlayerSharedAbilityService::class)->allAgeBadgeSnapshot())
            : app(PlayerSharedAbilityService::class)->badgeSnapshot());
        if ($withinGroup) {
            foreach (app(PlayerRatingGroupProjection::class)->project($snapshot, $comparisonGroup) as $id => $projection) {
                $ratings[$id] = [$projection];
            }
        }
        // A profile belongs here only when its calculated group confirms membership.
        // Invalidated snapshots and orphaned/stale badge rows fail closed.
        foreach ($ratings as $id => &$savedRatings) {
            $savedRatings = array_values(array_filter($savedRatings, function ($rating) use ($snapshot, $unified, $id) {
                $graph = $unified ? $snapshot['all_age'] : ($snapshot['cohorts'][$rating['cohort'] ?? ''] ?? []);
                $component = $graph['components'][$rating['component'] ?? ''] ?? [];
                return in_array((int) $id, $component['members'] ?? [], true)
                    && isset($graph['ratings'][$id])
                    && ($graph['ratings'][$id]['component'] ?? null) === ($rating['component'] ?? null);
            }));
            if (!$savedRatings) { unset($ratings[$id]); }
        }
        unset($savedRatings);
        $query->whereIn('id', array_keys($ratings));
        $headlines = [];
        foreach ($ratings as $id => &$saved) {
            // Match the no-context profile headline/name badge, not the highest historical score.
            usort($saved, fn ($a, $b) => strcmp($b['last_played'], $a['last_played']) ?: strcmp($a['cohort'], $b['cohort']));
            if ($saved && is_numeric($saved[0]['within_group_score'] ?? $saved[0]['score'] ?? null)) { $headlines[$id] = $saved[0]; }
        }
        unset($saved);
        // Disconnected estimates have no common offset. Keep them visible, but
        // score mode is an explicitly labelled diagnostic list, not that scale.
        $orderingHeadlines = ($combined || $groupedOrder || ($includeYounger && !$unified)) && !$diagnosticOrder
            ? array_filter($headlines, fn ($rating) => ($rating['comparable'] ?? false) === true)
            : $headlines;
        $scoreSql = 'NULL'; $scoreBindings = [];
        if ($orderingHeadlines) {
            $scoreSql = 'CASE id';
            foreach ($orderingHeadlines as $id => $rating) {
                $scoreSql .= ' WHEN ? THEN ?';
                $scoreBindings[] = (int) $id;
                $scoreBindings[] = (float) ($rating['within_group_score'] ?? $rating['score']);
            }
            $scoreSql .= ' ELSE NULL END';
        }
        $query->selectRaw('CAST(('.$scoreSql.') AS DECIMAL(30,15)) AS ordering_score', $scoreBindings);
        $stateSql = 'CASE id'; $stateBindings = [];
        foreach ($headlines as $id => $rating) {
            $stateSql .= ' WHEN ? THEN ?';
            $stateBindings[] = (int) $id;
            $stateBindings[] = isset($orderingHeadlines[$id]) ? 0 : 1;
        }
        $query->selectRaw(($headlines ? $stateSql.' ELSE 2 END' : '2').' AS ordering_state', $stateBindings);
        $componentSql = 'CASE id'; $componentBindings = [];
        foreach ($headlines as $id => $rating) {
            $componentSql .= ' WHEN ? THEN ?';
            $componentBindings[] = (int) $id; $componentBindings[] = $rating['component'];
        }
        $componentSql = $headlines ? $componentSql.' ELSE NULL END' : 'NULL';
        $query->selectRaw('('.$componentSql.') AS rating_component', $componentBindings);
        $ageSql = "CASE WHEN dateOfBirth IS NULL OR dateOfBirth < '1000-01-01' OR dateOfBirth > ? THEN ".self::AGE_ORDER['unknown']." WHEN YEAR(dateOfBirth) >= ? THEN ".self::AGE_ORDER['u7'];
        $ageBindings = [$today->toDateString(), $year - 7];
        foreach (range(8, 13) as $age) {
            $ageSql .= ' WHEN YEAR(dateOfBirth) = ? THEN '.self::AGE_ORDER['u'.$age];
            $ageBindings[] = $year - $age;
        }
        $query->selectRaw($ageSql.' WHEN YEAR(dateOfBirth) >= ? THEN '.self::AGE_ORDER['u18'].' ELSE '.self::AGE_ORDER['other'].' END AS age_order', [...$ageBindings, $year - 18]);
        $query->selectRaw("CASE WHEN LOWER(TRIM(gender)) IN ('1','male','boy','boys','m') THEN 0 WHEN LOWER(TRIM(gender)) IN ('2','female','girl','girls','f') THEN 1 ELSE 2 END AS gender_order");
        // SQL ranks the complete filtered groups before name search and pagination.
        // Only the requested page of profiles is materialised in PHP.
        $agePartition = $includeYounger ? '' : 'age_order, ';
        $partition = $groupedOrder ? 'PARTITION BY '.$agePartition.'gender_order, ordering_state ' : ($ageOrder ? 'PARTITION BY '.$agePartition.'ordering_state ' : ($combined ? 'PARTITION BY ordering_state ' : ($unified ? 'PARTITION BY rating_component ' : 'PARTITION BY '.$agePartition.'gender_order ')));
        $ranked = DB::query()->fromSub($query, 'profile_scores')->select('*')->selectRaw('ROW_NUMBER() OVER ('.$partition.'ORDER BY ordering_score IS NULL, ordering_score DESC, surname, name, id) AS position');
        $ordered = DB::query()->fromSub($ranked, 'ranked_profiles');
        foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            $ordered->where(fn ($q) => $q->where('name', 'like', '%'.addcslashes($token, '%_\\').'%')->orWhere('surname', 'like', '%'.addcslashes($token, '%_\\').'%'));
        }
        if ($groupedOrder) { $ordered->orderBy('ordering_state'); }
        elseif ($ageOrder) { if (!$includeYounger) $ordered->orderBy('age_order'); $ordered->orderBy('ordering_state'); }
        elseif ($combined) { $ordered->orderBy('ordering_state'); }
        elseif (!$unified) { if (!$includeYounger) $ordered->orderBy('age_order'); $ordered->orderBy('gender_order')->orderBy('ordering_state'); }
        else { $ordered->orderByRaw('rating_component IS NULL')->orderBy('rating_component'); }
        $ordered->orderBy('position');
        $mapPlayer = function ($player) use ($year, $today, $ratings, $headlines, $boys, $girls, $combined, $diagnosticOrder, $groupedOrder, $includeYounger, $unified) {
            $raw = (string) $player->dateOfBirth;
            try { $birth = CarbonImmutable::createFromFormat('!Y-m-d', $raw); } catch (\Throwable) { $birth = null; }
            $age = $birth && $birth->format('Y-m-d') === $raw && $birth->year >= 1000 && $birth->lessThanOrEqualTo($today) ? $year - $birth->year : null;
            $group = match (true) { $age === null => 'unknown', $age <= 7 => 'u7', $age <= 13 => 'u'.$age, $age <= 18 => 'u18', default => 'other' };
            $normalGender = strtolower(trim((string) $player->gender));
            $genderKey = in_array($normalGender, $boys, true) ? 'boys' : (in_array($normalGender, $girls, true) ? 'girls' : 'unknown');
            $saved = $ratings[$player->id] ?? [];
            return ['id' => $player->id, 'name' => trim($player->name.' '.$player->surname), 'birth_year' => $age === null ? null : $birth->year, 'age_group' => self::AGE_GROUPS[$group],
                'age_key' => $group, 'gender_key' => $genderKey, 'position' => (($includeYounger && !$unified && !$diagnosticOrder) || (($combined || $diagnosticOrder || $groupedOrder) && (int) $player->ordering_state !== 0)) ? null : (int) $player->position,
                'awaiting_calibration' => ($combined || $diagnosticOrder || $groupedOrder) && isset($headlines[$player->id]) && ($headlines[$player->id]['comparable'] ?? false) !== true,
                'gender' => self::GENDERS[$genderKey], 'ratings' => $saved, 'rating' => $headlines[$player->id] ?? null,
                'rating_reason' => $saved[0]['reason'] ?? null];
        };
        $sections = [];
        if ($groupedOrder) {
            $visible = collect(); $total = 0;
            foreach (self::AGE_ORDER as $key => $ageIndex) {
                if ($ageGroup !== '' && $ageGroup !== $key) continue;
                $columns = [];
                foreach (array_keys(self::GENDERS) as $genderIndex => $genderKey) {
                    if ($gender !== '' && $gender !== $genderKey) continue;
                    $columnQuery = (clone $ordered)->where('gender_order', $genderIndex);
                    if (!$includeYounger) $columnQuery->where('age_order', $ageIndex);
                    $column = $columnQuery->paginate($perPage, ['*'], $key.'_'.$genderKey.'_page')->withQueryString();
                    $column->through($mapPlayer);
                    $total += $column->total();
                    $visible = $visible->concat($column->items());
                    if ($genderKey !== 'unknown' || $column->total() > 0 || $gender === 'unknown') $columns[$genderKey] = $column;
                }
                if (array_sum(array_map(fn ($column) => $column->total(), $columns)) > 0 || $ageGroup !== '') {
                    $sections[$key] = ['label' => $ageScopeLabel ?? self::birthYearLabels($year)[$key], 'columns' => $columns];
                }
            }
            $players = new \Illuminate\Pagination\LengthAwarePaginator($visible, $total, max(1, $visible->count()));
        } else {
            $players = $ordered->paginate($perPage)->withQueryString();
            $players->through($mapPlayer);
        }
        $regions = app(PlayerRatingRegionAffiliations::class)->forPlayers($players->getCollection()->pluck('id')->all());
        $withRegions = fn ($player) => $player + ['region_affiliations' => $regions[$player['id']] ?? ['names' => [], 'additional' => 0]];
        $players->through($withRegions);
        foreach ($sections as &$section) {
            foreach ($section['columns'] as $column) $column->through($withRegions);
        }
        unset($section);
        return compact('ageScope', 'includeYounger', 'ageScopeLabel', 'perPage', 'sections', 'groupedOrder', 'players', 'snapshot', 'year', 'ageGroup', 'gender', 'search', 'unified', 'combined', 'sort', 'scoreOrder', 'diagnosticOrder', 'ageOrder', 'sharedCoverage', 'connectionPlan', 'comparisonGroup', 'withinGroup');
    }
}
