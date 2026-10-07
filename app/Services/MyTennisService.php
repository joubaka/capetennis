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

        $upcomingMatches = $this->nextScheduledMatchFor($player);

        return [
            'players' => $players,
            'linkedPlayerIds' => $linkedPlayerIds,
            'linkedPlayerPage' => $linkedPlayerPage,
            'accountUser' => $user,
            'selectedPlayer' => $player,
            'profile' => $player->getProfileStatus(),
            'entries' => $entries,
            'upcomingMatches' => $upcomingMatches,
            'history' => $this->timeline->for($player, 20),
        ];
    }

    public function nextScheduledMatchFor(Player $player): Collection
    {
        $registrationIds = $player->registrations()->pluck('registrations.id');

        $matches = Fixture::query()
            ->with(['draw.event', 'orderOfPlay.venue', 'fixtureResults', 'registration1.players', 'registration2.players'])
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
            ->limit(500)
            ->get();

        app(\App\Services\Scheduling\SchedulePublicationService::class)->projectFixtures($matches);
        $matches = $matches->filter(fn (Fixture $fixture) => $fixture->orderOfPlay?->time)->sortBy(fn (Fixture $fixture) => sprintf(
            '%s_%010d',
            $fixture->orderOfPlay?->time ?? '9999-12-31 23:59:59',
            $fixture->id
        ))->values();

        $individual = $matches->filter(fn (Fixture $fixture) => $fixture->fixtureResults->isEmpty()
            && Carbon::parse($fixture->orderOfPlay->time)->greaterThanOrEqualTo(now()));

        return $individual->concat($this->nextTeamMatchesFor($player))
            ->sortBy(fn ($fixture) => sprintf('%s_%s_%010d',
                Carbon::parse($fixture->scheduled_at)->format('Y-m-d H:i:s'),
                $fixture instanceof TeamFixture ? 'team' : 'individual', $fixture->id))
            ->take(1)->values();
    }

    private function nextTeamMatchesFor(Player $player): Collection
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

        foreach ($fixtures as $fixture) {
            $candidate = collect([$fixture]);
            app(\App\Services\Scheduling\SchedulePublicationService::class)->projectFixtures($candidate);
            if (! $fixture->scheduled_at || Carbon::parse($fixture->scheduled_at)->lessThan(now())) continue;
            app(TeamFixtureLineupPresenter::class)->prepare($candidate, publicDraw: true);
            return $candidate;
        }

        return collect();
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
