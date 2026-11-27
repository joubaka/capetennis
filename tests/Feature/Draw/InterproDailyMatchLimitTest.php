<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, EventType, Fixture, OrderOfPlay, Player, Registration};
use App\Services\Draw\InterproRoundRobinFormat;
use App\Services\Scheduling\{DailyMatchLimit, InterproDailyMatchGraph};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterproDailyMatchLimitTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $type = \Illuminate\Support\Facades\DB::table('eventtypes')->insertGetId(['name' => 'Fictional Interpro Trials',
            'type' => EventType::INDIVIDUAL, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['eventType' => $type, 'start_date' => '2026-11-27', 'end_date' => '2026-11-29']);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => false, 'oop_published' => false, 'locked' => false]);
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'preset_key' => InterproRoundRobinFormat::PRESET,
            'playoff_config' => InterproRoundRobinFormat::configuration() + ['challenge_playoffs' => true, 'challenge_pairs' => [2, 4, 6, 8]],
            'score_format' => 'one_full_set', 'boxes' => 4]);
        $members = [];
        foreach (['A', 'B', 'C', 'D'] as $name) {
            $group = $draw->groups()->create(['name' => $name]);
            foreach (range(1, 4) as $seed) {
                $registration = Registration::factory()->create();
                $registration->players()->attach(Player::factory()->create()->id);
                $group->registrations()->attach($registration->id, ['seed' => $seed]);
                $members[$name][] = $registration;
            }
        }
        app(InterproRoundRobinFormat::class)->prepare($draw->fresh());
        return [$event, $draw->fresh(), $members];
    }

    private function reserveBase(DailyMatchLimit $limit, Draw $draw): array
    {
        $fixtures = $draw->drawFixtures()->where('stage', '!=', 'RR')->get()->keyBy('match_nr');
        foreach ($fixtures as $number => $fixture) {
            $date = in_array($number, [2003, 3009, 3011, 5002, 5004, 5006, 5008], true) ? '2026-11-29' : '2026-11-28';
            $this->assertNull($limit->error('individual:'.$fixture->id, $date), 'Match '.$number.' on '.$date);
            $limit->reserve('individual:'.$fixture->id, $date);
        }
        return $fixtures->all();
    }

    public function test_unresolved_interpro_base_and_challenges_fit_three_per_day_and_fourth_is_rejected(): void
    {
        [$event, $draw] = $this->context();
        $limit = DailyMatchLimit::load($event);
        $fixtures = $this->reserveBase($limit, $draw);
        foreach ([3009, 3011] as $number) {
            $this->assertStringContainsString('Maximum 3 matches', $limit->error('individual:'.$fixtures[$number]->id, '2026-11-28'));
        }
        $this->assertSame(0, OrderOfPlay::count());
        $this->assertFalse((bool) $draw->fresh()->oop_published);
    }

    public function test_round_robin_appearances_and_other_registration_for_same_profile_are_added_to_playoff_path(): void
    {
        [$event, $draw, $members] = $this->context();
        $profile = $members['A'][0]->players()->first();
        $otherDraw = Draw::factory()->create(['event_id' => $event->id]);
        $alias = Registration::factory()->create();
        $alias->players()->attach($profile->id);
        $extra = Fixture::factory()->create(['draw_id' => $otherDraw->id, 'stage' => 'RR', 'registration1_id' => $alias->id,
            'registration2_id' => Registration::factory()->create()->id]);
        $limit = DailyMatchLimit::load($event);
        $this->reserveBase($limit, $draw);
        $this->assertStringContainsString('Maximum 3 matches', $limit->error('individual:'.$extra->id, '2026-11-28'));
        $this->assertNull($limit->error('individual:'.$extra->id, '2026-11-29'));
        $rr = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR', 'registration1_id' => $members['A'][0]->id,
            'registration2_id' => $members['A'][1]->id]);
        $limit = DailyMatchLimit::load($event);
        $this->reserveBase($limit, $draw);
        $this->assertStringContainsString('Maximum 3 matches', $limit->error('individual:'.$rr->id, '2026-11-28'));
    }

    public function test_incomplete_topology_and_foreign_group_sources_fail_closed(): void
    {
        [$event, $draw] = $this->context();
        $fixture = $draw->drawFixtures()->where('match_nr', 3001)->firstOrFail();
        $foreignDraw = Draw::factory()->create();
        $foreignGroup = $foreignDraw->groups()->create(['name' => 'A']);
        $fixture->update(['registration1_source_group_id' => $foreignGroup->id]);
        try {
            DailyMatchLimit::load($event);
            $this->fail('Foreign group source was accepted.');
        } catch (\InvalidArgumentException $error) {
            $this->assertStringContainsString('qualifier sources', $error->getMessage());
        }
        $fixture->delete();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('complete Interpro playoff fixtures');
        DailyMatchLimit::load($event);
    }

    public function test_automatic_nodes_keep_ancestry_but_do_not_add_an_appearance(): void
    {
        [$event, $draw] = $this->context();
        $fixtures = $draw->drawFixtures()->get();
        $node = $fixtures->firstWhere('match_nr', 3005);
        $node->match_status = 5;
        $graph = InterproDailyMatchGraph::build($draw, $fixtures);
        $bookings = [];
        foreach ([3001, 3005, 3007, 3009] as $number) $bookings['individual:'.$graph[$number]['id']] = '2026-11-28';
        $counts = DailyMatchLimit::pathCounts($graph, $bookings, '2026-11-28', []);
        $this->assertSame(3, max($counts));
        $this->assertSame([['type' => 'winner', 'match' => 3001], ['type' => 'winner', 'match' => 3002]], $graph[3005]['sources']);
    }

    public function test_unresolved_other_format_still_requires_resolved_players(): void
    {
        [$event] = $this->context();
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => null, 'registration2_id' => null,
            'winner_registration' => null]);
        $this->assertStringContainsString('Resolve the players', DailyMatchLimit::load($event)->error('individual:'.$fixture->id, '2026-11-28'));
    }

    public function test_stale_carried_result_cannot_remove_a_required_appearance(): void
    {
        [$event, $draw] = $this->context();
        $fixture = $draw->drawFixtures()->where('match_nr', 3005)->firstOrFail();
        $config = $draw->settings->playoff_config;
        $config[0]['trials_carries'][$fixture->id] = ['source' => 999999, 'fingerprint' => 'stale'];
        $draw->settings()->update(['playoff_config' => $config]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('original carried Interpro result');
        DailyMatchLimit::load($event);
    }

    public function test_concrete_foreign_playoff_player_cannot_disappear_from_the_path_counter(): void
    {
        [$event, $draw] = $this->context();
        $draw->drawFixtures()->where('match_nr', 3005)->update(['registration1_id' => Registration::factory()->create()->id]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('playoff participants');
        DailyMatchLimit::load($event);
    }
}
