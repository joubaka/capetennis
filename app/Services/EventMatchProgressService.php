<?php

namespace App\Services;

use App\Domain\Draws\Enums\FixtureState;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\TeamFixture;

final class EventMatchProgressService
{
    /** Public progress covers every published draw, independently of schedule/standings publication. */
    public function forEvent(Event $event): array
    {
        $progress = ['finished' => 0, 'total' => 0];
        $drawIds = app(PublicTournamentVisibility::class)->publishedDrawsFor($event)->select('draws.id');

        Fixture::query()->whereIn('draw_id', clone $drawIds)
            ->where(fn ($status) => $status->whereNull('match_status')
                ->orWhereNotIn('match_status', [FixtureState::STATUS_BYE, FixtureState::STATUS_DOUBLE_BYE]))
            // Some historical consolation walkovers only saved the winner.
            // They never represented a match between two participants.
            ->where(fn ($playable) => $playable->whereNull('winner_registration')
                ->orWhere(fn ($slots) => $slots->whereNotNull('registration1_id')->whereNotNull('registration2_id'))
                ->orWhereHas('fixtureResults'))
            ->where(function ($query) {
                $query->where(fn ($slots) => $slots->whereNotNull('registration1_id')->whereNotNull('registration2_id'))
                    ->orWhere(fn ($sources) => $sources->whereNotNull('registration1_source_group_id')->whereNotNull('registration2_source_group_id'))
                    ->orWhereExists(function ($feeders) {
                        $feeders->selectRaw('1')->from('fixtures as feeders')
                            ->whereColumn('feeders.draw_id', 'fixtures.draw_id')
                            ->where(fn ($targets) => $targets->whereColumn('feeders.parent_fixture_id', 'fixtures.id')
                                ->orWhereColumn('feeders.loser_parent_fixture_id', 'fixtures.id'));
                    });
            })
            ->with(['fixtureResults', 'draw.settings'])
            ->chunkById(200, function ($fixtures) use (&$progress) {
                foreach ($fixtures as $fixture) {
                    $progress['total']++;
                    $progress['finished'] += app(IndividualMatchOutcomeService::class)->winner($fixture) !== null ? 1 : 0;
                }
            });

        TeamFixture::query()->without('fixturePlayers')->whereIn('draw_id', clone $drawIds)
            ->publicDrawFixtures()
            ->chunkById(200, function ($fixtures) use (&$progress) {
                foreach ($fixtures as $fixture) {
                    $progress['total']++;
                    $progress['finished'] += app(TeamRubberResultService::class)->outcome($fixture)['complete'] ? 1 : 0;
                }
            });

        return $progress;
    }
}
