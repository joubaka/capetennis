<?php

namespace App\Services\Performance;

use App\Models\{CategoryEventRegistration, CategoryResult, Player, TeamFixture, User};
use App\Services\TeamFixtureLineupPresenter;
use App\Support\EventTypePresentation;
use Carbon\CarbonImmutable;

/** Discovery projection: public event participants with comparable saved ratings. */
class PlayerRatingTopTen
{
    public const PER_PAGE = 500;
    public const PAGE_SIZES = [25, 50, 100, 500];

    public function __construct(private PlayerRatingAccess $access, private PlayerAbilitySnapshotStore $snapshots) {}

    public function build(?User $user, ?Player $selected = null, array $filters = []): array
    {
        return $this->project($user, $selected, $filters);
    }

    /** Only call with identities resolved from the authorised player's published fixtures or saved match record. */
    public function forMatchPlayers(User $user, Player $selected, array $playerIds): array
    {
        $ids = array_slice(array_values(array_unique(array_map('intval', $playerIds))), 0, 100);
        if (!$ids) return [];
        return $this->project($user, $selected, [], $ids)['match_players'] ?? [];
    }

    private function project(?User $user, ?Player $selected, array $filters, ?array $matchIds = null): array
    {
        $result = ['visible' => $this->access->allowsDiscovery($user), 'lists' => [], 'as_of' => null, 'stale' => false, 'reason' => null];
        $result['can_browse'] = $user?->hasRole('super-user') === true;
        if (!$result['can_browse'] && (!$selected || !$user || !$this->access->allows($user, $selected))) $result['visible'] = false;
        $today = CarbonImmutable::today('Africa/Johannesburg');
        $selectedAge = $selected ? $this->ageKey($selected->dateOfBirth, $today) : '';
        $age = $filters['rating_age'] ?? $selectedAge;
        $gender = $filters['rating_gender'] ?? ($selected ? $this->genderKey($selected->gender) : '');
        $level = ($filters['rating_level'] ?? '') ?: 'overall';
        $requestedSize = $filters['rating_per_page'] ?? self::PER_PAGE;
        $perPage = (is_int($requestedSize) || is_string($requestedSize)) && in_array((string) $requestedSize, array_map('strval', self::PAGE_SIZES), true)
            ? (int) $requestedSize : self::PER_PAGE;
        if (!$result['can_browse']) {
            // A member's discovery scope comes from their authorised player,
            // never from submitted list filters or pagination parameters.
            $age = $selectedAge;
            $gender = $selected ? $this->genderKey($selected->gender) : 'unknown';
            $level = 'overall';
            $perPage = 10;
        }
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
            if ((!$result['can_browse'] || $matchIds !== null) && $key !== 'overall') continue;
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
                if ($matchIds !== null && !in_array((int) $id, $matchIds, true)) continue;
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
        try { $participants = $this->publicParticipants($ids); }
        catch (\OverflowException) { return $this->limited($result); }
        foreach ($participants as $player) {
            $public[(int) $player->id] = ['name' => trim($player->full_name),
                'age' => $this->ageKey($player->dateOfBirth, $today),
                'gender' => $this->genderKey($player->gender)];
        }
        foreach ($ratings as $key => $members) {
            $players = [];
            foreach ($members as $id => $rating) {
                if (isset($public[$id])) $players[] = ['id' => $id, ...$public[$id], ...$rating];
            }
            usort($players, fn ($a, $b) => $b['score'] <=> $a['score'] ?: strnatcasecmp($a['name'], $b['name']) ?: $a['id'] <=> $b['id']);
            if ($matchIds !== null) {
                $result['match_players'] = array_column(array_map(fn ($p) => array_intersect_key($p, array_flip(['id', 'name', 'score', 'confidence'])), $players), null, 'id');
                return $result;
            }
            if (!$result['can_browse']) {
                $population = $selectedAge === 'unknown' ? [] : array_values(array_filter($players,
                    fn ($p) => $this->matchesAgeScope($p['age'], $selectedAge) && $p['gender'] === $gender));
                $ranked = $this->positions($population);
                $result['summary']['age'] = ['player' => collect($ranked)->firstWhere('id', (int) $selected->id), 'total' => count($ranked)];
                $result['lists'][$key]['players'] = array_slice($ranked, 0, 10);
                continue;
            }
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

    private function publicParticipants(array $ids): iterable
    {
        if (!$ids) return [];

        $visibleEvent = fn ($event) => $event->visibleTo(null);
        $publishedDraw = fn ($draw) => $draw->where('published', true)->whereHas('event', $visibleEvent)
            ->whereHas('event.eventCategories', fn ($field) => $field
                ->where(fn ($category) => $category->whereNull('draws.category_event_id')->orWhereColumn('category_events.id', 'draws.category_event_id'))
                ->whereHas('categoryEventRegistrations', fn ($membership) => $membership->whereColumn('registration_id', 'registrations.id')));
        $publicResults = CategoryResult::query()->select('registration_id')
            ->whereHas('event', fn ($event) => $event->visibleTo(null)->where('results_published', true)
                ->whereHas('eventCategories', fn ($field) => $field->whereColumn('category_events.category_id', 'category_results.category_id')
                    ->whereHas('categoryEventRegistrations', fn ($membership) => $membership->whereColumn('registration_id', 'category_results.registration_id'))));
        $columns = ['id', 'name', 'surname', 'dateOfBirth', 'gender'];

        $individuals = Player::query()->whereIntegerInRaw('id', $ids)
            ->whereHas('registrations', fn ($registrations) => $registrations->where(fn ($participation) => $participation
                ->whereIn('registrations.id', $publicResults)
                ->orWhereHas('fixturesAsOne.draw', $publishedDraw)
                ->orWhereHas('fixturesAsTwo.draw', $publishedDraw)))
            ->get($columns)->keyBy('id');

        $remaining = array_fill_keys(array_diff($ids, $individuals->keys()->all()), true);
        if (!$remaining) return $individuals;

        // The individual event's public Players list shows only the first member of each active paid registration.
        $entries = CategoryEventRegistration::query()->activeAndPaid()->whereNull('withdrawn_at')
            ->whereHas('categoryEvent.event', $visibleEvent)
            ->whereHas('players', fn ($players) => $players->whereIntegerInRaw('players.id', array_keys($remaining)))
            ->with(['categoryEvent.event.eventTypeModel', 'registration.players'])->limit(5001)->get();
        if ($entries->count() > 5000) throw new \OverflowException('Public entry comparison scope exceeded.');
        foreach ($entries as $entry) {
            if (EventTypePresentation::template($entry->categoryEvent->event) !== 'individual') continue;
            $player = $entry->registration?->players->first();
            if (!$player || !isset($remaining[$player->id])) continue;
            $individuals->put($player->id, $player);
            unset($remaining[$player->id]);
        }
        if (!$remaining) return $individuals;

        // The public presenter verifies roster-side identity and does not expose imported profile links.
        $teams = TeamFixture::query()->publicDrawFixtures()->publishedTeamTies()->whereHas('draw.event', $visibleEvent)
            ->with(['draw', 'teamTie', 'fixturePlayers'])
            ->whereHas('fixturePlayers', fn ($slots) => $slots->whereIntegerInRaw('team1_id', array_keys($remaining))
                ->orWhereIntegerInRaw('team2_id', array_keys($remaining)));
        $confirmed = [];
        $scanned = 0;
        $teams->limit(5001)->chunkById(100, function ($fixtures) use (&$remaining, &$confirmed, &$scanned) {
            $scanned += $fixtures->count();
            if ($scanned > 5000) throw new \OverflowException('Public team comparison scope exceeded.');
            $presenter = app(TeamFixtureLineupPresenter::class);
            $fixtures = $presenter->eligiblePublicLineups($fixtures);
            if ($fixtures->isEmpty()) return true;
            $presenter->prepare($fixtures, publicDraw: true, includeProfileIds: true);
            foreach ($fixtures as $fixture) {
                foreach (['home', 'away'] as $side) {
                    foreach ($fixture->lineup_display[$side]['players'] as $participant) {
                        $id = $participant['player_id'];
                        if (!isset($remaining[$id])) continue;
                        $confirmed[] = $id;
                        unset($remaining[$id]);
                    }
                }
            }
            return $remaining !== [];
        });

        return $individuals->union(Player::query()->whereIntegerInRaw('id', $confirmed)->get($columns)->keyBy('id'));
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
