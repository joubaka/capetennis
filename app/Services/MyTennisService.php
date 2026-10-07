<?php

namespace App\Services;

use App\Models\CategoryEventRegistration;
use App\Models\Fixture;
use App\Models\Player;
use App\Models\TeamFixture;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read-only aggregation for the authenticated player/parent experience.
 *
 * This service deliberately composes existing models and accessors. It does
 * not calculate payment, draw, result, ranking, or eligibility state itself.
 */
class MyTennisService
{
    public function __construct(private readonly PlayerCompetitionTimelineService $timeline)
    {
    }

    public function playersFor(User $user): Collection
    {
        $pivotPlayers = $user->players()->get();
        $legacyPlayers = Player::query()->where('userId', $user->id)->get();

        return $pivotPlayers->concat($legacyPlayers)
            ->unique('id')
            ->sortBy(fn (Player $player) => mb_strtolower($player->full_name))
            ->values();
    }

    public function dashboard(User $user, ?int $playerId = null): array
    {
        $players = $this->playersFor($user);
        $linkedPlayerPage = $this->playerPage($user);
        $linkedPlayerIds = $user->players()->pluck('players.id')->map(fn ($id) => (int) $id)->all();
        $player = $playerId
            ? $players->firstWhere('id', $playerId)
            : $players->first();

        if (! $player) {
            return [
                'players' => $players,
                'linkedPlayerIds' => $linkedPlayerIds,
                'linkedPlayerPage' => $linkedPlayerPage,
                'accountUser' => $user,
                'selectedPlayer' => null,
                'profile' => null,
                'entries' => collect(),
                'upcomingMatches' => collect(),
                'history' => [
                    'entries' => collect(),
                    'placements' => collect(),
                    'rankingScores' => collect(),
                    'seriesRankings' => collect(),
                    'teamAppearances' => collect(),
                ],
            ];
        }

        $entries = CategoryEventRegistration::query()
            ->with(['categoryEvent.event', 'categoryEvent.category', 'registration.players'])
            ->whereHas('players', fn ($query) => $query->whereKey($player->id))
            ->latest('id')
            ->limit(100)
            ->get();

        $matches = $this->upcomingScheduledMatchesFor($player);
        $page = max(1, (int) request()->query('matches_page', 1));
        $upcomingMatchPage = new \Illuminate\Pagination\LengthAwarePaginator(
            $matches->forPage($page, 25)->values(), $matches->count(), 25, $page,
            ['path' => route('my.tennis'), 'pageName' => 'matches_page', 'query' => ['player' => $player->id]],
        );

        return [
            'players' => $players,
            'linkedPlayerIds' => $linkedPlayerIds,
            'linkedPlayerPage' => $linkedPlayerPage,
            'accountUser' => $user,
            'selectedPlayer' => $player,
            'profile' => $player->getProfileStatus(),
            'entries' => $entries,
            'upcomingMatches' => $upcomingMatchPage->getCollection(),
            'upcomingMatchPage' => $upcomingMatchPage,
            'history' => $this->timeline->for($player, 20),
        ];
    }

    public function nextScheduledMatchFor(Player $player): Collection
    {
        return $this->upcomingScheduledMatchesFor($player)->take(1)->values();
    }

    public function upcomingScheduledMatchesFor(Player $player): Collection
    {
        $registrationIds = $player->registrations()->pluck('registrations.id');

        $matches = Fixture::query()
            ->with(['draw.event', 'orderOfPlay.venue', 'fixtureResults', 'registration1.players', 'registration2.players',
                'registration1.categoryEventRegistrations' => fn ($query) => $query->active()->whereNull('withdrawn_at')->with('categoryEvent'),
                'registration2.categoryEventRegistrations' => fn ($query) => $query->active()->whereNull('withdrawn_at')->with('categoryEvent'),
            ])
            ->where(function ($query) use ($registrationIds): void {
                $query->whereIn('registration1_id', $registrationIds)
                    ->orWhereIn('registration2_id', $registrationIds);
            })
            ->whereHas('draw', fn ($query) => $query
                ->where('published', true)
                ->where('oop_published', true))
            ->whereIn('id', \Illuminate\Support\Facades\DB::table('published_schedule_assignments')
                ->where('fixture_kind', 'individual')
                ->where('scheduled_at', '>=', now())
                ->select('fixture_id'))
            ->whereHas('draw.event', fn ($query) => $query
                ->visibleTo(auth()->user())
                ->where(fn ($dates) => $dates->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', today())))
            ->where('match_status', 0)
            ->get();

        app(\App\Services\Scheduling\SchedulePublicationService::class)->projectFixtures($matches);
        $matches = $matches->filter(fn (Fixture $fixture) => $fixture->orderOfPlay?->time)->sortBy(fn (Fixture $fixture) => sprintf(
            '%s_%010d',
            $fixture->orderOfPlay?->time ?? '9999-12-31 23:59:59',
            $fixture->id
        ))->values();

        $individual = $matches->filter(function (Fixture $fixture): bool {
            if ($fixture->fixtureResults->isNotEmpty()
                || Carbon::parse($fixture->orderOfPlay->time)->lessThan(now())) return false;
            foreach ([$fixture->registration1, $fixture->registration2] as $registration) {
                if (! $registration || ! $registration->categoryEventRegistrations->contains(fn ($entry) =>
                    (int) $entry->category_event_id === (int) $fixture->draw->category_event_id
                    && (int) $entry->categoryEvent?->event_id === (int) $fixture->draw->event_id)) return false;
            }
            return true;
        });

        return $individual->concat($this->upcomingTeamMatchesFor($player))
            ->sortBy(fn ($fixture) => sprintf('%s_%s_%010d',
                Carbon::parse($fixture->scheduled_at)->format('Y-m-d H:i:s'),
                $fixture instanceof TeamFixture ? 'team' : 'individual', $fixture->id))
            ->values();
    }

    private function upcomingTeamMatchesFor(Player $player): Collection
    {
        // Limit candidates to events containing the player's roster or substitution;
        // actual participation is still resolved for each scheduled competition side.
        $eventIds = \App\Models\Team::query()
            ->where(fn ($query) => $query
                ->whereHas('team_players', fn ($members) => $members->where('player_id', $player->id))
                ->orWhereHas('competitionSubstitutions', fn ($substitutions) => $substitutions
                    ->where('details->new_type', 'profile')->where('details->new_identity_id', $player->id)))
            ->join('category_events as roster_category', 'roster_category.id', '=', 'teams.category_event_id')
            ->distinct()->pluck('roster_category.event_id');

        $fixtures = TeamFixture::query()->publicDrawFixtures()
            ->where(fn ($query) => $query
                ->whereHas('draw', fn ($draw) => $draw->whereIn('event_id', $eventIds))
                ->orWhere(fn ($legacy) => $legacy->whereNull('team_tie_id')
                    ->where(fn ($players) => $players
                        ->whereHas('team1', fn ($side) => $side->whereKey($player->id))
                        ->orWhereHas('team2', fn ($side) => $side->whereKey($player->id)))))
            ->with(['draw.event', 'teamTie.homeTeam.category', 'teamTie.awayTeam.category', 'fixtureResults', 'team1', 'team2'])
            ->whereHas('draw', fn ($query) => $query->where('oop_published', true))
            ->whereHas('draw.event', fn ($query) => $query->visibleTo(auth()->user())
                ->where(fn ($dates) => $dates->whereNull('end_date')->orWhereDate('end_date', '>=', today())))
            ->whereDoesntHave('fixtureResults')
            ->where('match_status', 0)
            ->whereDoesntHave('teamTie', fn ($query) => $query->where('status', \App\Models\TeamTie::STATUS_COMPLETED))
            ->join('published_schedule_assignments as published_slot', function ($join): void {
                $join->on('published_slot.fixture_id', '=', 'team_fixtures.id')
                    ->on('published_slot.draw_id', '=', 'team_fixtures.draw_id')
                    ->where('published_slot.fixture_kind', 'team');
            })
            ->where('published_slot.scheduled_at', '>=', now())
            ->orderBy('published_slot.scheduled_at')->orderBy('team_fixtures.id')
            ->select('team_fixtures.*')->lazy(100)
            ->filter(function (TeamFixture $fixture) use ($player): bool {
                if (! $fixture->teamTie) {
                    return $fixture->team1->contains('id', $player->id) || $fixture->team2->contains('id', $player->id);
                }
                foreach ([$fixture->teamTie->homeTeam, $fixture->teamTie->awayTeam] as $team) {
                    if (! $team || (int) $team->category?->event_id !== (int) $fixture->draw->event_id) continue;
                    // Resolve the active competition roster, including mixed sides and substitutions.
                    try {
                        $side = app(TeamDrawSideResolver::class)->side($fixture->draw, $team,
                            $fixture->teamTie->round_nr, $fixture->id);
                    } catch (\InvalidArgumentException $exception) {
                        continue;
                    }
                    if ($side?->team_players->contains('player_id', $player->id)) return true;
                }
                return false;
            });

        $matches = $fixtures->collect();
        app(\App\Services\Scheduling\SchedulePublicationService::class)->projectFixtures($matches);
        $matches = $matches->filter(fn (TeamFixture $fixture) => $fixture->scheduled_at
            && Carbon::parse($fixture->scheduled_at)->greaterThanOrEqualTo(now()))->values();
        app(TeamFixtureLineupPresenter::class)->prepare($matches, publicDraw: true, selectedPlayerId: $player->id);
        return $matches->groupBy(fn (TeamFixture $fixture) => $fixture->team_tie_id ? 'tie:'.$fixture->team_tie_id : 'fixture:'.$fixture->id)
            ->flatMap(function ($tieMatches) {
                $assigned = $tieMatches->filter(fn ($fixture) => ($fixture->lineup_display['home']['selected_player_assigned'] ?? false)
                    || ($fixture->lineup_display['away']['selected_player_assigned'] ?? false));
                if ($assigned->isNotEmpty()) return $assigned;
                // Only a genuinely unassigned tie gets a team-time fallback.
                if ($tieMatches->contains(fn ($fixture) => $fixture->fixturePlayers->contains(fn ($slot) =>
                    $slot->team1_id || $slot->team2_id || $slot->team1_no_profile_id || $slot->team2_no_profile_id)
                    || $fixture->team1->isNotEmpty() || $fixture->team2->isNotEmpty())) return collect();
                return $tieMatches->take(1);
            })->map(function ($fixture) {
            $names = [];
            foreach (['home', 'away'] as $side) {
                $names[$side] = collect($fixture->lineup_display[$side]['players'] ?? [])
                    ->pluck('name')->filter(fn ($name) => $name && $name !== 'TBD')->values()->all();
            }
            $fixture->setAttribute('profile_match_players', $names);
            return $fixture;
        })
            ->values();
    }

    public function playerPage(User $user, int $page = 1, int $perPage = 25): LengthAwarePaginator
    {
        return Player::query()
            ->where(function ($query) use ($user): void {
                $query->where('userId', $user->id)
                    ->orWhereHas('users', fn ($users) => $users->whereKey($user->id));
            })
            ->orderByRaw("LOWER(COALESCE(name, ''))")
            ->orderByRaw("LOWER(COALESCE(surname, ''))")
            ->paginate($perPage, ['*'], 'page', max(1, $page));
    }
}
