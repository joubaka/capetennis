<?php

namespace App\Domain\Ranking\Services;

use App\Models\CategoryResult;
use App\Models\Series;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RankingListDetailService
{
    public function __construct(
        private readonly RankingHeadToHeadEligibilityService $headToHeadEligibility,
    ) {}

    /**
     * Build event-result details for every score shown in an active ranking run.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function scoreDetails(Series $series, Collection $rankings): array
    {
        $events = $series->events->keyBy('id');
        $categoryEvents = $series->events
            ->flatMap(fn ($event) => $event->categoryEvents)
            ->keyBy('id');

        $playerIds = $rankings->pluck('player_id')->filter()->unique()->values();
        $eventIds = $events->keys();

        $actualPositions = CategoryResult::query()
            ->join('player_registrations as ranking_pr', 'ranking_pr.registration_id', '=', 'category_results.registration_id')
            ->join('categories as result_categories', 'result_categories.id', '=', 'category_results.category_id')
            ->whereIn('category_results.event_id', $eventIds)
            ->whereIn('ranking_pr.player_id', $playerIds)
            ->get([
                'category_results.event_id',
                'ranking_pr.player_id',
                'category_results.position',
                'result_categories.name as category_name',
            ])
            ->keyBy(fn ($result) => $this->resultKey(
                (int) $result->event_id,
                (int) $result->player_id,
                (string) $result->category_name
            ));

        return $rankings->mapWithKeys(function ($ranking) use ($events, $categoryEvents, $actualPositions) {
            $categoryName = (string) ($ranking->category?->name ?? '');
            $meta = is_array($ranking->meta_json) ? $ranking->meta_json : [];
            $legs = $this->rankingLegs($meta);

            $details = collect($legs)->map(function (array $leg) use (
                $ranking,
                $categoryName,
                $events,
                $categoryEvents,
                $actualPositions
            ) {
                $categoryEvent = ! empty($leg['category_event_id'])
                    ? $categoryEvents->get((int) $leg['category_event_id'])
                    : null;
                $event = $categoryEvent
                    ? $events->get((int) $categoryEvent->event_id)
                    : $events->get((int) ($leg['event_id'] ?? 0));
                $isSynthetic = (bool) ($leg['synthetic'] ?? $leg['is_auto'] ?? false);
                $rankingPosition = isset($leg['position']) ? (int) $leg['position'] : null;
                $result = $event
                    ? $actualPositions->get($this->resultKey((int) $event->id, (int) $ranking->player_id, $categoryName))
                    : null;

                return [
                    'points' => (int) ($leg['points'] ?? 0),
                    'counted' => ($leg['status'] ?? null) !== 'dropped' && ($leg['colour'] ?? null) !== 'red',
                    'synthetic' => $isSynthetic,
                    'ranking_position' => $rankingPosition,
                    'actual_position' => $isSynthetic ? null : ($result ? (int) $result->position : null),
                    'event' => $event,
                    'category_event_id' => $categoryEvent?->id,
                ];
            })->values()->all();

            return [$ranking->id => $details];
        })->all();
    }

    /**
     * Explain recorded matches between players still tied after the third score.
     *
     * @return array<string, array<string, mixed>>
     */
    public function headToHeadAdvisories(Series $series, Collection $rankings): array
    {
        $totalTieGroups = $rankings
            ->groupBy(fn ($ranking) => $this->tieKey((int) $ranking->category_id, (int) $ranking->total_points))
            ->filter(fn (Collection $group) => $group->count() > 1);

        $tieGroups = collect();
        foreach ($totalTieGroups as $key => $group) {
            foreach ($group->groupBy(fn ($ranking) => $this->rankingThirdScore($ranking)) as $thirdScore => $thirdScoreGroup) {
                if ($thirdScoreGroup->count() > 1) {
                    $tieGroups->put($key.':'.$thirdScore, $thirdScoreGroup);
                }
            }
        }

        if ($tieGroups->isEmpty()) {
            return [];
        }

        $linkedCategoryEvents = DB::table('ranking_list_category_events')
            ->whereIn('ranking_list_id', $tieGroups->flatten(1)->pluck('ranking_list_id')->filter()->unique())
            ->get(['ranking_list_id', 'category_event_id'])
            ->groupBy('ranking_list_id')
            ->map(fn (Collection $links) => $links->pluck('category_event_id')->map(fn ($id) => (int) $id));

        $resultEvents = $series->events
            ->filter(fn ($event) => (bool) $event->results_published)
            ->sortBy(fn ($event) => sprintf('%s-%010d', optional($event->start_date)->format('Y-m-d') ?? '', $event->id))
            ->values();
        $resultEventIds = $resultEvents->pluck('id');

        if ($resultEventIds->isEmpty()) {
            return [];
        }

        $tiedPlayerIds = $tieGroups->flatten(1)->pluck('player_id')->unique()->values();
        $fixtureQuery = DB::table('fixtures as ranking_fixtures')
            ->join('draws as ranking_draws', 'ranking_draws.id', '=', 'ranking_fixtures.draw_id')
            ->leftJoin('draw_settings as ranking_draw_settings', 'ranking_draw_settings.draw_id', '=', 'ranking_draws.id')
            ->join('events as ranking_events', 'ranking_events.id', '=', 'ranking_draws.event_id')
            ->join('player_registrations as ranking_pr1', 'ranking_pr1.registration_id', '=', 'ranking_fixtures.registration1_id')
            ->join('player_registrations as ranking_pr2', 'ranking_pr2.registration_id', '=', 'ranking_fixtures.registration2_id')
            ->leftJoin('category_events as ranking_ce', 'ranking_ce.id', '=', 'ranking_draws.category_event_id')
            ->leftJoin('categories as ranking_categories', 'ranking_categories.id', '=', 'ranking_ce.category_id')
            ->whereIn('ranking_events.id', $resultEventIds)
            ->whereIn('ranking_pr1.player_id', $tiedPlayerIds)
            ->whereIn('ranking_pr2.player_id', $tiedPlayerIds);
        $fixtures = $this->headToHeadEligibility
            ->applyPhaseScope($fixtureQuery)
            ->orderByDesc('ranking_events.start_date')
            ->orderByDesc('ranking_fixtures.id')
            ->get([
                'ranking_fixtures.id',
                'ranking_fixtures.registration1_id',
                'ranking_fixtures.registration2_id',
                'ranking_fixtures.winner_registration',
                'ranking_fixtures.draw_group_id',
                'ranking_draw_settings.workflow',
                'ranking_pr1.player_id as player1_id',
                'ranking_pr2.player_id as player2_id',
                'ranking_events.id as event_id',
                'ranking_events.name as event_name',
                'ranking_draws.category_event_id',
                'ranking_categories.name as category_name',
            ]);

        $qualifyingFullSets = $this->headToHeadEligibility->qualifyingFullSets($fixtures->pluck('id'));
        $fixtures = $fixtures->filter(fn ($fixture) => $qualifyingFullSets->has($fixture->id));

        if ($fixtures->isEmpty()) {
            return [];
        }

        $registrationIds = $fixtures
            ->flatMap(fn ($fixture) => [$fixture->registration1_id, $fixture->registration2_id])
            ->filter()
            ->unique();
        $registrationCategories = DB::table('category_event_registrations as ranking_memberships')
            ->join('category_events as ranking_member_ce', 'ranking_member_ce.id', '=', 'ranking_memberships.category_event_id')
            ->join('categories as ranking_member_categories', 'ranking_member_categories.id', '=', 'ranking_member_ce.category_id')
            ->whereIn('ranking_memberships.registration_id', $registrationIds)
            ->get([
                'ranking_memberships.registration_id',
                'ranking_member_ce.event_id',
                'ranking_member_ce.id as category_event_id',
                'ranking_member_categories.name as category_name',
            ])
            ->groupBy(fn ($membership) => $membership->event_id.':'.$membership->registration_id);

        $fixtureResults = DB::table('fixture_results')
            ->whereIn('fixture_id', $fixtures->pluck('id'))
            ->orderBy('set_nr')
            ->orderBy('id')
            ->get()
            ->groupBy('fixture_id');
        $playerNames = $rankings->mapWithKeys(fn ($ranking) => [
            (int) $ranking->player_id => $ranking->player?->full_name
                ?? $ranking->player?->name
                ?? 'Unknown Player',
        ]);

        $advisories = [];

        foreach ($tieGroups as $key => $group) {
            $categoryName = (string) ($group->first()?->category?->name ?? '');
            $eligibleCategoryEventIds = $linkedCategoryEvents->get((int) $group->first()->ranking_list_id, collect());
            $advisoryKey = $this->tieKey((int) $group->first()->category_id, (int) $group->first()->total_points);
            $groupPlayerIds = $group->pluck('player_id')->map(fn ($id) => (int) $id);
            $matches = [];
            $seenPairs = [];

            foreach ($fixtures as $fixture) {
                $player1Id = (int) $fixture->player1_id;
                $player2Id = (int) $fixture->player2_id;
                $fixtureCategoryName = (string) $fixture->category_name;
                $fixtureCategoryEventId = $fixture->category_event_id ? (int) $fixture->category_event_id : null;

                if ($fixtureCategoryName === '') {
                    $player1Categories = $registrationCategories
                        ->get($fixture->event_id.':'.$fixture->registration1_id, collect());
                    $player2CategoryNames = $registrationCategories
                        ->get($fixture->event_id.':'.$fixture->registration2_id, collect())
                        ->pluck('category_name')
                        ->map(fn ($name) => $this->normalizeCategory((string) $name));
                    $sharedCategory = $player1Categories->first(fn ($membership) => $player2CategoryNames
                        ->contains($this->normalizeCategory((string) $membership->category_name)));
                    $fixtureCategoryName = (string) ($sharedCategory?->category_name ?? '');
                    $fixtureCategoryEventId = $sharedCategory?->category_event_id
                        ? (int) $sharedCategory->category_event_id
                        : null;
                }

                if (! $groupPlayerIds->contains($player1Id)
                    || ! $groupPlayerIds->contains($player2Id)
                    || $this->normalizeCategory($fixtureCategoryName) !== $this->normalizeCategory($categoryName)
                    || ! $eligibleCategoryEventIds->contains($fixtureCategoryEventId)) {
                    continue;
                }

                $pair = [$player1Id, $player2Id];
                sort($pair);
                $pairKey = implode(':', $pair);
                if (isset($seenPairs[$pairKey])) {
                    continue;
                }

                $sets = $fixtureResults->get($fixture->id, collect());
                $winnerRegistration = (int) ($fixture->winner_registration ?: ($sets->last()?->winner_registration ?? 0));
                if (! in_array($winnerRegistration, [(int) $fixture->registration1_id, (int) $fixture->registration2_id], true)) {
                    continue;
                }
                $seenPairs[$pairKey] = true;

                $winnerIsFirst = $winnerRegistration === (int) $fixture->registration1_id;
                $winnerId = $winnerIsFirst ? $player1Id : $player2Id;
                $loserId = $winnerIsFirst ? $player2Id : $player1Id;
                $score = $sets->map(function ($set) use ($winnerIsFirst) {
                    $winnerScore = $winnerIsFirst ? $set->registration1_score : $set->registration2_score;
                    $loserScore = $winnerIsFirst ? $set->registration2_score : $set->registration1_score;

                    return $winnerScore.'-'.$loserScore;
                })->implode(', ');

                $qualifyingSet = $qualifyingFullSets->get($fixture->id);
                $qualifyingSet['score'] = $winnerIsFirst
                    ? $qualifyingSet['registration1_score'].'-'.$qualifyingSet['registration2_score']
                    : $qualifyingSet['registration2_score'].'-'.$qualifyingSet['registration1_score'];

                $matches[] = [
                    'fixture_id' => (int) $fixture->id,
                    'winner_name' => $playerNames->get($winnerId, 'Unknown Player'),
                    'loser_name' => $playerNames->get($loserId, 'Unknown Player'),
                    'event_id' => (int) $fixture->event_id,
                    'event_name' => (string) $fixture->event_name,
                    'category_event_id' => $fixtureCategoryEventId,
                    'score' => $score,
                    'phase' => $fixture->draw_group_id === null ? 'playoff' : 'single_phase_round_robin',
                    'qualifying_set' => $qualifyingSet,
                ];
            }

            if ($matches !== []) {
                $decision = $group
                    ->map(fn ($ranking) => $ranking->meta_json['head_to_head_decision'] ?? null)
                    ->filter()
                    ->first();
                $advisories[$advisoryKey] ??= [
                    'points' => (int) $group->first()->total_points,
                    'player_ids' => [],
                    'matches' => [],
                    'applied' => false,
                    'confirmed' => false,
                    'decision' => null,
                ];
                $advisories[$advisoryKey]['player_ids'] = array_values(array_unique(array_merge(
                    $advisories[$advisoryKey]['player_ids'],
                    $groupPlayerIds->all()
                )));
                $advisories[$advisoryKey]['matches'] = array_merge($advisories[$advisoryKey]['matches'], $matches);
                $advisories[$advisoryKey]['decision'] ??= $decision;
                $advisories[$advisoryKey]['applied'] = $advisories[$advisoryKey]['applied']
                    || ($decision && collect($matches)->contains(
                        fn (array $match) => (int) $match['fixture_id'] === (int) $decision['fixture_id']
                    ));
                $advisories[$advisoryKey]['confirmed'] = $advisories[$advisoryKey]['confirmed']
                    || ! empty($decision['confirmed_at']);
            }
        }

        return $advisories;
    }

    /** @return array<int, array<string, mixed>> */
    private function rankingLegs(array $meta): array
    {
        if (! empty($meta['counting_legs']) || ! empty($meta['dropped_legs'])) {
            return array_merge(
                array_map(fn (array $leg) => array_merge($leg, ['status' => 'counted']), $meta['counting_legs'] ?? []),
                array_map(fn (array $leg) => array_merge($leg, ['status' => 'dropped']), $meta['dropped_legs'] ?? [])
            );
        }

        return $meta['legs'] ?? [];
    }

    private function resultKey(int $eventId, int $playerId, string $categoryName): string
    {
        return $eventId.':'.$playerId.':'.$this->normalizeCategory($categoryName);
    }

    private function tieKey(int $categoryId, int $points): string
    {
        return $categoryId.':'.$points;
    }

    private function rankingThirdScore($ranking): int
    {
        $meta = is_array($ranking->meta_json) ? $ranking->meta_json : [];
        if (! empty($meta['dropped_legs'])) {
            return (int) collect($meta['dropped_legs'])->max('points');
        }

        return (int) ($meta['third_best'] ?? 0);
    }

    private function normalizeCategory(string $name): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($name)) ?? '');
    }
}
