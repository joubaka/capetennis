<?php

namespace Tests\Unit;

use App\Domain\Draws\Services\StandingsService;
use App\Models\{Draw, DrawGroup, DrawGroupRegistration, DrawSetting, Fixture, FixtureResult, Player, Registration};
use App\Services\Draw\TwoStageRoundRobinService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class RoundRobinHeadToHeadTieBreakTest extends TestCase
{
    private function context(array $matches): array
    {
        $draw = new Draw();
        $draw->id = 10;
        $draw->setRelation('settings', new DrawSetting(['workflow' => 'round_robin', 'score_format' => 'best_of_3_full_start_2', 'num_sets' => 3]));
        $group = new DrawGroup(['name' => 'A', 'draw_id' => 10]);
        $group->id = 20;
        $group->setRelation('draw', $draw);
        $members = [];
        foreach (range(1, 4) as $id) {
            $registration = new Registration();
            $registration->id = $id;
            $registration->setRelation('players', new Collection([new Player(['name' => 'Fictional '.$id, 'surname' => 'Player'])]));
            $member = new DrawGroupRegistration(['registration_id' => $id]);
            $member->setRelation('registration', $registration);
            $members[] = $member;
        }
        $group->setRelation('groupRegistrations', new Collection($members));
        $fixtures = [];
        foreach ($matches as $index => [$home, $away, $sets]) {
            $fixture = new Fixture(['draw_id' => 10, 'draw_group_id' => 20, 'stage' => 'RR', 'registration1_id' => $home, 'registration2_id' => $away]);
            $fixture->id = $index + 100;
            $fixture->setRelation('draw', $draw);
            $fixture->setRelation('fixtureResults', new Collection(array_map(fn ($set, $index) => new FixtureResult([
                'set_nr' => $index + 1, 'registration1_score' => $set[0], 'registration2_score' => $set[1],
            ]), $sets, array_keys($sets))));
            $fixtures[] = $fixture;
        }
        $fixtures = new Collection($fixtures);
        $draw->setRelation('groups', new Collection([$group]));
        $draw->setRelation('drawFixtures', $fixtures);
        return [$draw, $group, $fixtures];
    }

    public function test_two_player_points_ties_use_head_to_head_before_better_set_and_game_percentages(): void
    {
        [$draw, $group, $fixtures] = $this->context([
            [1, 2, [[6, 4], [4, 6], [6, 4]]],
            [1, 3, [[6, 4], [4, 6], [6, 4]]],
            [4, 1, [[6, 2], [6, 2]]],
            [2, 3, [[6, 2], [6, 2]]],
            [2, 4, [[6, 2], [6, 2]]],
            [3, 4, [[6, 4], [4, 6], [6, 4]]],
        ]);
        $service = app(StandingsService::class);
        $rows = $service->forGroup($group, $fixtures);
        $this->assertSame([1, 2, 3, 4], array_column($rows, 'reg_id'));
        $this->assertSame([4, 4, 2, 2], array_column($rows, 'points'));
        $this->assertLessThan($rows[1]['sets_won'] / ($rows[1]['sets_won'] + $rows[1]['sets_lost']), $rows[0]['sets_won'] / ($rows[0]['sets_won'] + $rows[0]['sets_lost']));
        $this->assertLessThan($rows[1]['games_won'] / ($rows[1]['games_won'] + $rows[1]['games_lost']), $rows[0]['games_won'] / ($rows[0]['games_won'] + $rows[0]['games_lost']));
        $this->assertSame(['H2H', 'H2H', 'H2H', 'H2H'], array_column($rows, 'tiebreak'));
        $this->assertTrue($service->hasUniqueLeader($draw, $rows, $fixtures));
        $this->assertTrue(app(TwoStageRoundRobinService::class)->positionsResolved($draw, $rows, $fixtures));
        $this->assertFalse($service->hasUniqueLeader($draw, [$rows[1], $rows[0]], $fixtures));
    }

    public function test_three_player_points_tie_uses_set_percentage_not_pairwise_head_to_head(): void
    {
        [$draw, $group, $fixtures] = $this->context([
            [1, 2, [[6, 2], [6, 2]]], [2, 3, [[6, 2], [6, 2]]],
            [3, 1, [[6, 2], [2, 6], [6, 2]]],
            [1, 4, [[6, 2], [6, 2]]], [2, 4, [[6, 2], [6, 2]]],
            [3, 4, [[6, 2], [2, 6], [6, 2]]],
        ]);
        $rows = app(StandingsService::class)->forGroup($group, $fixtures);
        $this->assertSame([1, 2, 3, 4], array_column($rows, 'reg_id'));
        $this->assertSame([4, 4, 4, 0], array_column($rows, 'points'));
        $this->assertSame(['Sets %', 'Sets %', 'Sets %'], array_column(array_slice($rows, 0, 3), 'tiebreak'));
        $this->assertTrue(app(TwoStageRoundRobinService::class)->positionsResolved($draw, $rows, $fixtures));
    }

    public function test_three_equal_set_percentages_use_games_percentage_before_head_to_head(): void
    {
        [$draw, $group, $fixtures] = $this->context([
            [1, 2, [[6, 2], [6, 2]]], [2, 3, [[6, 3], [6, 3]]], [3, 1, [[6, 4], [6, 4]]],
            [1, 4, [[6, 2], [6, 2]]], [2, 4, [[6, 2], [6, 2]]], [3, 4, [[6, 2], [6, 2]]],
        ]);
        $rows = app(StandingsService::class)->forGroup($group, $fixtures);
        $this->assertSame([1, 2, 3, 4], array_column($rows, 'reg_id'));
        $this->assertSame(['Games %', 'Games %', 'Games %'], array_column(array_slice($rows, 0, 3), 'tiebreak'));
        $this->assertTrue(app(TwoStageRoundRobinService::class)->positionsResolved($draw, $rows, $fixtures));
    }

    public function test_completely_equal_circular_three_way_tie_stays_unresolved(): void
    {
        [$draw, $group, $fixtures] = $this->context([
            [1, 2, [[6, 2], [6, 2]]], [2, 3, [[6, 2], [6, 2]]], [3, 1, [[6, 2], [6, 2]]],
            [1, 4, [[6, 2], [6, 2]]], [2, 4, [[6, 2], [6, 2]]], [3, 4, [[6, 2], [6, 2]]],
        ]);
        $service = app(StandingsService::class);
        $rows = $service->forGroup($group, $fixtures);
        $this->assertSame(['=', '=', '='], array_column(array_slice($rows, 0, 3), 'tiebreak'));
        $this->assertFalse($service->hasUniqueLeader($draw, $rows, $fixtures));
        $this->assertFalse(app(TwoStageRoundRobinService::class)->positionsResolved($draw, $rows, $fixtures));
        $comparator = (new \ReflectionMethod($service, 'comparator'))->invoke($service, $fixtures, $rows);
        foreach ([[$rows[0], $rows[1]], [$rows[1], $rows[2]], [$rows[2], $rows[0]]] as [$a, $b]) $this->assertSame(0, $comparator($a, $b));
    }

    public function test_lower_position_checks_keep_the_full_three_way_points_cohort(): void
    {
        [$draw, , $fixtures] = $this->context([[3, 2, [[6, 2], [6, 2]]]]);
        $rows = [
            ['reg_id' => 1, 'points' => 4, 'sets_won' => 3, 'sets_lost' => 1, 'games_won' => 36, 'games_lost' => 20],
            ['reg_id' => 2, 'points' => 4, 'sets_won' => 2, 'sets_lost' => 2, 'games_won' => 30, 'games_lost' => 20],
            ['reg_id' => 3, 'points' => 4, 'sets_won' => 2, 'sets_lost' => 2, 'games_won' => 20, 'games_lost' => 30],
        ];
        $this->assertTrue(app(TwoStageRoundRobinService::class)->positionsResolved($draw, $rows, $fixtures));
    }
}
