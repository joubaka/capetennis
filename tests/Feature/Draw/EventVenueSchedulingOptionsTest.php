<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, OrderOfPlay, Player, Registration, Team, TeamFixture, TeamRegion, TeamTie, User, Venue};
use App\Services\Scheduling\EventVenueScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventVenueSchedulingOptionsTest extends TestCase
{
    use RefreshDatabase;

    public static function genderOrders(): array
    {
        return [['boys_then_girls', 'Boys', 'Girls'], ['girls_then_boys', 'Girls', 'Boys']];
    }

    #[DataProvider('genderOrders')]
    public function test_two_four_rubber_ties_per_gender_alternate_waves_for_two_rounds(string $order, string $first, string $second): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event);
        foreach ([$second, $first] as $gender) {
            $draw = $this->draw($event, $venue, $gender, 8);
            foreach ([1, 2] as $round) {
                foreach ([1, 2] as $number) {
                    $tie = $this->tie($draw, $round, $number);
                    foreach (range(1, 4) as $sequence) $this->rubber($draw, ['team_tie_id' => $tie->id, 'round_nr' => $round, 'tie_nr' => $number], $sequence);
                }
            }
        }
        $service = app(EventVenueScheduleService::class);
        $options = $this->schedulingOptions() + ['gender_waves' => $order, 'tie_allocation' => 'complete_tie', 'round_progression' => 'all_round'];
        $preview = $service->preview($event, $options);
        $this->assertCount(32, $preview['matches']);
        $this->assertSame([], $preview['unscheduled']);
        foreach ([['08:00', $first, 1], ['09:30', $second, 1], ['11:00', $first, 2], ['12:30', $second, 2]] as [$time, $gender, $round]) {
            $wave = collect($preview['matches'])->where('scheduled_at', '2026-09-10 '.$time.':00');
            $this->assertCount(8, $wave);
            $this->assertSame([$gender], $wave->pluck('draw_name')->unique()->values()->all());
            $this->assertSame([$round], $wave->pluck('round')->unique()->values()->all());
            $this->assertCount(8, $wave->pluck('court')->unique());
        }
        $this->assertSame(32, $service->apply($event, $options, $preview['revision'])['count']);
        $this->assertSame(32, TeamFixture::whereNotNull('scheduled_at')->count());
        $this->assertSame(0, OrderOfPlay::count());
    }

    public static function tieIdentityStrategies(): array
    {
        return [['canonical'], ['legacy_opponents']];
    }

    #[DataProvider('genderOrders')]
    public function test_court_ready_reuses_free_courts_between_gender_waves_for_two_rounds(string $order, string $first, string $second): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event);
        foreach ([$second, $first] as $gender) {
            $draw = $this->draw($event, $venue, $gender, 3);
            foreach ([1, 2] as $round) {
                $tie = $this->tie($draw, $round, 1);
                foreach (range(1, 4) as $sequence) $this->rubber($draw, ['team_tie_id' => $tie->id, 'round_nr' => $round], $sequence);
            }
        }
        $service = app(EventVenueScheduleService::class);
        $options = array_replace($this->schedulingOptions(), ['duration' => 45, 'wave_minutes' => 45,
            'player_rest' => 0, 'gender_waves' => $order, 'tie_allocation' => 'complete_tie', 'round_progression' => 'all_round']);
        $strict = $service->preview($event, $options);
        $ready = $service->preview($event, $options + ['gender_wave_release' => 'court_ready']);
        $this->assertCount(16, $ready['matches']);
        $this->assertSame([], $ready['unscheduled']);
        $this->assertNotSame($strict['revision'], $ready['revision']);
        $strictSecond = collect($strict['matches'])->where('draw_name', $second)->where('round', 1)->min('scheduled_at');
        $this->assertSame('2026-09-10 09:30:00', $strictSecond);
        $wave = collect($ready['matches'])->where('scheduled_at', '2026-09-10 08:45:00');
        $this->assertCount(3, $wave);
        $this->assertCount(1, $wave->where('draw_name', $first)->where('round', 1));
        $this->assertCount(2, $wave->where('draw_name', $second)->where('round', 1));
        $this->assertCount(3, $wave->pluck('court')->unique());
        foreach ([$first, $second] as $gender) {
            $starts = collect($ready['matches'])->where('draw_name', $gender)->where('round', 2)->pluck('scheduled_at');
            $this->assertCount(4, $starts);
            $this->assertTrue($starts->every(fn ($at) => $at >= '2026-09-10 09:30:00'));
        }
        $this->assertSame(16, $service->apply($event, $options + ['gender_wave_release' => 'court_ready'], $ready['revision'])['count']);
    }

    public function test_court_ready_keeps_player_rest_and_minimum_wave_interval(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event);
        $girls = $this->draw($event, $venue, 'Girls', 3);
        $boys = $this->draw($event, $venue, 'Boys', 3);
        $player = Player::factory()->create();
        $this->rubber($girls, ['team_tie_id' => $this->tie($girls, 1, 1)->id], 1, $player->id);
        $boy = $this->rubber($boys, ['team_tie_id' => $this->tie($boys, 1, 1)->id], 1, $player->id);
        $options = array_replace($this->schedulingOptions(), ['duration' => 45, 'wave_minutes' => 45,
            'player_rest' => 60, 'gender_waves' => 'girls_then_boys', 'gender_wave_release' => 'court_ready']);
        $service = app(EventVenueScheduleService::class);
        $rest = $service->preview($event, $options);
        $this->assertSame('2026-09-10 09:45:00', collect($rest['matches'])->firstWhere('fixture_id', $boy->id)['scheduled_at']);
        $interval = $service->preview($event, array_replace($options, ['player_rest' => 0, 'wave_minutes' => 90]));
        $this->assertSame('2026-09-10 09:30:00', collect($interval['matches'])->firstWhere('fixture_id', $boy->id)['scheduled_at']);
    }

    #[DataProvider('tieIdentityStrategies')]
    public function test_complete_tie_reserves_rest_delayed_rubber_before_other_ties_without_delaying_their_start(string $strategy): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event);
        $draw = $this->draw($event, $venue, 'Boys', 2);
        if ($strategy === 'canonical') {
            $first = ['team_tie_id' => $this->tie($draw, 1, 1)->id];
            $second = ['team_tie_id' => $this->tie($draw, 1, 2)->id];
        } else {
            $regions = collect(range(1, 4))->map(fn ($number) => TeamRegion::create(['region_name' => 'Region '.$number, 'short_name' => 'R'.$number]));
            $first = ['tie_nr' => 7, 'region1' => $regions[0]->id, 'region2' => $regions[1]->id];
            $second = ['tie_nr' => 7, 'region1' => $regions[2]->id, 'region2' => $regions[3]->id];
        }
        $player = Player::factory()->create();
        $firstRubber = $this->rubber($draw, $first, 1, $player->id);
        $delayedRubber = $this->rubber($draw, $first, 2, $player->id);
        if ($strategy === 'legacy_opponents') $delayedRubber->update(['region1' => $first['region2'], 'region2' => $first['region1']]);
        $otherFirst = $this->rubber($draw, $second, 1);
        $this->rubber($draw, $second, 2);
        $this->rubber($draw, $second, 3);
        $service = app(EventVenueScheduleService::class);
        $balanced = $service->preview($event, $this->schedulingOptions());
        $options = $this->schedulingOptions() + ['tie_allocation' => 'complete_tie'];
        $complete = $service->preview($event, $options);
        $rows = collect($complete['matches'])->keyBy('fixture_id');
        $this->assertSame('2026-09-10 10:30:00', collect($balanced['matches'])->firstWhere('fixture_id', $delayedRubber->id)['scheduled_at']);
        $this->assertSame('2026-09-10 10:15:00', $rows[$delayedRubber->id]['scheduled_at']);
        $this->assertSame('2026-09-10 08:00:00', $rows[$firstRubber->id]['scheduled_at']);
        $this->assertSame('2026-09-10 08:00:00', $rows[$otherFirst->id]['scheduled_at'], 'Another tie can play on a free court before the first tie finishes.');
        $this->assertNotSame($balanced['revision'], $complete['revision']);
        $this->assertSame([], $complete['unscheduled']);
        $this->assertSame(5, $service->apply($event, $options, $complete['revision'])['count']);
    }

    public function test_blocked_tie_warns_and_other_ties_still_use_free_courts(): void
    {
        $event = Event::factory()->create();
        $draw = $this->draw($event, $this->venue($event), 'Boys', 2);
        $player = Player::factory()->create();
        $this->rubber($draw, ['tie_nr' => 1], 1, $player->id);
        $blocked = $this->rubber($draw, ['tie_nr' => 1], 2, $player->id);
        $other = $this->rubber($draw, ['tie_nr' => 2], 1);
        $options = array_replace($this->schedulingOptions(), ['end' => '2026-09-10 09:15:00', 'tie_allocation' => 'complete_tie', 'allow_partial' => true]);
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $this->assertCount(2, $preview['matches']);
        $this->assertSame($blocked->id, $preview['unscheduled'][0]['fixture_id']);
        $this->assertContains($other->id, array_column($preview['matches'], 'fixture_id'));
        $this->assertStringContainsString('could not be completely allocated', implode(' ', $preview['warnings']));
        $this->assertSame(2, $service->apply($event, $options, $preview['revision'])['count']);
        $this->assertNull($blocked->fresh()->scheduled_at);
    }

    public function test_girls_first_preserves_saved_times_and_warns_with_the_selected_order(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event);
        foreach (['Boys', 'Girls'] as $gender) {
            $draw = Draw::factory()->create(['event_id' => $event->id, 'gender' => $gender, 'drawName' => $gender]);
            $draw->venues()->attach($venue->id, ['num_courts' => 1]);
            $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 1, 'bracket_id' => 1, 'match_nr' => 1,
                'registration1_id' => Registration::factory()->create()->id, 'registration2_id' => Registration::factory()->create()->id]);
            OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id, 'court' => '1',
                'time' => $gender === 'Boys' ? '2026-09-10 08:00:00' : '2026-09-10 10:00:00', 'duration_minutes' => 75]);
        }
        $before = OrderOfPlay::orderBy('id')->pluck('time', 'id')->all();
        $service = app(EventVenueScheduleService::class);
        $options = $this->schedulingOptions() + ['gender_waves' => 'girls_then_boys'];
        $preview = $service->preview($event, $options);
        $this->assertSame([], $preview['matches']);
        $this->assertStringContainsString('saved time does not follow girls then boys waves', implode(' ', $preview['warnings']));
        $this->assertSame(0, $service->apply($event, $options, $preview['revision'])['count']);
        $this->assertSame($before, OrderOfPlay::orderBy('id')->pluck('time', 'id')->all());
    }

    public function test_new_options_are_persisted_and_invalid_tie_allocation_is_rejected(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $event = Event::factory()->create();
        $venue = $this->venue($event);
        $draw = $this->draw($event, $venue, 'Girls', 2);
        foreach (['1', '2'] as $label) {
            DB::table('event_venue_courts')->insert(['event_id' => $event->id, 'venue_id' => $venue->id,
                'label' => $label, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin)->postJson(route('backend.event-venue-schedule.assignments', $event), [
            'venues' => [['id' => $venue->id, 'courts' => 2]],
            'assignments' => [['draw_id' => $draw->id, 'venue_ids' => [$venue->id],
                'court_allocations' => [['venue_id' => $venue->id, 'court_labels' => ['1', '2']]]]],
            'schedule' => $this->schedulingOptions() + ['gender_waves' => 'girls_then_boys', 'gender_wave_release' => 'court_ready', 'tie_allocation' => 'complete_tie',
                'draw_starts' => [], 'venue_starts' => [], 'reschedule_existing' => false],
        ])->assertOk();
        $saved = json_decode(DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true);
        $this->assertSame('girls_then_boys', $saved['gender_waves']);
        $this->assertSame('complete_tie', $saved['tie_allocation']);
        $this->assertSame('court_ready', $saved['gender_wave_release']);
        foreach (['preview', 'apply'] as $action) {
            $this->postJson(route('backend.event-venue-schedule.'.$action, $event), $this->schedulingOptions() + [
                'tie_allocation' => 'finish_playing', 'revision' => str_repeat('a', 64),
            ])->assertUnprocessable()->assertJsonValidationErrors('tie_allocation');
            $this->assertSame($saved, json_decode(DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true));
            $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
            $this->assertSame(0, OrderOfPlay::count());
        }
        $this->postJson(route('backend.event-venue-schedule.assignments', $event), [
            'venues' => [['id' => $venue->id, 'courts' => 2]],
            'assignments' => [['draw_id' => $draw->id, 'venue_ids' => [$venue->id], 'court_allocations' => []]],
            'schedule' => $this->schedulingOptions() + ['gender_wave_release' => 'invalid', 'draw_starts' => [], 'venue_starts' => [], 'reschedule_existing' => false],
        ])->assertUnprocessable()->assertJsonValidationErrors('schedule.gender_wave_release');
        $this->assertSame($saved, json_decode(DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true));
        foreach (['preview', 'apply'] as $action) {
            $this->postJson(route('backend.event-venue-schedule.'.$action, $event), $this->schedulingOptions() + ['gender_wave_release' => 'invalid'])
                ->assertUnprocessable()->assertJsonValidationErrors('gender_wave_release');
        }
    }

    private function venue(Event $event): Venue
    {
        $venue = new Venue();
        $venue->forceFill(['name' => 'Shared Courts'])->save();
        $event->venues()->attach($venue->id, ['num_courts' => 8]);
        return $venue;
    }

    private function draw(Event $event, Venue $venue, string $gender, int $courts): Draw
    {
        $draw = Draw::factory()->create(['event_id' => $event->id, 'gender' => $gender, 'drawName' => $gender]);
        $draw->forceFill(['team_category_id' => 1])->save();
        $draw->venues()->attach($venue->id, ['num_courts' => $courts]);
        return $draw;
    }

    private function tie(Draw $draw, int $round, int $number): TeamTie
    {
        return TeamTie::create(['draw_id' => $draw->id, 'round_nr' => $round, 'tie_nr' => $number,
            'home_team_id' => Team::factory()->create()->id, 'away_team_id' => Team::factory()->create()->id, 'status' => 'draft']);
    }

    private function rubber(Draw $draw, array $tie, int $sequence, ?int $playerId = null): TeamFixture
    {
        $rubber = TeamFixture::create($tie + ['draw_id' => $draw->id, 'fixture_type' => 1,
            'round_nr' => 1, 'match_nr' => $sequence, 'rubber_sequence' => $sequence, 'player_count_per_team' => 1]);
        $rubber->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => $playerId ?: Player::factory()->create()->id,
            'team2_id' => Player::factory()->create()->id]);
        return $rubber;
    }

    private function schedulingOptions(): array
    {
        return ['start' => '2026-09-10 08:00:00', 'end' => '2026-09-10 18:00:00',
            'duration' => 75, 'wave_minutes' => 90, 'court_gap' => 0, 'player_rest' => 60];
    }
}
