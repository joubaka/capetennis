<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Player, TeamFixture, Venue};
use App\Services\Scheduling\EventVenueScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleProgrammeTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(int $rubbers = 1, int $courts = 1): array
    {
        $event = Event::factory()->create();
        $venue = new Venue();
        $venue->forceFill(['name' => 'Programme courts'])->save();
        $players = Player::factory()->count($rubbers * 2)->create();
        $rounds = []; $drawIds = [];
        foreach (['Singles', 'Singles Reverse', 'Doubles', 'Mixed doubles'] as $index => $discipline) {
            $draw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'u/10 Boys – '.$discipline, 'gender' => 'Boys']);
            $draw->forceFill(['team_category_id' => 1])->save();
            $draw->venues()->attach($venue->id, ['num_courts' => $courts]);
            $drawIds[] = $draw->id;
            foreach ([1, 2, 3] as $round) {
                foreach (range(1, $rubbers) as $number) {
                    $fixture = TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'round_nr' => $round, 'match_nr' => $number, 'rubber_sequence' => $number, 'player_count_per_team' => 1]);
                    $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => $players[($number - 1) * 2]->id, 'team2_id' => $players[($number - 1) * 2 + 1]->id]);
                }
                $day = $index === 0 ? 1 : ($index === 1 ? ($round === 1 ? 1 : 2) : ($index === 2 ? 2 : 3));
                $sequence = $index === 0 ? $round : ($index === 1 ? ($round === 1 ? 4 : $round - 1) : ($index === 2 ? $round + 2 : $round));
                $rounds[] = ['draw_id' => $draw->id, 'round' => $round, 'day' => $day, 'sequence' => $sequence];
            }
        }
        $days = array_map(fn ($day) => ['start' => '2026-10-'.$day.' 08:00:00', 'end' => '2026-10-'.$day.' 18:00:00'], ['09', '10', '11']);
        $options = ['start' => $days[0]['start'], 'end' => $days[2]['end'], 'duration' => 30, 'wave_minutes' => 30, 'court_gap' => 0, 'player_rest' => 0, 'draw_ids' => $drawIds, 'programme' => compact('days', 'rounds')];
        return [$event, $options];
    }

    public function test_complete_preview_uses_all_three_days_without_writes_and_applies_once(): void
    {
        [$event, $options] = $this->scenario();
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $this->assertCount(12, $preview['matches']);
        $this->assertSame([], $preview['unscheduled']);
        $this->assertSame([4, 5, 3], collect($preview['matches'])->groupBy(fn ($row) => substr($row['scheduled_at'], 0, 10))->map->count()->values()->all());
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
        $this->assertSame(12, $service->apply($event, $options, $preview['revision'])['count']);
        $saved = $service->preview($event, $options);
        $this->assertCount(0, $saved['matches']);
        $this->assertSame(12, TeamFixture::whereNotNull('scheduled_at')->count());
    }

    public function test_apply_retains_other_age_settings_and_round_venues_and_persists_programme_options(): void
    {
        [$event, $options] = $this->scenario();
        $retained = ['programme_settings' => ['13' => ['duration' => 85]], 'round_venue_setups' => [['draw_id' => 999, 'round' => 1, 'venue_ids' => [7]]]];
        $legacyDraw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'u/15 Boys Singles']);
        $retained['programme'] = ['days' => $options['programme']['days'], 'rounds' => [['drawId' => $legacyDraw->id, 'round' => 1, 'day' => 1, 'sequence' => 1]]];
        \Illuminate\Support\Facades\DB::table('event_venue_schedule_drafts')->insert(['event_id' => $event->id, 'options' => json_encode($retained), 'created_at' => now(), 'updated_at' => now()]);
        $options['gender_wave_release'] = 'court_ready';
        $options['programme']['days'][0]['gender_waves'] = 'girls_then_boys';
        $options['programme']['days'][0]['break_start'] = '2026-10-09 12:00:00';
        $options['programme']['days'][0]['break_end'] = '2026-10-09 13:00:00';
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $this->assertSame($retained, json_decode(\Illuminate\Support\Facades\DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true));
        $service->apply($event, $options, $preview['revision']);
        $saved = json_decode(\Illuminate\Support\Facades\DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true);
        $this->assertSame($retained['programme_settings']['13'], $saved['programme_settings']['13']);
        $this->assertSame($retained['programme'], $saved['programme_settings']['15']['programme']);
        $this->assertSame($retained['round_venue_setups'], $saved['round_venue_setups']);
        $this->assertSame($preview['input']['programme'], $saved['programme_settings']['10']['programme']);
        $this->assertSame('court_ready', $saved['programme_settings']['10']['gender_wave_release']);
        $this->assertSame(30, $saved['programme_settings']['10']['duration']);
    }

    public function test_replanning_saved_programme_uses_changed_day_start_only_after_apply(): void
    {
        [$event, $options] = $this->scenario();
        $service = app(EventVenueScheduleService::class);
        $initial = $service->preview($event, $options);
        $service->apply($event, $options, $initial['revision']);
        $before = TeamFixture::orderBy('id')->get()->map->getAttributes()->all();
        $fixed = $service->preview($event, $options);
        $this->assertSame([], $fixed['matches']);
        $this->assertSame($before, TeamFixture::orderBy('id')->get()->map->getAttributes()->all());
        $options['programme']['days'][0]['start'] = '2026-10-09 09:00:00';
        $options['start'] = '2026-10-09 09:00:00';
        $options['reschedule_existing'] = true;
        $options['replan_venue_ids'] = $event->draws()->with('venues')->get()->flatMap(fn ($draw) => $draw->venues->pluck('id'))->unique()->values()->all();
        $replanned = $service->preview($event, $options);
        $this->assertCount(12, $replanned['matches']);
        $this->assertSame('2026-10-09 09:00:00', $replanned['matches'][0]['scheduled_at']);
        $this->assertSame($before, TeamFixture::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame(12, $service->apply($event, $options, $replanned['revision'])['count']);
        $this->assertSame('2026-10-09 09:00:00', TeamFixture::orderBy('scheduled_at')->first()->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_six_court_programme_pipelines_sections_with_real_player_rest_and_gender_waves(): void
    {
        [$event, $options] = $this->scenario(8, 6);
        $girlsPlayers = Player::factory()->count(16)->create();
        foreach ($options['draw_ids'] as $sourceId) {
            $source = Draw::findOrFail($sourceId);
            $girls = Draw::factory()->create(['event_id' => $event->id, 'drawName' => str_replace('Boys', 'Girls', $source->drawName), 'gender' => 'Girls']);
            $girls->forceFill(['team_category_id' => 1])->save();
            $girls->venues()->attach($source->venues->first()->id, ['num_courts' => 6]);
            $options['draw_ids'][] = $girls->id;
            foreach (TeamFixture::where('draw_id', $sourceId)->get() as $sourceFixture) {
                $number = (int) $sourceFixture->match_nr;
                $fixture = TeamFixture::create(['draw_id' => $girls->id, 'fixture_type' => 1, 'round_nr' => $sourceFixture->round_nr,
                    'match_nr' => $number, 'rubber_sequence' => $number, 'player_count_per_team' => 1]);
                $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => $girlsPlayers[($number - 1) * 2]->id, 'team2_id' => $girlsPlayers[($number - 1) * 2 + 1]->id]);
            }
            foreach (collect($options['programme']['rounds'])->where('draw_id', $sourceId) as $round) $options['programme']['rounds'][] = array_replace($round, ['draw_id' => $girls->id]);
        }
        $regions = [\App\Models\TeamRegion::create(['region_name' => 'Home region', 'short_name' => 'HOME']), \App\Models\TeamRegion::create(['region_name' => 'Away region', 'short_name' => 'AWAY'])];
        TeamFixture::whereIn('draw_id', $options['draw_ids'])->update(['region1' => $regions[0]->id, 'region2' => $regions[1]->id]);
        $options['programme']['days'][0]['start'] = $options['start'] = '2026-10-09 08:30:00';
        $options += ['gender_waves' => 'girls_then_boys', 'gender_wave_release' => 'whole_wave', 'round_progression' => 'team_ready'];
        $options['duration'] = $options['wave_minutes'] = 45;
        $options['player_rest'] = 15;
        $preview = app(EventVenueScheduleService::class)->preview($event, $options);
        $dayOne = collect($preview['matches'])->where('programme_day', 1);
        $this->assertCount(62, $dayOne);
        $this->assertLessThanOrEqual('2026-10-09 17:15:00', $dayOne->max('scheduled_at'));
        foreach (collect($preview['matches'])->flatMap(fn ($row) => array_map(fn ($id) => ['id' => $id, 'at' => $row['scheduled_at']], $row['participant_ids']))->groupBy('id') as $bookings) {
            $previous = null;
            foreach ($bookings->sortBy('at') as $booking) {
                if ($previous) $this->assertGreaterThanOrEqual(3600, strtotime($booking['at']) - strtotime($previous));
                $previous = $booking['at'];
            }
        }
        foreach ($dayOne->groupBy('programme_sequence') as $section) {
            $girlsEnd = $section->filter(fn ($row) => str_contains($row['draw_name'], 'Girls'))->max('scheduled_at');
            $boysStart = $section->filter(fn ($row) => str_contains($row['draw_name'], 'Boys'))->min('scheduled_at');
            $this->assertGreaterThanOrEqual(2700, strtotime($boysStart) - strtotime($girlsEnd));
        }
        $options['gender_wave_release'] = 'court_ready';
        $continuous = app(EventVenueScheduleService::class)->preview($event, $options);
        $continuousDay = collect($continuous['matches'])->where('programme_day', 1);
        $this->assertCount(64, $continuousDay);
        $this->assertCount(0, collect($continuous['unscheduled'])->where('programme_day', 1));
        $this->assertLessThanOrEqual('2026-10-09 17:15:00', $continuousDay->max('scheduled_at'));
        foreach ($continuousDay->flatMap(fn ($row) => array_map(fn ($id) => ['id' => $id, 'at' => $row['scheduled_at']], $row['participant_ids']))->groupBy('id') as $bookings) {
            $previous = null;
            foreach ($bookings->sortBy('at') as $booking) {
                if ($previous) $this->assertGreaterThanOrEqual(3600, strtotime($booking['at']) - strtotime($previous));
                $previous = $booking['at'];
            }
        }
        foreach ($continuousDay->groupBy('programme_sequence') as $section) {
            $girlsStart = $section->filter(fn ($row) => str_contains($row['draw_name'], 'Girls'))->min('scheduled_at');
            $boysStart = $section->filter(fn ($row) => str_contains($row['draw_name'], 'Boys'))->min('scheduled_at');
            $this->assertGreaterThanOrEqual(2700, strtotime($boysStart) - strtotime($girlsStart));
        }
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
    }

    public function test_saved_next_section_allows_start_overlap_only_for_available_players_and_courts(): void
    {
        [$event, $options] = $this->scenario(1, 2);
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $service->apply($event, $options, $preview['revision']);
        $next = TeamFixture::where('draw_id', $options['draw_ids'][1])->where('round_nr', 1)->firstOrFail();
        $next->update(['scheduled_at' => '2026-10-09 09:00:00', 'match_status' => 1]);
        $options['replan_venue_ids'] = [$next->venue_id];
        $options['reschedule_existing'] = true;
        $shared = $service->preview($event, $options);
        $this->assertNotEmpty($shared['unscheduled']);
        $players = Player::factory()->count(2)->create();
        $next->fixturePlayers()->first()->update(['team1_id' => $players[0]->id, 'team2_id' => $players[1]->id]);
        $separate = $service->preview($event, $options);
        $this->assertCount(0, $separate['unscheduled']);
        $singlesThird = collect($separate['matches'])->where('draw_id', $options['draw_ids'][0])->where('round', 3)->first();
        $this->assertSame('2026-10-09 09:00:00', $singlesThird['scheduled_at']);
        $this->assertNotSame($next->court_label, $singlesThird['court']);
        $this->assertSame('2026-10-09 09:00:00', $next->fresh()->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_short_day_leaves_matches_unallocated_and_does_not_spill_overnight(): void
    {
        [$event, $options] = $this->scenario();
        $options['programme']['days'][0]['end'] = '2026-10-09 08:45:00';
        $preview = app(EventVenueScheduleService::class)->preview($event, $options);
        $this->assertNotEmpty($preview['unscheduled']);
        $this->assertCount(1, collect($preview['matches'])->filter(fn ($row) => str_starts_with($row['scheduled_at'], '2026-10-09')));
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
    }

    public function test_current_capacity_reason_replaces_resolved_programme_dependency_warning(): void
    {
        [$event, $options] = $this->scenario();
        $options['programme']['days'][0]['end'] = '2026-10-09 08:45:00';
        $preview = app(EventVenueScheduleService::class)->preview($event, $options);
        $drawId = $options['draw_ids'][0];
        $rows = collect($preview['unscheduled']);
        $capacity = $rows->where('draw_id', $drawId)->where('round', 2)->first();
        $this->assertNotNull($capacity);
        $this->assertStringContainsString('No valid court slot fits before programme Day 1 ends at 08:45', $capacity['reason']);
        $this->assertStringNotContainsString('must be scheduled first', $capacity['reason']);
        $downstream = $rows->where('draw_id', $drawId)->where('round', 3)->first();
        $this->assertStringContainsString('Earlier programme section '.Draw::findOrFail($drawId)->drawName.' round 2 (Day 1, order 2)', $downstream['reason']);
        $this->assertStringNotContainsString('qualifying', $downstream['reason']);
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
        $options['programme']['days'][0]['end'] = '2026-10-09 10:00:00';
        $extended = app(EventVenueScheduleService::class)->preview($event, $options);
        $this->assertSame([], $extended['unscheduled']);
        $this->assertCount(12, $extended['matches']);
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
    }

    public function test_draw_progression_blocker_identifies_its_source_without_programme(): void
    {
        [$event, $options] = $this->scenario();
        unset($options['programme']);
        $options['round_progression'] = 'all_round';
        $options['end'] = '2026-10-09 08:15:00';
        $preview = app(EventVenueScheduleService::class)->preview($event, $options);
        $drawId = $options['draw_ids'][0];
        $later = collect($preview['unscheduled'])->where('draw_id', $drawId)->where('round', 2)->first();
        $this->assertStringContainsString(Draw::findOrFail($drawId)->drawName.' round 1', $later['reason']);
        $this->assertStringContainsString('draw progression', $later['reason']);
        $this->assertStringNotContainsString('programme section', $later['reason']);
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
    }

    public function test_capacity_at_an_alternative_venue_is_not_reported_as_a_gender_dependency(): void
    {
        [$event, $options] = $this->scenario();
        $source = Draw::findOrFail($options['draw_ids'][0]);
        $girls = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'u/10 Girls – Singles', 'gender' => 'Girls']);
        $girls->forceFill(['team_category_id' => 1])->save();
        $girls->venues()->attach($source->venues->first()->id, ['num_courts' => 1]);
        $second = new Venue();
        $second->forceFill(['name' => 'Girls alternative court'])->save();
        $girls->venues()->attach($second->id, ['num_courts' => 1]);
        $options['draw_ids'][] = $girls->id;
        foreach ([1, 2, 3] as $round) {
            TeamFixture::create(['draw_id' => $girls->id, 'fixture_type' => 1, 'round_nr' => $round, 'match_nr' => $round]);
            $options['programme']['rounds'][] = ['draw_id' => $girls->id, 'round' => $round, 'day' => 1, 'sequence' => $round];
        }
        $options['gender_waves'] = 'boys_then_girls';
        $options['programme']['days'][0]['end'] = '2026-10-09 08:15:00';
        $preview = app(EventVenueScheduleService::class)->preview($event, $options);
        $first = collect($preview['unscheduled'])->where('draw_id', $girls->id)->where('round', 1)->first();
        $this->assertStringContainsString('No valid court slot fits', $first['reason']);
        $this->assertStringNotContainsString('gender wave', $first['reason']);
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
    }

    public function test_omitted_round_and_foreign_round_are_rejected(): void
    {
        [$event, $options] = $this->scenario();
        $foreign = Draw::factory()->create();
        foreach (['missing', 'foreign', 'overnight', 'same_date', 'reversed_rounds'] as $case) {
            $invalid = $options;
            if ($case === 'missing') array_pop($invalid['programme']['rounds']);
            if ($case === 'foreign') $invalid['programme']['rounds'][0]['draw_id'] = $foreign->id;
            if ($case === 'overnight') $invalid['programme']['days'][0]['end'] = '2026-10-10 01:00:00';
            if ($case === 'same_date') $invalid['programme']['days'][1] = ['start' => '2026-10-09 19:00:00', 'end' => '2026-10-09 23:00:00'];
            if ($case === 'reversed_rounds') $invalid['programme']['rounds'][0]['sequence'] = 4;
            try { app(EventVenueScheduleService::class)->preview($event, $invalid); $this->fail('Invalid programme accepted.'); }
            catch (\InvalidArgumentException $exception) { $this->assertNotEmpty($exception->getMessage()); }
        }
    }

    public function test_changed_programme_revision_rejects_apply_without_writes(): void
    {
        [$event, $options] = $this->scenario();
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $options['programme']['days'][2]['end'] = '2026-10-11 17:00:00';
        try { $service->apply($event, $options, $preview['revision']); $this->fail('Stale programme accepted.'); }
        catch (\InvalidArgumentException $exception) { $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count()); }
    }

    public function test_daily_breaks_keep_matches_and_rest_out_of_the_break(): void
    {
        [$event, $options] = $this->scenario();
        $options['programme']['days'][0] += ['break_start' => '2026-10-09 08:45:00', 'break_end' => '2026-10-09 10:00:00'];
        $options['player_rest'] = 15;
        $preview = app(EventVenueScheduleService::class)->preview($event, $options);
        $this->assertCount(12, $preview['matches']);
        $dayOne = collect($preview['matches'])->filter(fn ($row) => str_starts_with($row['scheduled_at'], '2026-10-09'));
        $this->assertSame(['2026-10-09 08:00:00', '2026-10-09 10:00:00', '2026-10-09 10:45:00', '2026-10-09 11:30:00'], $dayOne->pluck('scheduled_at')->all());
    }

    public function test_representative_288_fixture_programme_uses_one_calendar(): void
    {
        [$event, $options] = $this->scenario(24, 15);
        $started = microtime(true);
        $preview = app(EventVenueScheduleService::class)->preview($event, $options);
        fwrite(STDERR, '\n288-fixture preview: '.number_format(microtime(true) - $started, 2).' seconds\n');
        $this->assertCount(288, $preview['matches']);
        $this->assertSame([], $preview['unscheduled']);
        $this->assertCount(288, collect($preview['matches'])->pluck('fixture_key')->unique());
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
    }

    public function test_incoherent_fixed_programme_order_is_rejected(): void
    {
        [$event, $options] = $this->scenario();
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $service->apply($event, $options, $preview['revision']);
        TeamFixture::where('draw_id', $options['draw_ids'][1])->where('round_nr', 1)->update(['scheduled_at' => '2026-10-09 08:00:00']);
        $this->expectException(\InvalidArgumentException::class);
        $service->preview($event, $options);
    }

    public function test_gender_order_repeats_within_each_round(): void
    {
        [$event, $options] = $this->scenario();
        $source = Draw::findOrFail($options['draw_ids'][0]);
        $girls = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'u/10 Girls – Singles', 'gender' => 'Girls']);
        $girls->forceFill(['team_category_id' => 1])->save();
        $girls->venues()->attach($source->venues->first()->id, ['num_courts' => 1]);
        $options['draw_ids'][] = $girls->id;
        foreach ([1, 2, 3] as $round) {
            $fixture = TeamFixture::create(['draw_id' => $girls->id, 'fixture_type' => 1, 'round_nr' => $round, 'match_nr' => $round, 'rubber_sequence' => 1, 'player_count_per_team' => 1]);
            $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => Player::factory()->create()->id, 'team2_id' => Player::factory()->create()->id]);
            $options['programme']['rounds'][] = ['draw_id' => $girls->id, 'round' => $round, 'day' => 1, 'sequence' => $round];
        }
        $options['gender_waves'] = 'boys_then_girls';
        $preview = app(EventVenueScheduleService::class)->preview($event, $options);
        $singles = collect($preview['matches'])->whereIn('draw_id', [$source->id, $girls->id])->values();
        $this->assertSame([$source->id, $girls->id, $source->id, $girls->id, $source->id, $girls->id], $singles->pluck('draw_id')->all());
        $this->assertSame([1, 1, 2, 2, 3, 3], $singles->pluck('round')->all());
    }

    public function test_each_day_uses_its_own_gender_order(): void
    {
        $event = Event::factory()->create();
        $venue = new Venue();
        $venue->forceFill(['name' => 'Daily order courts'])->save();
        $source = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'u/10 Boys – Singles', 'gender' => 'Boys']);
        $source->forceFill(['team_category_id' => 1])->save();
        $source->venues()->attach($venue->id, ['num_courts' => 2]);
        $days = array_map(fn ($day) => ['start' => '2026-10-'.$day.' 08:00:00', 'end' => '2026-10-'.$day.' 18:00:00'], ['09', '10', '11']);
        $options = ['start' => $days[0]['start'], 'end' => $days[2]['end'], 'duration' => 30, 'wave_minutes' => 30, 'court_gap' => 0, 'player_rest' => 0, 'draw_ids' => [$source->id], 'programme' => ['days' => $days, 'rounds' => []]];
        $girls = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'u/10 Girls – Singles', 'gender' => 'Girls']);
        $girls->forceFill(['team_category_id' => 1])->save();
        $girls->venues()->attach($source->venues->first()->id, ['num_courts' => 2]);
        $options['draw_ids'][] = $girls->id;
        foreach ([1, 2, 3] as $round) {
            $boysFixture = TeamFixture::create(['draw_id' => $source->id, 'fixture_type' => 1, 'round_nr' => $round, 'match_nr' => $round, 'rubber_sequence' => 1, 'player_count_per_team' => 1]);
            $boysFixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => Player::factory()->create()->id, 'team2_id' => Player::factory()->create()->id]);
            $fixture = TeamFixture::create(['draw_id' => $girls->id, 'fixture_type' => 1, 'round_nr' => $round, 'match_nr' => $round, 'rubber_sequence' => 1, 'player_count_per_team' => 1]);
            $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => Player::factory()->create()->id, 'team2_id' => Player::factory()->create()->id]);
            foreach ([$source->id, $girls->id] as $drawId) $options['programme']['rounds'][] = ['draw_id' => $drawId, 'round' => $round, 'day' => $round, 'sequence' => 1];
        }
        foreach (['girls_then_boys', 'boys_then_girls', 'combined'] as $index => $order) $options['programme']['days'][$index]['gender_waves'] = $order;
        $options['gender_waves'] = 'boys_then_girls';
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $this->assertSame([], $preview['unscheduled']);
        $rows = collect($preview['matches']);
        foreach ([[$girls->id, 1, '08:00:00'], [$source->id, 1, '08:30:00'], [$source->id, 2, '08:00:00'], [$girls->id, 2, '08:30:00'], [$source->id, 3, '08:00:00'], [$girls->id, 3, '08:00:00']] as [$drawId, $round, $time]) {
            $this->assertSame($time, substr($rows->where('draw_id', $drawId)->where('round', $round)->first()['scheduled_at'], 11));
        }
        $options['programme']['days'][0]['gender_waves'] = 'boys_then_girls';
        try { $service->apply($event, $options, $preview['revision']); $this->fail('Changed day order accepted with stale preview.'); }
        catch (\InvalidArgumentException $exception) { $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count()); }
    }
}
