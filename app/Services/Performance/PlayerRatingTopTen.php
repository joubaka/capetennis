<?php

namespace App\Services\Performance;

use App\Domain\Ranking\Services\RankingTeamEligibilityService;
use App\Models\{Player, SeriesRanking, User};
use Carbon\CarbonImmutable;

/** Discovery projection: public series identities and comparable saved ratings only. */
class PlayerRatingTopTen
{
    public const PER_PAGE = 25;
    public const PAGE_SIZES = [25, 50, 100, 500];

    public function __construct(private PlayerRatingAccess $access, private PlayerAbilitySnapshotStore $snapshots, private RankingTeamEligibilityService $eligibility) {}

    public function build(?User $user, ?Player $selected = null, array $filters = []): array
    {
        $result = ['visible' => $this->access->allowsDiscovery($user), 'lists' => [], 'as_of' => null, 'stale' => false, 'reason' => null];
        $today = CarbonImmutable::today('Africa/Johannesburg');
        $selectedAge = $selected ? $this->ageKey($selected->dateOfBirth, $today) : '';
        $age = $filters['rating_age'] ?? $selectedAge;
        $gender = $filters['rating_gender'] ?? ($selected ? $this->genderKey($selected->gender) : '');
        $level = ($filters['rating_level'] ?? '') ?: 'overall';
        $requestedSize = $filters['rating_per_page'] ?? self::PER_PAGE;
        $perPage = (is_int($requestedSize) || is_string($requestedSize)) && in_array((string) $requestedSize, array_map('strval', self::PAGE_SIZES), true)
            ? (int) $requestedSize : self::PER_PAGE;
        // Existing Green/Yellow filter links now select the shared Overall rating.
        if ($level === 'standard') $level = 'overall';
        $ageOptions = PlayerRatingProfileDirectory::birthYearLabels($today->year);
        foreach (array_keys($ageOptions) as $key) {
            if (preg_match('/^u\d+$/', $key)) $ageOptions[$key] = strtoupper($key).' and younger · born '.($today->year - (int) substr($key, 1)).' or later';
        }
        $result += ['filters' => ['rating_age' => $age, 'rating_gender' => $gender, 'rating_level' => $level, 'rating_per_page' => $perPage],
            'age_options' => $ageOptions, 'summary' => [], 'selected_age' => $selectedAge];
        if (!$result['visible']) return $result;
        $snapshot = $this->snapshots->current();
        foreach (['red' => 'Red', 'orange' => 'Orange', 'overall' => 'Overall CTR'] as $key => $label) {
            $result['lists'][$key] = ['label' => $label, 'players' => []];
        }
        if (!array_key_exists('reason', $snapshot) || $snapshot['reason'] !== null) {
            $result['reason'] = 'Saved ratings are not available yet.';
            if ($selected) $result['lists'] = array_intersect_key($result['lists'], [$level => true]);
            return $result;
        }
        $result['as_of'] = $snapshot['snapshot_as_of'] ?? null;
        $result['stale'] = ($snapshot['snapshot_stale'] ?? false) === true;
        $ratings = [];
        foreach (array_keys($result['lists']) as $key) {
            $graph = $key === 'overall' ? ($snapshot['all_age'] ?? []) : ($snapshot['ball_ratings'][$key] ?? []);
            $reference = $graph['reference_component'] ?? null;
            $component = $graph['components'][$reference ?? ''] ?? [];
            if (!$reference || ($component['converged'] ?? false) !== true) continue;
            $members = array_fill_keys($component['members'] ?? [], true);
            foreach ($graph['ratings'] ?? [] as $id => $rating) {
                if (!is_array($rating) || !ctype_digit((string) $id) || (int) $id < 1 || (string) (int) $id !== (string) $id) continue;
                $score = $rating['score'] ?? null;
                if (!isset($members[$id]) || ($rating['component'] ?? null) !== $reference || ($rating['comparable'] ?? false) !== true
                    || !is_numeric($score) || !is_finite((float) $score) || $score < 0 || $score > 100) continue;
                $ratings[$key][(int) $id] = ['score' => round((float) $score, 1), 'confidence' => $rating['confidence_label'] ?? 'Low'];
            }
        }
        $ids = array_values(array_unique(array_merge(...array_values(array_map('array_keys', $ratings ?: [[]])))));
        if (count($ids) > 5000) return $this->limited($result);
        $public = [];
        foreach (array_chunk($ids, 200) as $chunk) {
            $rows = SeriesRanking::query()->with(['series:id,minimum_events_for_team_selection', 'player:id,name,surname,dateOfBirth,gender'])
                ->whereIn('player_id', $chunk)->where('status', 'published')->whereNotNull('run_id')
                ->whereHas('series', fn ($query) => $query->where('leaderboard_published', true))
                ->where('run_id', function ($query) {
                    $query->from('series_rankings as latest')->select('latest.run_id')
                        ->whereColumn('latest.series_id', 'series_rankings.series_id')->where('latest.status', 'published')->whereNotNull('latest.run_id')
                        ->orderByDesc('latest.published_at')->orderByDesc('latest.updated_at')->limit(1);
                })->limit(1001)->get();
            if ($rows->count() > 1000) return $this->limited($result);
            foreach ($rows as $row) {
                if (!$row->player || !$row->series || $this->eligibility->publiclyRanked(collect([$row]), $row->series)->isEmpty()) continue;
                $public[(int) $row->player_id] = ['name' => trim($row->player->name.' '.$row->player->surname),
                    'age' => $this->ageKey($row->player->dateOfBirth, $today),
                    'gender' => $this->genderKey($row->player->gender)];
            }
        }
        foreach ($ratings as $key => $members) {
            $players = [];
            foreach ($members as $id => $rating) {
                if (isset($public[$id])) $players[] = ['id' => $id, ...$public[$id], ...$rating];
            }
            usort($players, fn ($a, $b) => $b['score'] <=> $a['score'] ?: strnatcasecmp($a['name'], $b['name']) ?: $a['id'] <=> $b['id']);
            if ($selected && $key === 'overall') {
                $populations = ['age' => $selectedAge === 'unknown' ? [] : array_values(array_filter($players, fn ($p) => $this->matchesAgeScope($p['age'], $selectedAge))), 'overall' => $players];
                foreach ($populations as $scope => $population) {
                    $ranked = $this->positions($population);
                    $result['summary'][$scope] = ['player' => collect($ranked)->firstWhere('id', (int) $selected->id), 'total' => count($ranked)];
                }
            }
            if ($selected) $players = array_values(array_filter($players, fn ($p) => ($key !== 'overall' || $this->matchesAgeScope($p['age'], $age)) && ($gender === '' || $p['gender'] === $gender)));
            $ranked = $this->positions($players);
            if ($selected) {
                $total = count($ranked);
                $pages = max(1, (int) ceil($total / $perPage));
                $page = min($pages, max(1, (int) ($filters['rating_page'] ?? 1)));
                $result['lists'][$key]['players'] = array_slice($ranked, ($page - 1) * $perPage, $perPage);
                $result['lists'][$key]['pagination'] = ['page' => $page, 'pages' => $pages, 'total' => $total, 'per_page' => $perPage];
            } else {
                $result['lists'][$key]['players'] = array_slice($ranked, 0, 10);
            }
        }
        if ($selected) $result['lists'] = array_intersect_key($result['lists'], [$level => true]);
        return $result;
    }

    private function matchesAgeScope(string $playerAge, string $selectedAge): bool
    {
        if ($selectedAge === '') return true;
        // Only bounded junior groups include younger ages. Missing DOB and adult
        // buckets remain exact selections, as in the rating profile directory.
        if (!preg_match('/^u\d+$/', $selectedAge)) return $playerAge === $selectedAge;
        return preg_match('/^u\d+$/', $playerAge) === 1
            && (int) substr($playerAge, 1) <= (int) substr($selectedAge, 1);
    }

    private function genderKey(mixed $raw): string
    {
        $gender = strtolower(trim((string) $raw));
        return in_array($gender, ['1', 'male', 'boy', 'boys', 'm'], true) ? 'boys'
            : (in_array($gender, ['2', 'female', 'girl', 'girls', 'f'], true) ? 'girls' : 'unknown');
    }

    private function positions(array $players): array
    {
        $position = 0; $previous = null;
        foreach ($players as $index => &$player) {
            if ($previous !== $player['score']) $position = $index + 1;
            $previous = $player['score'];
            $player['position'] = $position;
        }
        return $players;
    }

    private function ageKey(?string $raw, CarbonImmutable $today): string
    {
        try { $birth = CarbonImmutable::createFromFormat('!Y-m-d', $raw ?? ''); } catch (\Throwable) { return 'unknown'; }
        if (!$birth || $birth->format('Y-m-d') !== $raw || $birth->year < 1000 || $birth->greaterThan($today)) return 'unknown';
        $age = $today->year - $birth->year;
        return match (true) { $age <= 7 => 'u7', $age <= 13 => 'u'.$age, $age <= 18 => 'u18', default => 'other' };
    }

    private function limited(array $result): array
    {
        $result['reason'] = 'The published player selection exceeds the safe display limit. Ratings are temporarily unavailable.';
        foreach ($result['lists'] as &$list) $list['players'] = [];
        if ($result['selected_age'] !== '') $result['lists'] = array_intersect_key($result['lists'], [$result['filters']['rating_level'] => true]);
        return $result;
    }
}
