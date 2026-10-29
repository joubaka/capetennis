<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Current profile filters do not change the scope of saved rating evidence. */
class PlayerRatingProfileDirectory
{
    public const AGE_GROUPS = ['u10' => 'U10 (10 and younger)', 'u11' => 'U11 (11)', 'u12' => 'U12 (12)', 'u13' => 'U13 (13)', 'u18' => 'U18 (14–18)', 'unknown' => 'Missing / invalid birth date', 'other' => 'Over 18'];
    public const GENDERS = ['boys' => 'Boys', 'girls' => 'Girls', 'unknown' => 'Missing / unrecognised gender'];

    public function build(string $ageGroup, string $gender, string $search, string $comparisonGroup = ''): array
    {
        $today = CarbonImmutable::today('Africa/Johannesburg');
        $year = $today->year;
        $query = DB::table('players')->select(['id', 'name', 'surname', 'dateOfBirth', 'gender']);
        $validBirth = fn ($q) => $q->where('dateOfBirth', '>=', '1000-01-01')->where('dateOfBirth', '<=', $today->toDateString());
        if ($ageGroup === 'unknown') {
            $query->where(fn ($q) => $q->whereNull('dateOfBirth')->orWhere('dateOfBirth', '<', '1000-01-01')->orWhere('dateOfBirth', '>', $today->toDateString()));
        } elseif ($ageGroup !== '') {
            $validBirth($query);
            if ($ageGroup === 'other') {
                $query->where('dateOfBirth', '<', ($year - 18).'-01-01');
            } else {
                [$min, $max] = match ($ageGroup) { 'u10' => [0, 10], 'u11' => [11, 11], 'u12' => [12, 12], 'u13' => [13, 13], 'u18' => [14, 18] };
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
            'connected' => count(array_filter($snapshot['all_age']['ratings'] ?? [], fn ($rating) => is_numeric($rating['score'] ?? null)))] : null;
        $ratings = $snapshot['reason'] ? [] : ($unified
            ? array_map(fn ($rating) => [$rating], app(PlayerSharedAbilityService::class)->allAgeBadgeSnapshot())
            : app(PlayerSharedAbilityService::class)->badgeSnapshot());
        if ($withinGroup) {
            foreach (app(PlayerRatingGroupProjection::class)->project($snapshot, $comparisonGroup) as $id => $projection) {
                $ratings[$id] = [$projection];
            }
        }
        $headlines = [];
        foreach ($ratings as $id => &$saved) {
            // Match the no-context profile headline/name badge, not the highest historical score.
            usort($saved, fn ($a, $b) => strcmp($b['last_played'], $a['last_played']) ?: strcmp($a['cohort'], $b['cohort']));
            if ($saved && is_numeric($saved[0]['within_group_score'] ?? $saved[0]['score'] ?? null)) { $headlines[$id] = $saved[0]; }
        }
        unset($saved);
        $scoreSql = 'NULL'; $scoreBindings = [];
        if ($headlines) {
            $scoreSql = 'CASE id';
            foreach ($headlines as $id => $rating) {
                $scoreSql .= ' WHEN ? THEN ?';
                $scoreBindings[] = (int) $id;
                $scoreBindings[] = (float) ($rating['within_group_score'] ?? $rating['score']);
            }
            $scoreSql .= ' ELSE NULL END';
        }
        $query->selectRaw('CAST(('.$scoreSql.') AS DECIMAL(30,15)) AS ordering_score', $scoreBindings);
        $query->selectRaw("CASE WHEN dateOfBirth IS NULL OR dateOfBirth < '1000-01-01' OR dateOfBirth > ? THEN 5 WHEN YEAR(dateOfBirth) >= ? THEN 0 WHEN YEAR(dateOfBirth) = ? THEN 1 WHEN YEAR(dateOfBirth) = ? THEN 2 WHEN YEAR(dateOfBirth) = ? THEN 3 WHEN YEAR(dateOfBirth) >= ? THEN 4 ELSE 6 END AS age_order",
            [$today->toDateString(), $year - 10, $year - 11, $year - 12, $year - 13, $year - 18]);
        $query->selectRaw("CASE WHEN LOWER(TRIM(gender)) IN ('1','male','boy','boys','m') THEN 0 WHEN LOWER(TRIM(gender)) IN ('2','female','girl','girls','f') THEN 1 ELSE 2 END AS gender_order");
        // SQL ranks the complete filtered groups before name search and pagination.
        // Only the requested page of profiles is materialised in PHP.
        $partition = $unified ? '' : 'PARTITION BY age_order, gender_order ';
        $ranked = DB::query()->fromSub($query, 'profile_scores')->select('*')->selectRaw('ROW_NUMBER() OVER ('.$partition.'ORDER BY ordering_score IS NULL, ordering_score DESC, surname, name, id) AS position');
        $ordered = DB::query()->fromSub($ranked, 'ranked_profiles');
        foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            $ordered->where(fn ($q) => $q->where('name', 'like', '%'.addcslashes($token, '%_\\').'%')->orWhere('surname', 'like', '%'.addcslashes($token, '%_\\').'%'));
        }
        if (!$unified) { $ordered->orderBy('age_order')->orderBy('gender_order'); }
        $players = $ordered->orderBy('position')->paginate(25)->withQueryString();
        $players->through(function ($player) use ($year, $today, $ratings, $headlines, $boys, $girls) {
            $raw = (string) $player->dateOfBirth;
            try { $birth = CarbonImmutable::createFromFormat('!Y-m-d', $raw); } catch (\Throwable) { $birth = null; }
            $age = $birth && $birth->format('Y-m-d') === $raw && $birth->year >= 1000 && $birth->lessThanOrEqualTo($today) ? $year - $birth->year : null;
            $group = match (true) { $age === null => 'unknown', $age <= 10 => 'u10', $age <= 13 => 'u'.$age, $age <= 18 => 'u18', default => 'other' };
            $normalGender = strtolower(trim((string) $player->gender));
            $genderKey = in_array($normalGender, $boys, true) ? 'boys' : (in_array($normalGender, $girls, true) ? 'girls' : 'unknown');
            $saved = $ratings[$player->id] ?? [];
            return ['id' => $player->id, 'name' => trim($player->name.' '.$player->surname), 'age_group' => self::AGE_GROUPS[$group],
                'age_key' => $group, 'gender_key' => $genderKey, 'position' => (int) $player->position,
                'gender' => self::GENDERS[$genderKey], 'ratings' => $saved, 'rating' => $headlines[$player->id] ?? null,
                'rating_reason' => $saved[0]['reason'] ?? null];
        });
        return compact('players', 'snapshot', 'year', 'ageGroup', 'gender', 'search', 'unified', 'sharedCoverage', 'connectionPlan', 'comparisonGroup', 'withinGroup');
    }
}
