<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Current profile filters do not change the scope of saved rating evidence. */
class PlayerRatingProfileDirectory
{
    public const AGE_GROUPS = ['u10' => 'U10 (10 and younger)', 'u11' => 'U11 (11)', 'u12' => 'U12 (12)', 'u13' => 'U13 (13)', 'u18' => 'U18 (14–18)', 'unknown' => 'Missing / invalid birth date', 'other' => 'Over 18'];
    public const GENDERS = ['boys' => 'Boys', 'girls' => 'Girls', 'unknown' => 'Missing / unrecognised gender'];

    public function build(string $ageGroup, string $gender, string $search): array
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
        $ratings = $snapshot['reason'] ? [] : app(PlayerSharedAbilityService::class)->badgeSnapshot();
        $headlines = [];
        foreach ($ratings as $id => &$saved) {
            // Match the no-context profile headline/name badge, not the highest historical score.
            usort($saved, fn ($a, $b) => strcmp($b['last_played'], $a['last_played']) ?: strcmp($a['cohort'], $b['cohort']));
            if ($saved) { $headlines[$id] = $saved[0]; }
        }
        unset($saved);
        $scoreSql = 'NULL'; $scoreBindings = [];
        if ($headlines) {
            $scoreSql = 'CASE id';
            foreach ($headlines as $id => $rating) {
                $scoreSql .= ' WHEN ? THEN ?';
                $scoreBindings[] = (int) $id;
                $scoreBindings[] = (float) $rating['score'];
            }
            $scoreSql .= ' ELSE NULL END';
        }
        $query->selectRaw('CAST(('.$scoreSql.') AS DECIMAL(30,15)) AS ordering_score', $scoreBindings);
        $query->selectRaw("CASE WHEN dateOfBirth IS NULL OR dateOfBirth < '1000-01-01' OR dateOfBirth > ? THEN 5 WHEN YEAR(dateOfBirth) >= ? THEN 0 WHEN YEAR(dateOfBirth) = ? THEN 1 WHEN YEAR(dateOfBirth) = ? THEN 2 WHEN YEAR(dateOfBirth) = ? THEN 3 WHEN YEAR(dateOfBirth) >= ? THEN 4 ELSE 6 END AS age_order",
            [$today->toDateString(), $year - 10, $year - 11, $year - 12, $year - 13, $year - 18]);
        $query->selectRaw("CASE WHEN LOWER(TRIM(gender)) IN ('1','male','boy','boys','m') THEN 0 WHEN LOWER(TRIM(gender)) IN ('2','female','girl','girls','f') THEN 1 ELSE 2 END AS gender_order");
        // SQL ranks the complete filtered groups before name search and pagination.
        // Only the requested page of profiles is materialised in PHP.
        $ranked = DB::query()->fromSub($query, 'profile_scores')->select('*')->selectRaw('ROW_NUMBER() OVER (PARTITION BY age_order, gender_order ORDER BY ordering_score IS NULL, ordering_score DESC, surname, name, id) AS position');
        $ordered = DB::query()->fromSub($ranked, 'ranked_profiles');
        foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            $ordered->where(fn ($q) => $q->where('name', 'like', '%'.addcslashes($token, '%_\\').'%')->orWhere('surname', 'like', '%'.addcslashes($token, '%_\\').'%'));
        }
        $players = $ordered->orderBy('age_order')->orderBy('gender_order')->orderBy('position')->paginate(25)->withQueryString();
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
                'gender' => self::GENDERS[$genderKey], 'ratings' => $saved, 'rating' => $headlines[$player->id] ?? null];
        });
        return compact('players', 'snapshot', 'year', 'ageGroup', 'gender', 'search');
    }
}
