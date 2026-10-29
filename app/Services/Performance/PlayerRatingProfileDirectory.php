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
        foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.addcslashes($token, '%_\\').'%')->orWhere('surname', 'like', '%'.addcslashes($token, '%_\\').'%'));
        }
        $players = $query->orderBy('surname')->orderBy('name')->orderBy('id')->paginate(25)->withQueryString();
        $snapshot = app(PlayerAbilitySnapshotStore::class)->current();
        $ratings = $snapshot['reason'] ? [] : app(PlayerSharedAbilityService::class)->badgeSnapshot();
        $players->through(function ($player) use ($year, $today, $ratings, $boys, $girls) {
            $raw = (string) $player->dateOfBirth;
            try { $birth = CarbonImmutable::createFromFormat('!Y-m-d', $raw); } catch (\Throwable) { $birth = null; }
            $age = $birth && $birth->format('Y-m-d') === $raw && $birth->year >= 1000 && $birth->lessThanOrEqualTo($today) ? $year - $birth->year : null;
            $group = match (true) { $age === null => 'unknown', $age <= 10 => 'u10', $age <= 13 => 'u'.$age, $age <= 18 => 'u18', default => 'other' };
            $normalGender = strtolower(trim((string) $player->gender));
            return ['id' => $player->id, 'name' => trim($player->name.' '.$player->surname), 'age_group' => self::AGE_GROUPS[$group],
                'gender' => self::GENDERS[in_array($normalGender, $boys, true) ? 'boys' : (in_array($normalGender, $girls, true) ? 'girls' : 'unknown')],
                'ratings' => $ratings[$player->id] ?? []];
        });

        return compact('players', 'snapshot', 'year', 'ageGroup', 'gender', 'search');
    }
}
