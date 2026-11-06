<?php

namespace App\Services\Performance;

use App\Domain\Draws\Enums\FixtureState;
use App\Models\{Fixture, NoProfileTeamPlayer, Player, Team, TeamFixture, TeamPlayer, TeamTie, User};
use App\Services\{IndividualMatchOutcomeService, TeamFixtureLineupPresenter, TeamRubberResultService};
use Illuminate\Support\Collection;

class PlayerLiveResults
{
    private const LIMIT = 20;

    public function forPlayer(User $user, Player $player): Collection
    {
        $since = now()->subDays(7);
        $publishedEvent = fn ($query) => $query->visibleTo($user)->where('published', true);
        $individual = Fixture::query()
            ->with(['draw.event', 'draw.settings', 'fixtureResults', 'registration1.players',
                'registration2.players', 'registration1.categoryEvents', 'registration2.categoryEvents'])
            ->whereHas('draw', fn ($query) => $query->where('published', true)->whereHas('event', $publishedEvent))
            ->where(fn ($query) => $query
                ->whereHas('registration1.players', fn ($players) => $players->whereKey($player->id))
                ->orWhereHas('registration2.players', fn ($players) => $players->whereKey($player->id)))
            ->whereIn('match_status', [FixtureState::STATUS_COMPLETED, FixtureState::STATUS_BYE, FixtureState::STATUS_RETIRED])
            ->withRecordedResult()
            ->where(fn ($query) => $query
                ->whereHas('fixtureResults', fn ($scores) => $scores->where('updated_at', '>=', $since))
                ->orWhere(fn ($terminal) => $terminal->whereIn('match_status', [FixtureState::STATUS_BYE, FixtureState::STATUS_RETIRED])
                    ->where('fixtures.updated_at', '>=', $since)))
            ->withMax('fixtureResults as score_updated_at', 'updated_at')
            ->orderByDesc('score_updated_at')->latest('fixtures.updated_at')
            ->latest('fixtures.id')->limit(self::LIMIT * 2)->get()
            ->map(fn (Fixture $fixture) => $this->individualResult($fixture, $player));

        $team = TeamFixture::query()
            ->publicDrawFixtures()->publishedTeamTies()
            ->whereHas('draw.event', $publishedEvent)
            ->with(['draw.event', 'teamTie'])
            ->where('match_status', '!=', FixtureState::STATUS_PARTIAL)
            ->whereHas('fixturePlayers', fn ($players) => $players
                ->where(fn ($side) => $side->where('team1_id', $player->id)->orWhere('team2_id', $player->id)))
            ->whereHas('teamResults', fn ($scores) => $scores->where('updated_at', '>=', $since))
            ->withMax('teamResults as score_updated_at', 'updated_at')
            ->latest('score_updated_at')->latest('team_fixtures.id')->limit(self::LIMIT * 2)->get();

        $team = $this->eligibleTeamLineups($team);
        app(TeamFixtureLineupPresenter::class)->prepare($team, publicDraw: true, selectedPlayerId: $player->id, includeProfileIds: true);

        return $individual->concat($team->map(fn (TeamFixture $fixture) => $this->teamResult($fixture)))
            ->filter()->sortByDesc('recorded_at')->take(self::LIMIT)->values();
    }

    private function individualResult(Fixture $fixture, Player $player): ?array
    {
        if (!$fixture->draw?->published || !$fixture->draw->event?->published) return null;

        $draw = $fixture->draw;
        foreach ([$fixture->registration1, $fixture->registration2] as $registration) {
            if (!$registration?->categoryEvents->contains(fn ($category) => (int) $category->event_id === (int) $draw->event_id
                && (!$draw->category_event_id || (int) $category->id === (int) $draw->category_event_id))) return null;
        }
        $home = $fixture->registration1->players->contains('id', $player->id);
        $away = $fixture->registration2->players->contains('id', $player->id);
        if ($home === $away) return null;

        $outcome = app(IndividualMatchOutcomeService::class);
        $winner = $outcome->winner($fixture);
        if (!$winner && !$outcome->isDraw($fixture)) return null;

        $own = $home ? $fixture->registration1 : $fixture->registration2;
        $opponents = ($home ? $fixture->registration2 : $fixture->registration1)->players
            ->map(fn (Player $opponent) => ['name' => $opponent->full_name, 'player_id' => $opponent->id]);
        $score = $fixture->isWalkover() ? 'W/O' : $this->score($fixture->fixtureResults, 'registration', $home);
        if ($fixture->isRetired()) $score = trim($score.' RET');

        return $this->row(
            $fixture,
            $opponents,
            $winner ? ((int) $winner === (int) $own->id ? 'Win' : 'Loss') : 'Draw',
            $score,
            'public.roundrobin.show',
        );
    }

    private function teamResult(TeamFixture $fixture): ?array
    {
        if (!$fixture->draw?->published || !$fixture->draw->event?->published) return null;

        $lineup = $fixture->lineup_display;
        $home = $lineup['home']['selected_player_assigned'];
        $away = $lineup['away']['selected_player_assigned'];
        if ($home === $away) return null;

        $outcome = app(TeamRubberResultService::class)->outcome($fixture);
        if (!$outcome['complete'] || !$outcome['winner']) return null;

        $opponents = collect($lineup[$home ? 'away' : 'home']['players']);
        if ($opponents->isEmpty() || $opponents->contains(fn ($opponent) => !$opponent['name'] || $opponent['name'] === 'TBD')) return null;

        return $this->row(
            $fixture,
            $opponents,
            $outcome['winner'] === ($home ? 'home' : 'away') ? 'Win' : 'Loss',
            $this->score($fixture->teamResults, 'team', $home),
            'frontend.fixtures.show',
        );
    }

    private function eligibleTeamLineups(Collection $fixtures): Collection
    {
        if ($fixtures->isEmpty()) return $fixtures;

        $legacy = $fixtures->filter(fn (TeamFixture $fixture) => !$fixture->team_tie_id);
        $slots = $legacy->flatMap(fn (TeamFixture $fixture) => $fixture->fixturePlayers);
        $eventIds = $legacy->pluck('draw.event_id')->unique();
        $profiles = $slots->pluck('team1_id')->merge($slots->pluck('team2_id'))->filter()->unique();
        $imports = $slots->pluck('team1_no_profile_id')->merge($slots->pluck('team2_no_profile_id'))->filter()->unique();
        $profileMemberships = collect();
        foreach ([TeamPlayer::class => 'player_id', NoProfileTeamPlayer::class => 'player_profile'] as $model => $profileKey) {
            if ($profiles->isEmpty()) break;
            $memberships = $model::query()->with('team.category')->whereIn($profileKey, $profiles)
                ->whereHas('team.category', fn ($query) => $query->whereIn('event_id', $eventIds))->get();
            foreach ($memberships as $membership) {
                if ($category = $membership->team?->category) {
                    $profileMemberships->put($category->event_id.':'.$membership->{$profileKey}, true);
                }
            }
        }
        $importEvents = $imports->isEmpty() ? collect() : NoProfileTeamPlayer::query()->with('team.category')->whereKey($imports->all())->get()
            ->mapWithKeys(fn (NoProfileTeamPlayer $import) => [$import->id => (int) $import->team?->category?->event_id]);

        $sourceIds = $fixtures->mapWithKeys(fn (TeamFixture $fixture) => [$fixture->id => $this->teamSourceIds($fixture)]);
        $teamIds = $sourceIds->flatten()->filter()->unique();
        $teamEvents = $teamIds->isEmpty() ? collect() : Team::query()->with('category')->whereKey($teamIds->all())->get()
            ->mapWithKeys(fn (Team $team) => [$team->id => (int) $team->category?->event_id]);

        return $fixtures->filter(function (TeamFixture $fixture) use ($profileMemberships, $importEvents, $sourceIds, $teamEvents) {
            $event = (int) $fixture->draw?->event_id;
            if (!$event || $fixture->fixturePlayers->contains(fn ($slot) =>
                ($slot->team1_id && $slot->team1_no_profile_id) || ($slot->team2_id && $slot->team2_no_profile_id))) return false;
            if ($fixture->team_tie_id) {
                $tie = $fixture->teamTie;
                return $tie?->published_at && (int) $tie->draw_id === (int) $fixture->draw_id
                    && in_array($tie->status, [TeamTie::STATUS_PUBLISHED, TeamTie::STATUS_COMPLETED], true)
                    && $sourceIds[$fixture->id]->every(fn ($id) => $teamEvents->get($id) === $event);
            }
            if ($fixture->draw->team_scoring_rules !== null || $fixture->draw->team_format_snapshot !== null) return false;

            // Legacy fixtures have no canonical team sides for the lineup presenter to validate.
            return $fixture->fixturePlayers->every(function ($slot) use ($event, $profileMemberships, $importEvents) {
                foreach ([1, 2] as $side) {
                    $profile = $slot->{'team'.$side.'_id'};
                    $import = $slot->{'team'.$side.'_no_profile_id'};
                    if ($profile && !$profileMemberships->has($event.':'.$profile)) return false;
                    if ($import && $importEvents->get($import) !== $event) return false;
                }

                return true;
            });
        })->values();
    }

    private function teamSourceIds(TeamFixture $fixture): Collection
    {
        $tie = $fixture->teamTie;
        if (!$tie) return collect();

        $mixed = $fixture->draw?->team_draw_selection['mixed_sides'] ?? [];

        return collect([$tie->home_team_id, $tie->away_team_id])->flatMap(fn ($id) => isset($mixed[$id])
            ? [$mixed[$id]['boys'], $mixed[$id]['girls']]
            : [$id]);
    }

    private function score(Collection $scores, string $prefix, bool $home): string
    {
        $homeColumn = $prefix.'1_score';
        $awayColumn = $prefix.'2_score';

        return $scores->sortBy('set_nr')->unique('set_nr')
            ->filter(fn ($score) => is_numeric($score->{$homeColumn}) && is_numeric($score->{$awayColumn}))
            ->map(fn ($score) => $home
                ? $score->{$homeColumn}.'-'.$score->{$awayColumn}
                : $score->{$awayColumn}.'-'.$score->{$homeColumn})
            ->implode(' ');
    }

    private function row(Fixture|TeamFixture $fixture, Collection $opponents, string $outcome, string $score, string $route): array
    {
        $recordedAt = $fixture->score_updated_at;
        if ($fixture instanceof Fixture && ($fixture->isRetired() || $fixture->isWalkover())) {
            $recordedAt = max($recordedAt, $fixture->updated_at?->toDateTimeString());
        }

        return [
            'recorded_at' => $recordedAt,
            'event' => $fixture->draw->event->name,
            'draw' => $fixture->draw->drawName,
            'opponents' => $opponents->pluck('name')->filter()->join(' / '),
            'opponent_players' => $opponents->filter(fn ($opponent) => filled($opponent['name']))->values()->all(),
            'outcome' => $outcome,
            'score' => $score,
            'url' => route($route, $fixture->draw_id),
        ];
    }
}
