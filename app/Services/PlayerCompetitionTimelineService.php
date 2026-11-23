<?php

namespace App\Services;

use App\Models\CategoryEventRegistration;
use App\Models\Player;
use App\Models\Position;
use App\Models\RankingScores;
use App\Models\SeriesRanking;
use App\Models\TeamFixturePlayer;
use App\Models\TeamFixture;
use App\Domain\Ranking\Enums\RankingStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Canonical read model for a player's competition history.
 * Existing entry, placement, ranking, and team-draw records remain the source
 * of truth; this class only composes bounded, publication-aware reads.
 */
class PlayerCompetitionTimelineService
{
    public function for(Player $player, int $limit = 50, bool $dashboardOnly = false): array
    {
        $limit = max(1, min(100, $limit));

        $entries = $dashboardOnly ? collect() : CategoryEventRegistration::query()
            ->with(['categoryEvent.event', 'categoryEvent.category', 'registration.players'])
            ->whereHas('players', fn ($query) => $query->whereKey($player->id))
            ->latest('id')->limit($limit)->get();

        $placements = $this->publishedPlacementsFor($player, $limit);

        $recentResults = $this->recentResultsFor($player, $limit, $placements);

        $rankingScores = $dashboardOnly ? collect() : RankingScores::query()
            ->with(['rankingList'])
            ->where('player_id', $player->id)
            ->latest('updated_at')->limit($limit)->get();

        $seriesRankings = SeriesRanking::query()
            ->with(['series', 'category', 'rankingList'])
            ->where('player_id', $player->id)
            ->whereHas('series', fn ($query) => $query->where('leaderboard_published', true))
            ->where(function ($scope) {
                $scope->where('status', RankingStatus::Published->value)->whereNotNull('run_id')->where('run_id', function ($query) {
                $query->from('series_rankings as latest')->select('latest.run_id')
                    ->whereColumn('latest.series_id', 'series_rankings.series_id')
                    ->where('latest.status', RankingStatus::Published->value)->whereNotNull('latest.run_id')
                    ->orderByDesc('latest.published_at')->orderByDesc('latest.updated_at')->limit(1);
                });
                $scope->orWhere(function ($legacy) {
                    $legacy->whereNull('run_id')->where(fn ($state) => $state->where('status', RankingStatus::Published->value)
                        ->orWhere(fn ($old) => $old->whereNull('status')->whereNotNull('published_at')))
                        ->whereNotExists(function ($current) {
                            $current->selectRaw('1')->from('series_rankings as modern')
                                ->whereColumn('modern.series_id', 'series_rankings.series_id')
                                ->where('modern.status', RankingStatus::Published->value)->whereNotNull('modern.run_id');
                        });
                });
            })
            ->latest('published_at')->limit($limit)->get();
        $seriesRankings = $seriesRankings->filter(fn ($ranking) => app(\App\Domain\Ranking\Services\RankingTeamEligibilityService::class)
            ->publiclyRanked(collect([$ranking]), $ranking->series)->isNotEmpty())->values();

        $teamAppearances = $dashboardOnly ? collect() : TeamFixturePlayer::query()
            ->with(['fixture.draw.event', 'fixture.venue', 'fixture.teamResults'])
            ->where(function ($query) use ($player): void {
                $query->where('team1_id', $player->id)->orWhere('team2_id', $player->id);
            })
            ->whereHas('fixture.draw', fn ($query) => $query->where('published', true))
            ->latest('id')->limit($limit)->get();

        return compact('entries', 'placements', 'recentResults', 'rankingScores', 'seriesRankings', 'teamAppearances');
    }

    /** Public draw scores do not require publication of the separate finishing list. */
    public function recentResultsFor(Player $player, int $limit = 20, ?Collection $placements = null): Collection
    {
        $limit = max(1, min(100, $limit));
        $placements ??= $this->publishedPlacementsFor($player, $limit);
        $rows = $placements->map(fn ($placement) => [
            'event_id' => $placement->categoryEvent->event_id,
            'category_event_id' => $placement->category_event_id,
            'event' => $placement->categoryEvent->event->name,
            'category' => $placement->categoryEvent->category?->name ?? 'Category',
            'position' => $placement->position,
            'date' => $placement->categoryEvent->event->end_date?->toDateString() ?? '',
            'draw_id' => null,
        ]);

        // Select draws, rather than match rows, so one busy draw cannot crowd
        // every other recent event out of the bounded dashboard read.
        $draws = DB::table('draws')->join('events', 'events.id', '=', 'draws.event_id')
            ->leftJoin('category_events', function ($join) {
                $join->on('category_events.id', '=', 'draws.category_event_id')->on('category_events.event_id', '=', 'events.id');
            })->leftJoin('categories', 'categories.id', '=', 'category_events.category_id')
            ->where('events.published', true)->where('draws.published', true)
            ->whereDate('events.end_date', '<', today('Africa/Johannesburg'))
            ->whereExists(function ($query) use ($player) {
                $query->selectRaw('1')->from('fixtures')
                    ->join('player_registrations as participants', function ($join) {
                        $join->on('participants.registration_id', '=', 'fixtures.registration1_id')
                            ->orOn('participants.registration_id', '=', 'fixtures.registration2_id');
                    })->join('category_event_registrations as entries', 'entries.registration_id', '=', 'participants.registration_id')
                    ->join('category_events as entered_category', 'entered_category.id', '=', 'entries.category_event_id')
                    ->whereColumn('fixtures.draw_id', 'draws.id')->where('participants.player_id', $player->id)
                    ->whereNull('entries.deleted_at')->whereColumn('entered_category.event_id', 'events.id')
                    ->where(fn ($category) => $category->whereNull('draws.category_event_id')
                        ->orWhereColumn('entered_category.id', 'draws.category_event_id'))
                    ->whereExists(fn ($scores) => $scores->selectRaw('1')->from('fixture_results')->whereColumn('fixture_results.fixture_id', 'fixtures.id'));
            })
            ->groupBy('draws.event_id', 'draws.category_event_id', 'events.name', 'events.end_date', 'categories.name')
            ->orderByDesc('events.end_date')->orderByDesc(DB::raw('MAX(draws.id)'))->limit($limit)
            ->get([DB::raw('MAX(draws.id) as id'), 'draws.event_id', 'draws.category_event_id', DB::raw('MAX(draws.drawName) as drawName'), 'events.name', 'events.end_date', 'categories.name as category']);
        foreach ($draws as $draw) {
            if ($rows->contains(fn ($row) => (int) $row['event_id'] === (int) $draw->event_id
                && (int) $row['category_event_id'] === (int) $draw->category_event_id)) continue;
            $rows->push(['event_id' => $draw->event_id, 'category_event_id' => $draw->category_event_id,
                'event' => $draw->name, 'category' => $draw->category ?? $draw->drawName,
                'position' => null, 'date' => $draw->end_date, 'draw_id' => $draw->id]);
        }
        foreach ($this->teamDrawResultsFor($player, $limit) as $row) {
            if (!$rows->contains(fn ($existing) => (int) $existing['event_id'] === (int) $row['event_id']
                && (int) $existing['category_event_id'] === (int) $row['category_event_id'])) $rows->push($row);
        }
        // Only a separately published finishing list supplies a numbered place.
        return $rows->sortByDesc('date')->take($limit)->values();
    }

    private function publishedPlacementsFor(Player $player, int $limit): Collection
    {
        return Position::with(['categoryEvent.event', 'categoryEvent.category'])
            ->where('player_id', $player->id)
            ->whereHas('categoryEvent.event', fn ($query) => $query->where('published', true)->where('results_published', true))
            ->orderByDesc(DB::table('events')->join('category_events', 'category_events.event_id', '=', 'events.id')
                ->whereColumn('category_events.id', 'positions.category_event_id')->select('events.end_date')->limit(1))
            ->latest('id')->limit($limit)->get();
    }

    private function teamDrawResultsFor(Player $player, int $limit): Collection
    {
        $fixtures = TeamFixture::query()->publishedTeamTies()->publicDrawFixtures()
            ->whereHas('draw.event', fn ($event) => $event->where('published', true)
                ->whereDate('end_date', '<', today('Africa/Johannesburg')))
            ->whereHas('fixturePlayers', fn ($slots) => $slots->where('team1_id', $player->id)->orWhere('team2_id', $player->id))
            ->whereHas('fixtureResults')
            ->with(['draw' => fn ($draw) => $draw->without('venues')->with(['event', 'categoryEvent.category']), 'teamTie'])
            ->join('draws as result_draw', 'result_draw.id', '=', 'team_fixtures.draw_id')
            ->join('events as result_event', 'result_event.id', '=', 'result_draw.event_id')
            ->orderByDesc('result_event.end_date')->orderByDesc('team_fixtures.id')
            ->limit(500)->get(['team_fixtures.*']);
        $presenter = app(TeamFixtureLineupPresenter::class);
        $fixtures = $presenter->eligiblePublicLineups($fixtures);
        if ($fixtures->isEmpty()) return collect();
        $presenter->prepare($fixtures, publicDraw: true, selectedPlayerId: $player->id, includeProfileIds: true);
        return $fixtures->filter(fn ($fixture) => collect(['home', 'away'])->contains(fn ($side) =>
            collect($fixture->lineup_display[$side]['players'] ?? [])->contains(fn ($person) => (int) ($person['player_id'] ?? 0) === (int) $player->id)))
            ->unique(fn ($fixture) => $fixture->draw->event_id.':'.$fixture->draw->category_event_id)->take($limit)->map(fn ($fixture) => [
                'event_id' => $fixture->draw->event_id, 'category_event_id' => $fixture->draw->category_event_id,
                'event' => $fixture->draw->event->name,
                'category' => $fixture->draw->categoryEvent?->category?->name ?? $fixture->draw->drawName,
                'position' => null, 'date' => $fixture->draw->event->end_date->toDateString(), 'draw_id' => $fixture->draw_id,
            ])->values();
    }
}
