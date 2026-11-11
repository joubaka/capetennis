<?php

namespace App\Services\Performance;

use App\Domain\Draws\Enums\FixtureState;
use App\Models\{Fixture, Player, TeamFixture, User};
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

        $presenter = app(TeamFixtureLineupPresenter::class);
        $team = $presenter->eligiblePublicLineups($team);
        $presenter->prepare($team, publicDraw: true, selectedPlayerId: $player->id, includeProfileIds: true);

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
