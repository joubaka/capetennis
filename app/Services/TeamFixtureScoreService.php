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
            $draw = \App\Models\Draw::whereKey($fixture->draw_id)->lockForUpdate()->firstOrFail();
            $tie = $fixture->team_tie_id ? \App\Models\TeamTie::whereKey($fixture->team_tie_id)->lockForUpdate()->firstOrFail() : null;
            $current = TeamFixture::whereKey($fixture->id)->lockForUpdate()->firstOrFail();
            abort_if($current->draw_id !== $draw->id || $current->team_tie_id !== $tie?->id || ($tie && $tie->draw_id !== $draw->id), 409, 'Fixture relationships changed.');
            $canonical = $draw->team_scoring_rules !== null && $tie !== null;
            abort_if($draw->locked || (!$canonical && $tie?->isCompleted()), 409, 'Scores are locked for this fixture.');

            abort_if($canonical && (!$tie->published_at || !in_array($tie->status, [\App\Models\TeamTie::STATUS_PUBLISHED, \App\Models\TeamTie::STATUS_COMPLETED], true)),
                409, 'Validate and publish the tie before entering scores.');

            if ($canonical) {
                $validated = app(TeamRubberResultService::class)->validate($current, $scores);
                $scores = [];
                foreach ($validated as $set) {
                    $scores['set'.$set['set_nr'].'_home'] = $set['team1_score'];
                    $scores['set'.$set['set_nr'].'_away'] = $set['team2_score'];
                }
            }

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

            $current->load('teamResults');
            $outcome = $canonical ? app(TeamRubberResultService::class)->outcome($current) : null;
            $current->update(['match_status' => !$canonical || $outcome['complete'] ? FixtureState::STATUS_COMPLETED
                : ($current->teamResults->isEmpty() ? FixtureState::STATUS_PENDING : FixtureState::STATUS_PARTIAL)]);
            if ($canonical) { app(TeamStandingsService::class)->refreshTie($tie); }
        });
    }

    public function delete(TeamFixture $fixture): void
    {
        DB::transaction(function () use ($fixture): void {
            $draw = \App\Models\Draw::whereKey($fixture->draw_id)->lockForUpdate()->firstOrFail();
            $tie = $fixture->team_tie_id ? \App\Models\TeamTie::whereKey($fixture->team_tie_id)->lockForUpdate()->firstOrFail() : null;
            $current = TeamFixture::whereKey($fixture->id)->lockForUpdate()->firstOrFail();
            abort_if($current->draw_id !== $draw->id || $current->team_tie_id !== $tie?->id || ($tie && $tie->draw_id !== $draw->id), 409, 'Fixture relationships changed.');
            $canonical = $draw->team_scoring_rules !== null && $tie !== null;
            abort_if($draw->locked || (!$canonical && $tie?->isCompleted()), 409, 'Scores are locked for this fixture.');
            TeamFixtureResult::where('team_fixture_id', $fixture->id)->delete();
            TeamFixture::whereKey($fixture->id)->update([
                'match_status' => FixtureState::STATUS_PENDING,
            ]);
            if ($canonical) { app(TeamStandingsService::class)->refreshTie($tie); }
        });
    }
}
