<?php

namespace Tests\Unit;

use App\Domain\Draws\Services\{StandingsService, TennisScoreFormat};
use App\Models\{Draw, DrawGroup, DrawGroupRegistration, DrawSetting, Fixture, FixtureResult, Player, Registration};
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class RoundRobinMatchTiebreakStandingsTest extends TestCase
{
    private function context(array $matches, ?string $format = null): array
    {
        $draw = new Draw(); $draw->id = 10;
        $draw->setRelation('settings', new DrawSetting(['workflow' => 'round_robin',
            'score_format' => $format ?? TennisScoreFormat::BEST_OF_3_START_2_MATCH_TIEBREAK, 'num_sets' => 3]));
        $group = new DrawGroup(['name' => 'A', 'draw_id' => 10]); $group->id = 20;
        $group->setRelation('draw', $draw);
        $members = [];
        foreach (range(1, 3) as $id) {
            $registration = new Registration(); $registration->id = $id;
            $registration->setRelation('players', new Collection([new Player(['name' => 'Fictional '.$id, 'surname' => 'Player'])]));
            $member = new DrawGroupRegistration(['registration_id' => $id]);
            $member->setRelation('registration', $registration); $members[] = $member;
        }
        $group->setRelation('groupRegistrations', new Collection($members));
        $fixtures = [];
        foreach ($matches as $index => [$home, $away, $sets]) {
            $fixture = new Fixture(['draw_id' => 10, 'draw_group_id' => 20, 'stage' => 'RR',
                'registration1_id' => $home, 'registration2_id' => $away]); $fixture->id = 100 + $index;
            $fixture->setRelation('draw', $draw);
            $fixture->setRelation('fixtureResults', new Collection(array_map(fn ($set, $i) => new FixtureResult([
                'set_nr' => $i + 1, 'registration1_score' => $set[0], 'registration2_score' => $set[1],
            ]), $sets, array_keys($sets))));
            $fixtures[] = $fixture;
        }
        return [$group, new Collection($fixtures)];
    }

    public function test_extended_decider_counts_one_game_and_one_set_without_rewriting_points(): void
    {
        [$group, $fixtures] = $this->context([[1, 2, [[6, 2], [2, 6], [12, 10]]]]);
        $rows = collect(app(StandingsService::class)->forGroup($group, $fixtures))->keyBy('reg_id');
        $this->assertSame([9, 8, 2, 1, 2], [$rows[1]['games_won'], $rows[1]['games_lost'], $rows[1]['sets_won'], $rows[1]['sets_lost'], $rows[1]['points']]);
        $this->assertSame([8, 9, 1, 2, 0], [$rows[2]['games_won'], $rows[2]['games_lost'], $rows[2]['sets_won'], $rows[2]['sets_lost'], $rows[2]['points']]);
        $this->assertSame([12, 10], [$fixtures[0]->fixtureResults[2]->registration1_score, $fixtures[0]->fixtureResults[2]->registration2_score]);
    }

    public function test_third_set_winner_on_away_side_receives_the_single_game(): void
    {
        [$group, $fixtures] = $this->context([[1, 2, [[6, 2], [2, 6], [18, 20]]]]);
        $rows = collect(app(StandingsService::class)->forGroup($group, $fixtures))->keyBy('reg_id');
        $this->assertSame([8, 9, 1, 2], [$rows[1]['games_won'], $rows[1]['games_lost'], $rows[1]['sets_won'], $rows[1]['sets_lost']]);
        $this->assertSame([9, 8, 2, 1], [$rows[2]['games_won'], $rows[2]['games_lost'], $rows[2]['sets_won'], $rows[2]['sets_lost']]);
    }

    public function test_three_way_tie_cannot_be_decided_by_extra_tiebreak_points(): void
    {
        [$group, $fixtures] = $this->context([
            [1, 2, [[6, 2], [2, 6], [10, 8]]],
            [2, 3, [[6, 2], [2, 6], [30, 28]]],
            [3, 1, [[6, 2], [2, 6], [12, 10]]],
        ]);
        $rows = app(StandingsService::class)->forGroup($group, $fixtures);
        foreach ($rows as $row) {
            $this->assertSame([17, 17, 3, 3, 2, '='], [$row['games_won'], $row['games_lost'], $row['sets_won'], $row['sets_lost'], $row['points'], $row['tiebreak']]);
        }
    }

    public function test_existing_other_match_tiebreak_preset_totals_are_not_reinterpreted(): void
    {
        [$group, $fixtures] = $this->context([[1, 2, [[6, 2], [2, 6], [12, 10]]]], TennisScoreFormat::BEST_OF_3_MATCH_TIEBREAK);
        $rows = collect(app(StandingsService::class)->forGroup($group, $fixtures))->keyBy('reg_id');
        $this->assertSame([20, 18], [$rows[1]['games_won'], $rows[1]['games_lost']]);
    }

    public function test_unfinished_retirement_tiebreak_keeps_points_without_adding_games_or_a_set(): void
    {
        [$group, $fixtures] = $this->context([[1, 2, [[6, 2], [2, 6], [4, 3]]]]);
        $fixtures[0]->match_status = \App\Domain\Draws\Enums\FixtureState::STATUS_RETIRED;
        $fixtures[0]->winner_registration = 2;
        $fixtures[0]->fixtureResults[0]->winner_registration = 1;
        $fixtures[0]->fixtureResults[1]->winner_registration = 2;
        $rows = collect(app(StandingsService::class)->forGroup($group, $fixtures))->keyBy('reg_id');
        $this->assertSame([8, 8, 1, 1, 0], [$rows[1]['games_won'], $rows[1]['games_lost'], $rows[1]['sets_won'], $rows[1]['sets_lost'], $rows[1]['points']]);
        $this->assertSame(2, $rows[2]['points']);
        $this->assertSame([4, 3], [$fixtures[0]->fixtureResults[2]->registration1_score, $fixtures[0]->fixtureResults[2]->registration2_score]);
    }
}
