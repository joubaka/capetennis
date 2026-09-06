<?php

namespace App\Services;

use App\Domain\Draws\Enums\FixtureState;
use App\Models\TeamFixture;
use App\Models\TeamFixtureResult;
use Illuminate\Support\Facades\DB;

class TeamFixtureScoreService
{
    /**
     * Persist the three supported score sets while repairing legacy duplicates.
     * The fixture row lock serializes every score writer that uses this service.
     */
    public function save(TeamFixture $fixture, array $scores): void
    {
        DB::transaction(function () use ($fixture, $scores): void {
            TeamFixture::whereKey($fixture->id)->lockForUpdate()->firstOrFail();

            foreach (range(1, 3) as $setNumber) {
                $home = $scores["set{$setNumber}_home"] ?? null;
                $away = $scores["set{$setNumber}_away"] ?? null;
                $results = TeamFixtureResult::where('team_fixture_id', $fixture->id)
                    ->where('set_nr', $setNumber)
                    ->orderBy('id')
                    ->get();

                if ($home === null && $away === null) {
                    TeamFixtureResult::whereIn('id', $results->pluck('id'))->delete();
                    continue;
                }

                $result = $results->shift() ?? new TeamFixtureResult();
                $result->fill([
                    'team_fixture_id' => $fixture->id,
                    'set_nr' => $setNumber,
                    'team1_score' => $home,
                    'team2_score' => $away,
                    'match_winner_id' => null,
                    'match_loser_id' => null,
                ])->save();

                if ($results->isNotEmpty()) {
                    TeamFixtureResult::whereIn('id', $results->pluck('id'))->delete();
                }
            }

            TeamFixture::whereKey($fixture->id)->update([
                'match_status' => FixtureState::STATUS_COMPLETED,
            ]);
        });
    }

    public function delete(TeamFixture $fixture): void
    {
        DB::transaction(function () use ($fixture): void {
            TeamFixture::whereKey($fixture->id)->lockForUpdate()->firstOrFail();
            TeamFixtureResult::where('team_fixture_id', $fixture->id)->delete();
            TeamFixture::whereKey($fixture->id)->update([
                'match_status' => FixtureState::STATUS_PENDING,
            ]);
        });
    }
}
