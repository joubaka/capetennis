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

    private function context(?Event $event = null): array
    {
        $type = \Illuminate\Support\Facades\DB::table('eventtypes')->insertGetId(['name' => 'Fictional Interpro Trials',
            'type' => EventType::INDIVIDUAL, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event ??= Event::factory()->create(['eventType' => $type, 'start_date' => '2026-11-27', 'end_date' => '2026-11-29']);
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

    private function batch(array $draws, \App\Models\Venue $venue, array $numbers, string $start, string $end): array
    {
        $rounds = array_values(array_unique(array_map(fn ($number) => in_array($number, [3005, 3006, 4001, 4002], true) ? 5 : 4, $numbers)));
        $fixtures = Fixture::whereIn('draw_id', array_map(fn ($draw) => $draw->id, $draws))->where('stage', '!=', 'RR')->whereIn('match_nr', $numbers)->get();
        return ['start' => '2026-11-28 '.$start, 'end' => '2026-11-28 '.$end, 'duration' => 75, 'court_gap' => 30, 'player_rest' => 30,
            'day_batch' => true, 'full_day_scope' => '2026-11-28', 'limit_daily_matches' => true,
            'draw_ids' => array_map(fn ($draw) => $draw->id, $draws), 'draw_order' => array_map(fn ($draw) => $draw->id, $draws), 'draw_order_mode' => 'mixed',
            'selected_fixture_keys' => $fixtures->map(fn ($fixture) => 'individual:'.$fixture->id)->all(),
            'draw_rounds' => array_map(fn ($draw) => ['draw_id' => $draw->id, 'rounds' => $rounds], $draws),
            'venue_ids' => [$venue->id], 'day_court_labels' => [$venue->id => array_map('strval', range(1, 10))]];
    }

    private function scheduleContext(): array
    {
        [$event, $boys, $boysMembers] = $this->context();
        [, $girls, $girlsMembers] = $this->context($event);
        $venue = \App\Models\Venue::forceCreate(['name' => 'Fictional ten-court playoff venue']);
        $event->venues()->attach($venue->id, ['num_courts' => 10]);
        foreach ([$boys, $girls] as $index => $draw) {
            $draw->venues()->attach($venue->id, ['num_courts' => 10]);
            app(\App\Domain\Draws\Services\RoundRobinGenerationService::class)->generate($draw);
            $court = [];
            foreach ($draw->drawFixtures()->where('stage', 'RR')->get() as $fixture) {
                $round = (int) $fixture->round;
                $court[$round] = ($court[$round] ?? 0) + 1;
                $start = \Carbon\Carbon::parse('2026-11-27 13:00')->addMinutes(($round - 1) * 90 + $index * 45);
                OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id,
                    'court' => (string) $court[$round], 'time' => $start, 'duration_minutes' => 45, 'gap_minutes' => 0]);
            }
        }
        return [$event, $boys, $girls, $venue, $boysMembers, $girlsMembers];
    }

    public function test_both_draws_save_eight_openers_then_ten_disjoint_second_wave_matches(): void
    {
        [$event, $boys, $girls, $venue] = $this->scheduleContext();
        $service = app(\App\Services\Scheduling\EventVenueScheduleService::class);
        $firstOptions = $this->batch([$boys, $girls], $venue, [3001, 3002, 3003, 3004], '08:00', '09:15');
        $first = $service->preview($event, $firstOptions);
        $this->assertSame([], $first['unscheduled']);
        $this->assertCount(8, $first['matches']);
        $this->assertSame(8, $service->apply($event, $firstOptions, $first['revision'])['count']);
        $options = $this->batch([$boys, $girls], $venue, [2001, 2002, 3005, 3006, 4001], '09:45', '11:00');
        $preview = $service->preview($event, $options);
        $this->assertSame([], $preview['unscheduled']);
        $this->assertCount(10, $preview['matches']);
        $this->assertSame(['2026-11-28 09:45:00'], collect($preview['matches'])->pluck('scheduled_at')->unique()->values()->all());
        $this->assertCount(10, collect($preview['matches'])->pluck('court')->unique());
        // A separately saved championship batch must not block its disjoint lower siblings.
        $mainOptions = $this->batch([$boys, $girls], $venue, [2001, 2002], '09:45', '11:00');
        $mainPreview = $service->preview($event, $mainOptions);
        $this->assertCount(4, $mainPreview['matches']);
        $this->assertSame(4, $service->apply($event, $mainOptions, $mainPreview['revision'])['count']);
        $lowerOptions = $this->batch([$boys, $girls], $venue, [3005, 3006, 4001], '09:45', '11:00');
        $lowerPreview = $service->preview($event, $lowerOptions);
        $this->assertSame([], $lowerPreview['unscheduled']);
        $this->assertCount(6, $lowerPreview['matches']);
        $this->assertSame(['2026-11-28 09:45:00'], collect($lowerPreview['matches'])->pluck('scheduled_at')->unique()->values()->all());
        $this->assertSame(6, $service->apply($event, $lowerOptions, $lowerPreview['revision'])['count']);
        $this->assertSame(66, OrderOfPlay::count());
        $this->assertSame(48, OrderOfPlay::whereDate('time', '2026-11-27')->count());
        $this->assertFalse((bool) $boys->fresh()->oop_published);
        $this->assertFalse((bool) $girls->fresh()->oop_published);
        // Saved first-round dependencies still enforce the full thirty-minute rest.
        $early = $this->batch([$boys, $girls], $venue, [4002], '09:15', '10:30');
        $blocked = $service->preview($event, $early);
        $this->assertSame([], $blocked['matches']);
        $this->assertCount(2, $blocked['unscheduled']);
    }

    public function test_same_person_in_another_draw_still_reserves_their_time(): void
    {
        [$event, $boys, $girls, $venue, $boysMembers, $girlsMembers] = $this->scheduleContext();
        $girlsMembers['A'][0]->players()->sync([$boysMembers['A'][0]->players()->first()->id]);
        $service = app(\App\Services\Scheduling\EventVenueScheduleService::class);
        $options = $this->batch([$boys, $girls], $venue, [2001, 2002], '08:00', '09:15');
        $preview = $service->preview($event, $options);
        $this->assertNotEmpty($preview['unscheduled']);
        $this->assertLessThan(4, count($preview['matches']));
        $this->assertSame(48, OrderOfPlay::count());
    }

    public function test_malformed_links_and_duplicate_player_profiles_cannot_claim_disjoint_paths(): void
    {
        [, $draw, $members] = $this->context();
        $fixtures = $draw->drawFixtures()->get();
        $proof = \App\Services\Scheduling\InterproParticipantGroups::class;
        $this->assertSame('interpro-draw-'.$draw->id, $proof::forDraw($draw, $fixtures));
        $fixtures->firstWhere('match_nr', 3001)->parent_fixture_id = null;
        $this->assertNull($proof::forDraw($draw, $fixtures));
        $fixtures = $draw->drawFixtures()->get();
        $fixtures->firstWhere('match_nr', 2001)->registration1_id = $members['A'][0]->id;
        $this->assertNull($proof::forDraw($draw, $fixtures));
        $members['B'][0]->players()->sync([$members['A'][0]->players()->first()->id]);
        $this->assertNull($proof::forDraw($draw, $draw->drawFixtures()->get()));
    }
}
