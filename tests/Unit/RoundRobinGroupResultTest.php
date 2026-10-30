<?php

namespace Tests\Unit;

use App\Domain\Draws\Services\StandingsService;
use App\Models\{Draw, DrawGroup, DrawGroupRegistration, DrawSetting, Fixture, FixtureResult};
use App\Services\Draw\RoundRobinGroupResultService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class RoundRobinGroupResultTest extends TestCase
{
    private function state(bool $timed = false, bool $tied = false): array
    {
        $draw = new Draw(['id' => 10]);
        $draw->setRelation('settings', new DrawSetting(['workflow' => 'round_robin', 'num_sets' => 1, 'score_format' => $timed ? 'timed' : null]));
        $group = new DrawGroup(['name' => 'A']);
        $group->id = 1;
        $group->setRelation('groupRegistrations', new Collection([new DrawGroupRegistration(['registration_id' => 1]), new DrawGroupRegistration(['registration_id' => 2])]));
        $fixture = new Fixture(['stage' => 'RR', 'draw_group_id' => 1, 'registration1_id' => 1, 'registration2_id' => 2]);
        $fixture->setRelation('draw', $draw);
        $fixture->setRelation('fixtureResults', new Collection([new FixtureResult(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => $tied ? 6 : 2])]));
        $draw->setRelation('groups', new Collection([$group]));
        $draw->setRelation('drawFixtures', new Collection([$fixture]));
        $rows = [
            ['reg_id' => 1, 'wins' => $tied ? 0 : 1, 'points' => $tied ? 1 : 2, 'sets_won' => $tied ? 0 : 1, 'sets_lost' => 0, 'games_won' => 6, 'games_lost' => $tied ? 6 : 2],
            ['reg_id' => 2, 'wins' => 0, 'points' => $tied ? 1 : 0, 'sets_won' => 0, 'sets_lost' => $tied ? 0 : 1, 'games_won' => $tied ? 6 : 2, 'games_lost' => 6],
        ];
        return [$draw, [1 => $rows]];
    }

    public function test_complete_pairs_confirm_winner_but_missing_duplicate_and_incomplete_pairs_do_not(): void
    {
        [$draw, $standings] = $this->state();
        $service = app(RoundRobinGroupResultService::class);
        $this->assertTrue($service->annotate($draw, $standings)[1][0]['group_winner']);
        $fixture = $draw->drawFixtures->first();
        $draw->setRelation('drawFixtures', new Collection([$fixture, clone $fixture]));
        $this->assertSame('incomplete', $service->annotate($draw, $standings)[1][0]['group_result_status']);
        $draw->setRelation('drawFixtures', new Collection());
        $this->assertFalse($service->annotate($draw, $standings)[1][0]['group_winner']);
        $draw->setRelation('drawFixtures', new Collection([$fixture]));
        $fixture->setRelation('fixtureResults', new Collection());
        $this->assertFalse($service->annotate($draw, $standings)[1][0]['group_winner']);
    }

    public function test_timed_equal_totals_are_complete_but_unresolved(): void
    {
        [$draw, $standings] = $this->state(true, true);
        $rows = app(RoundRobinGroupResultService::class)->annotate($draw, $standings)[1];
        $this->assertSame('tied', $rows[0]['group_result_status']);
        $this->assertFalse($rows[0]['group_winner']);
    }

    public function test_equal_three_way_leaders_are_unresolved_while_two_way_head_to_head_resolves(): void
    {
        [$draw, $standings] = $this->state();
        $row = ['wins' => 1, 'sets_won' => 1, 'sets_lost' => 1, 'games_won' => 6, 'games_lost' => 6];
        $rows = array_map(fn ($id) => $row + ['reg_id' => $id], [1, 2]);
        $service = app(StandingsService::class);
        $this->assertTrue($service->hasUniqueLeader($draw, $rows, $draw->drawFixtures));
        $rows[] = $row + ['reg_id' => 3];
        $this->assertFalse($service->hasUniqueLeader($draw, $rows, $draw->drawFixtures));
    }
}
