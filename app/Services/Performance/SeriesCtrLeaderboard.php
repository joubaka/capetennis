<?php

namespace App\Services\Performance;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Read-only CTR discovery among the already published series participants. */
class SeriesCtrLeaderboard
{
    public function __construct(private PlayerRatingAccess $access, private PlayerAbilitySnapshotStore $snapshots) {}

    public function build(?User $user, Collection $players): array
    {
        $today = CarbonImmutable::today('Africa/Johannesburg');
        $result = ['visible' => $this->access->allowsDiscovery($user), 'year' => $today->year, 'groups' => [], 'ratings' => []];
        if (!$result['visible']) return $result;

        $snapshot = $this->snapshots->current();
        if (!array_key_exists('reason', $snapshot) || $snapshot['reason'] !== null) return $result;
        $graph = $snapshot['all_age'] ?? [];
        $reference = $graph['reference_component'] ?? null;
        if (!$reference) return $result;
        $component = $graph['components'][$reference] ?? [];
        if (!($component['converged'] ?? false)) return $result;
        $members = array_fill_keys($component['members'] ?? [], true);
        $labels = PlayerRatingProfileDirectory::birthYearLabels($today->year);
        foreach ($players->filter()->unique('id') as $player) {
            $rating = $graph['ratings'][$player->id] ?? null;
            if (!$rating || !isset($members[$player->id]) || ($rating['component'] ?? null) !== $reference
                || ($rating['comparable'] ?? false) !== true || !is_numeric($rating['score'] ?? null)
                || !is_finite((float) $rating['score'])) continue;

            $result['ratings'][$player->id] = ['score' => (float) $rating['score'], 'confidence' => $rating['confidence_label'] ?? 'Low'];
            $raw = (string) $player->dateOfBirth;
            try { $birth = CarbonImmutable::createFromFormat('!Y-m-d', $raw); } catch (\Throwable) { $birth = null; }
            if (!$birth || $birth->format('Y-m-d') !== $raw || $birth->year < 1000 || $birth->greaterThan($today)) continue;
            $age = $today->year - $birth->year;
            $ageKey = match (true) { $age <= 10 => 'u10', $age <= 13 => 'u'.$age, $age <= 18 => 'u18', default => 'other' };
            $gender = strtolower(trim((string) $player->gender));
            $genderKey = in_array($gender, ['1', 'male', 'boy', 'boys', 'm'], true) ? 'boys'
                : (in_array($gender, ['2', 'female', 'girl', 'girls', 'f'], true) ? 'girls' : null);
            if (!$genderKey) continue;
            $key = $ageKey.'|'.$genderKey;
            $result['groups'][$key] ??= ['label' => $labels[$ageKey].' · '.PlayerRatingProfileDirectory::GENDERS[$genderKey], 'players' => []];
            $result['groups'][$key]['players'][] = ['id' => $player->id, 'name' => trim($player->name.' '.$player->surname),
                'surname' => $player->surname, 'first_name' => $player->name, ...$result['ratings'][$player->id]];
        }
        $ordered = [];
        foreach (['u10', 'u11', 'u12', 'u13', 'u18', 'other'] as $ageKey) {
            foreach (['boys', 'girls'] as $genderKey) {
                $key = $ageKey.'|'.$genderKey;
                if (!isset($result['groups'][$key])) continue;
                $group = $result['groups'][$key];
                usort($group['players'], fn ($a, $b) => $b['score'] <=> $a['score']
                    ?: strcasecmp($a['surname'], $b['surname']) ?: strcasecmp($a['first_name'], $b['first_name']) ?: $a['id'] <=> $b['id']);
                $group['players'] = array_slice($group['players'], 0, 10);
                $ordered[$key] = $group;
            }
        }
        $result['groups'] = $ordered;
        return $result;
    }
}
