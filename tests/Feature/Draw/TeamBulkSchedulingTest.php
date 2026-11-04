<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Player, TeamFixture, Venue};
use App\Services\Scheduling\{EventVenueScheduleService, TeamDisciplineOrder, UnifiedTeamScheduleService};
use App\Services\TeamScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamBulkSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(Draw $draw, string $code, int $round = 1): TeamFixture
    {
        $pair = str_contains($code, 'double');
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'round_nr' => $round, 'match_nr' => 1,
            'fixture_type' => 1, 'rubber_code' => $code, 'rubber_sequence' => 1, 'player_count_per_team' => $pair ? 2 : 1]);
        foreach (range(1, $pair ? 2 : 1) as $slot) {
            $players = Player::factory()->count(2)->create();
            $fixture->fixturePlayers()->create(['slot_no' => $slot, 'team1_id' => $players[0]->id, 'team2_id' => $players[1]->id]);
        }
        return $fixture;
    }

    private function scenario(): array
    {
        $event = Event::factory()->create(['start_date' => '2026-10-09', 'end_date' => '2026-10-09']);
        $draws = collect(); $rounds = []; $venues = [];
        foreach ([10, 12] as $age) {
            $venue = Venue::forceCreate(['name' => 'Under '.$age.' courts']);
            $venues[$age] = $venue->id;
            foreach (['singles', 'reverse_singles', 'doubles', 'mixed_doubles'] as $index => $code) {
                $draw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'Under '.$age.' '.str_replace('_', ' ', $code)]);
                $draw->forceFill(['team_category_id' => 1, 'team_draw_selection' => ['rubber_code' => $code]])->save();
                $draw->venues()->attach($venue->id, ['num_courts' => 4]);
                $draws->push($draw);
                $this->fixture($draw, $code);
                if ($index === 0) $this->fixture($draw, $code);
                $rounds[] = ['draw_id' => $draw->id, 'round' => 1, 'day' => 1, 'sequence' => $index + 1];
            }
        }
        $options = ['draw_ids' => $draws->pluck('id')->all(), 'start' => '2026-10-09 08:00:00', 'end' => '2026-10-09 18:00:00',
            'duration' => 30, 'player_rest' => 0, 'court_gap' => 0,
            'programme' => ['days' => [['start' => '2026-10-09 08:00:00', 'end' => '2026-10-09 18:00:00']], 'rounds' => $rounds]];
        return [$event, $draws, $venues, $options];
    }

    public function test_bulk_keeps_age_venues_and_waits_for_every_prior_discipline_independently(): void
    {
        [$event, $draws, $venues, $options] = $this->scenario();
        $first = TeamFixture::where('draw_id', $draws[0]->id)->firstOrFail();
        $first->update(['scheduled_at' => '2026-10-09 08:00:00', 'duration_min' => 90, 'venue_id' => $venues[10], 'court_label' => '1']);
        $foreign = Draw::factory()->create(['drawName' => 'Under 10 singles']);
        $this->fixture($foreign, 'singles');
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $this->assertCount(9, $preview['matches']);
        $this->assertSame([], $preview['unscheduled']);
        foreach ($preview['matches'] as $row) {
            $age = app(\App\Services\Scheduling\ScheduleProgramme::class)->age($draws->firstWhere('id', $row['draw_id']));
            $this->assertSame($venues[$age], $row['venue_id']);
        }
        $byDraw = collect($preview['matches'])->groupBy('draw_id');
        $this->assertSame('2026-10-09 09:30:00', $byDraw[$draws[1]->id][0]['scheduled_at']);
        $this->assertSame('2026-10-09 08:30:00', $byDraw[$draws[5]->id][0]['scheduled_at']);
        foreach ([0, 4] as $offset) {
            foreach ([1, 2] as $phase) {
                $this->assertGreaterThanOrEqual(
                    strtotime($byDraw[$draws[$offset + $phase]->id][0]['scheduled_at']) + 1800,
                    strtotime($byDraw[$draws[$offset + $phase + 1]->id][0]['scheduled_at']));
            }
        }
        $this->assertSame(9, $service->apply($event, $options, $preview['revision'])['count']);
        $this->assertCount(0, $service->preview($event, $options)['matches']);
        $stored = json_decode(DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true);
        foreach ([10, 12] as $age) {
            $this->assertCount(4, $stored['programme_settings'][(string) $age]['programme']['rounds']);
            $ageIds = $draws->filter(fn ($draw) => str_starts_with($draw->drawName, 'Under '.$age.' '))->pluck('id')->all();
            $this->assertEqualsCanonicalizing($ageIds, array_column($stored['programme_settings'][(string) $age]['programme']['rounds'], 'drawId'));
        }
        $this->assertSame('08:00', $first->fresh()->scheduled_at->format('H:i'));
        $this->assertNull($foreign->fixtures()->first()->scheduled_at);
    }

    public function test_partial_selection_cannot_bypass_unscheduled_singles(): void
    {
        [$event, $draws, , $options] = $this->scenario();
        unset($options['programme']);
        $options['draw_ids'] = [$draws[2]->id];
        $preview = app(EventVenueScheduleService::class)->preview($event, $options);
        $this->assertCount(0, $preview['matches']);
        $this->assertCount(1, $preview['unscheduled']);
        $this->assertStringContainsString('must be scheduled first', $preview['unscheduled'][0]['reason']);
    }

    public function test_manual_order_and_venue_bypasses_are_rejected_and_earlier_moves_protect_successors(): void
    {
        [$event, $draws, $venues, $options] = $this->scenario();
        $service = app(UnifiedTeamScheduleService::class);
        $doubles = $draws[2]->fixtures()->firstOrFail();
        $data = ['venue_id' => $venues[10], 'court' => '1', 'scheduled_at' => '2026-10-09 08:00:00', 'duration' => 30];
        $this->assertStringContainsString('earlier disciplines', $service->manualError($event, $doubles, $data));
        $this->assertStringContainsString('assigned', $service->manualError($event, $doubles, array_replace($data, ['venue_id' => $venues[12]])));
        $planner = app(EventVenueScheduleService::class);
        $preview = $planner->preview($event, $options);
        $planner->apply($event, $options, $preview['revision']);
        $singles = $draws[0]->fixtures()->firstOrFail();
        $this->assertStringContainsString('later discipline', $service->manualError($event, $singles, array_replace($data, ['scheduled_at' => '2026-10-09 10:00:00'])));
        $this->assertNotNull($service->removalError($event, [$singles->id]));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(TeamScheduleService::class)->save($draws[0], ['fixture_id' => $singles->id, 'scheduled_at' => '2026-10-09 10:00:00']);
    }

    public function test_composite_ties_use_codes_over_sequence_without_round_dependency_cycles(): void
    {
        $event = Event::factory()->create(['start_date' => '2026-10-09', 'end_date' => '2026-10-09']);
        $venue = Venue::forceCreate(['name' => 'Composite courts']);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'Under 10 composite']);
        $draw->forceFill(['team_category_id' => 1])->save();
        $draw->venues()->attach($venue->id, ['num_courts' => 8]);
        foreach ([1, 2] as $round) foreach (['mixed_doubles', 'doubles', 'reverse_singles', 'singles'] as $code) $this->fixture($draw, $code, $round);
        $preview = app(EventVenueScheduleService::class)->preview($event, ['draw_ids' => [$draw->id], 'start' => '2026-10-09 08:00', 'end' => '2026-10-09 18:00', 'duration' => 30, 'wave_minutes' => 30, 'court_gap' => 0, 'player_rest' => 0, 'round_progression' => 'all_round',
            'programme' => ['days' => [['start' => '2026-10-09 08:00', 'end' => '2026-10-09 18:00']],
                'rounds' => [['draw_id' => $draw->id, 'round' => 1, 'day' => 1, 'sequence' => 1], ['draw_id' => $draw->id, 'round' => 2, 'day' => 1, 'sequence' => 2]]]]);
        $this->assertCount(8, $preview['matches'], json_encode($preview['unscheduled']));
        $this->assertSame([], $preview['unscheduled']);
        $phaseOrder = app(TeamDisciplineOrder::class);
        $phases = collect($preview['matches'])->groupBy(fn ($row) => $phaseOrder->phase(TeamFixture::find($row['fixture_id'])));
        foreach ([1, 2, 3] as $phase) {
            $end = Carbon::parse($phases[$phase]->max('scheduled_at'))->addMinutes(30);
            $this->assertTrue($end->lte(Carbon::parse($phases[$phase + 1]->min('scheduled_at'))));
        }
    }

    public function test_legacy_bulk_auto_also_obeys_age_phases_and_venues(): void
    {
        [$event, $draws, $venues] = $this->scenario();
        $result = app(TeamScheduleService::class)->automatic($draws->reverse(), ['start' => '2026-10-09 08:00', 'end' => '2026-10-09 18:00', 'duration' => 30, 'gap' => 0]);
        $this->assertSame(10, $result['count']);
        foreach ([0, 4] as $offset) {
            $this->assertSame('08:30', $draws[$offset + 1]->fixtures()->first()->scheduled_at->format('H:i'));
            $this->assertSame('09:00', $draws[$offset + 2]->fixtures()->first()->scheduled_at->format('H:i'));
            $this->assertSame('09:30', $draws[$offset + 3]->fixtures()->first()->scheduled_at->format('H:i'));
        }
    }

    public function test_inverted_programmes_are_rejected(): void
    {
        [$event, $draws, , $options] = $this->scenario();
        $options['programme']['rounds'][0]['sequence'] = 5;
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must follow');
        app(EventVenueScheduleService::class)->preview($event, $options);
    }

    public function test_incomplete_age_is_rejected_without_writes(): void
    {
        [$event, $draws, , $options] = $this->scenario();
        $options['draw_ids'] = [$draws[0]->id];
        $options['programme']['rounds'] = [$options['programme']['rounds'][0]];
        try {
            app(EventVenueScheduleService::class)->preview($event, $options);
            $this->fail('An incomplete age group was accepted.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('every discipline', $exception->getMessage());
        }
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
    }

    public function test_foreign_draw_cannot_enter_the_bulk_selection(): void
    {
        [$event, , , $options] = $this->scenario();
        $options['draw_ids'][] = Draw::factory()->create()->id;
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('do not belong');
        app(EventVenueScheduleService::class)->preview($event, $options);
    }

    public function test_partial_earlier_phase_respects_saved_external_successors(): void
    {
        [$event, $draws, $venues, $options] = $this->scenario();
        $later = $draws[2]->fixtures()->firstOrFail();
        $later->update(['scheduled_at' => '2026-10-09 09:00', 'duration_min' => 30, 'venue_id' => $venues[10], 'court_label' => '4']);
        unset($options['programme']);
        $options['draw_ids'] = [$draws[0]->id];
        $options['start'] = '2026-10-09 10:00';
        $preview = app(EventVenueScheduleService::class)->preview($event, $options);
        $this->assertCount(0, $preview['matches']);
        $this->assertCount(2, $preview['unscheduled']);
        $this->assertStringContainsString('saved later discipline', $preview['unscheduled'][0]['reason']);
        $this->assertSame('09:00', $later->fresh()->scheduled_at->format('H:i'));
    }

    public function test_legacy_reset_cannot_clear_earlier_disciplines_with_saved_external_successors(): void
    {
        [$event, $draws, $venues] = $this->scenario();
        TeamFixture::where('draw_id', $draws[0]->id)->update(['scheduled_at' => '2026-10-09 08:00', 'duration_min' => 30, 'venue_id' => $venues[10], 'court_label' => '1']);
        $draws[2]->fixtures()->first()->update(['scheduled_at' => '2026-10-09 09:00', 'duration_min' => 30, 'venue_id' => $venues[10], 'court_label' => '4']);
        try {
            app(TeamScheduleService::class)->automatic(collect([$draws[0]]), ['start' => '2026-10-09 10:00', 'end' => '2026-10-09 10:10', 'duration' => 30], true);
            $this->fail('Reset removed prerequisites of a saved later discipline.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertStringContainsString('later discipline', $exception->getMessage());
        }
        $this->assertSame(3, TeamFixture::whereNotNull('scheduled_at')->count());
    }

    public function test_playoff_singles_keep_source_mixed_as_a_dependency_without_a_reverse_cycle(): void
    {
        $event = Event::factory()->create();
        $source = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'Under 10 qualification']);
        $source->forceFill(['team_category_id' => 1, 'team_draw_selection' => ['competition' => ['phase' => 'round_robin']]])->save();
        $playoff = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'Under 10 placement']);
        $playoff->forceFill(['team_category_id' => 1, 'team_draw_selection' => ['competition' => ['phase' => 'playoff', 'source_draw_id' => $source->id]]])->save();
        $mixed = $this->fixture($source, 'mixed_doubles');
        $singles = $this->fixture($playoff, 'singles');
        $adapter = app(UnifiedTeamScheduleService::class);
        $this->assertContains('team:'.$mixed->id, $adapter->nodes($playoff, 'all_round')['team:'.$singles->id]['dependencies']);
        $this->assertNotContains('team:'.$singles->id, $adapter->nodes($source, 'all_round')['team:'.$mixed->id]['dependencies']);
    }
}
