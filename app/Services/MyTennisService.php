<?php

namespace App\Services;

use App\Models\CategoryEventRegistration;
use App\Models\Fixture;
use App\Models\Player;
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
        if ($registrationIds->isEmpty()) {
            return collect();
        }

        $matches = Fixture::query()
            ->with(['draw.event', 'orderOfPlay.venue', 'fixtureResults', 'registration1.players', 'registration2.players'])
            ->where(function ($query) use ($registrationIds): void {
                $query->whereIn('registration1_id', $registrationIds)
                    ->orWhereIn('registration2_id', $registrationIds);
            })
            ->whereHas('draw', fn ($query) => $query
                ->where('published', true)
                ->where('oop_published', true))
            ->whereHas('orderOfPlay', fn ($query) => $query->whereNotNull('time'))
            ->whereHas('draw.event', fn ($query) => $query
                ->whereNull('end_date')
                ->orWhereDate('end_date', '>=', today()))
            ->limit(500)
            ->get();

        $matches = $matches->sortBy(fn (Fixture $fixture) => sprintf(
            '%s_%010d',
            $fixture->orderOfPlay?->time ?? '9999-12-31 23:59:59',
            $fixture->id
        ))->values();

        return $matches
            ->filter(fn (Fixture $fixture) => $fixture->fixtureResults->isEmpty()
                && Carbon::parse($fixture->orderOfPlay->time)->greaterThanOrEqualTo(now()))
            ->take(1)
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
