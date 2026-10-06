<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, OrderOfPlay, Player, Registration, TeamFixture, User, Venue};
use App\Services\Scheduling\ScheduleAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleAuditTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $event = Event::factory()->create();
        $draw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'u/10 Girls']);
        $venue = Venue::forceCreate(['name' => 'Audit courts']);
        $event->venues()->attach($venue->id, ['num_courts' => 6]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $user->id]);
        $this->actingAs($user);
        DB::table('event_venue_schedule_drafts')->insert(['event_id' => $event->id, 'options' => json_encode(['player_rest' => 30, 'court_gap' => 5]), 'created_at' => now(), 'updated_at' => now()]);
        return [$event, $draw, $venue];
    }

    private function team(Draw $draw, Venue $venue, string $time, string $court = '1', ?Player $player = null): TeamFixture
    {
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1, 'match_nr' => 1, 'fixture_type' => 1,
            'player_count_per_team' => 1, 'scheduled_at' => $time, 'venue_id' => $venue->id, 'court_label' => $court, 'duration_min' => 60, 'match_status' => 0]);
        $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => ($player ?? Player::factory()->create())->id, 'team2_id' => Player::factory()->create()->id]);
        return $fixture;
    }

    public function test_audit_detects_cross_storage_conflicts_rest_and_travel_without_mutation_or_foreign_details(): void
    {
        [$event, $draw, $venue] = $this->scenario();
        $player = Player::factory()->create();
        $first = $this->team($draw, $venue, '2026-10-10 08:00:00', 'Court 1', $player);
        $registration = Registration::factory()->create(); $registration->players()->attach($player);
        $other = Registration::factory()->create(); $other->players()->attach(Player::factory()->create());
        $individual = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $registration->id, 'registration2_id' => $other->id]);
        OrderOfPlay::create(['fixture_id' => $individual->id, 'venue_id' => $venue->id, 'court' => '1', 'time' => '2026-10-10 08:30:00', 'duration_minutes' => 60]);
        $away = Venue::forceCreate(['name' => 'Other venue']); $event->venues()->attach($away->id, ['num_courts' => 1]);
        $this->team($draw, $away, '2026-10-10 09:45:00', '1', $player);
        $foreign = Draw::factory()->create(['event_id' => Event::factory()->create()->id, 'drawName' => 'PRIVATE FOREIGN DRAW']);
        $this->team($foreign, $venue, '2026-10-10 08:15:00', '1');
        $before = [TeamFixture::orderBy('id')->get()->map->getAttributes()->all(), DB::table('event_venue_schedule_drafts')->first()->options];
        $response = $this->get(route('backend.event-venue-schedule.calendar.audit', ['event' => $event->id]))->assertOk();
        $report = $response->viewData('report');
        $codes = array_column($report['issues'], 'code');
        foreach (['court_overlap', 'player_overlap', 'player_rest', 'venue_change'] as $code) $this->assertContains($code, $codes);
        $this->assertSame(3, $report['checked']);
        $this->assertTrue(collect($report['issues'])->contains('external', true));
        $response->assertDontSee('PRIVATE FOREIGN DRAW')->assertSee('Schedule audit');
        $this->assertStringNotContainsString('PRIVATE FOREIGN DRAW', json_encode($report));
        $this->assertSame($before, [TeamFixture::orderBy('id')->get()->map->getAttributes()->all(), DB::table('event_venue_schedule_drafts')->first()->options]);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
        $this->assertStringContainsString('#match-team-'.$first->id, json_encode($report));
    }

    public function test_day_scope_compares_neighboring_day_and_filters_own_links(): void
    {
        [$event, $draw, $venue] = $this->scenario();
        $player = Player::factory()->create();
        $this->team($draw, $venue, '2026-10-10 23:30:00', '1', $player);
        $this->team($draw, $venue, '2026-10-11 00:15:00', '1', $player);
        $this->team($draw, $venue, '2026-10-12 08:00:00', '2');
        $report = app(ScheduleAuditService::class)->audit($event, ['date' => '2026-10-11']);
        $this->assertSame(1, $report['checked']);
        $this->assertContains('court_overlap', array_column($report['issues'], 'code'));
        $this->assertContains('player_overlap', array_column($report['issues'], 'code'));
        $empty = app(ScheduleAuditService::class)->audit($event, ['date' => '2026-10-12']);
        $this->assertSame(0, $empty['errors']);
        $this->assertSame(0, $empty['warnings']);
    }

    public function test_audit_rejects_other_event_manager_and_foreign_scope(): void
    {
        [$event, $draw, $venue] = $this->scenario();
        $foreign = Event::factory()->create();
        $this->get(route('backend.event-venue-schedule.calendar.audit', $foreign))->assertForbidden();
        $foreignDraw = Draw::factory()->create(['event_id' => $foreign->id]);
        $this->get(route('backend.event-venue-schedule.calendar.audit', ['event' => $event, 'draw_id' => $foreignDraw->id]))->assertUnprocessable();
        $foreignVenue = Venue::forceCreate(['name' => 'Private foreign venue']);
        $this->get(route('backend.event-venue-schedule.calendar.audit', ['event' => $event, 'venue_id' => $foreignVenue->id]))->assertUnprocessable();
    }

    public function test_completeness_undated_aggregate_and_query_count_are_bounded_without_false_bye_warnings(): void
    {
        [$event, $draw, $venue] = $this->scenario();
        foreach (range(1, 30) as $index) $this->team($draw, $venue, '2026-10-10 08:00:00', (string) $index);
        $missing = $this->team($draw, $venue, '2026-10-10 10:00:00', '31');
        $missing->update(['duration_min' => null, 'court_label' => null]);
        $missing->fixturePlayers()->delete();
        TeamFixture::create(['draw_id' => $draw->id, 'round_nr' => 1, 'match_nr' => 2, 'fixture_type' => 1, 'match_status' => 0]);
        Fixture::factory()->create(['draw_id' => $draw->id]); // unresolved one-sided/byelike placeholder
        DB::enableQueryLog();
        $report = app(ScheduleAuditService::class)->audit($event);
        $queries = count(DB::getQueryLog()); DB::disableQueryLog();
        $this->assertSame(31, $report['checked']);
        $this->assertSame(1, $report['unscheduled']);
        $this->assertContains('missing_location', array_column($report['issues'], 'code'));
        $this->assertContains('missing_duration', array_column($report['issues'], 'code'));
        $this->assertContains('unknown_players', array_column($report['issues'], 'code'));
        $this->assertLessThan(40, $queries);
    }
    public function test_long_foreign_booking_overlap_and_serialized_issue_limit_are_explicit(): void
    {
        [$event, $draw, $venue] = $this->scenario();
        $this->team($draw, $venue, '2026-10-10 09:00:00');
        $foreign = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $long = $this->team($foreign, $venue, '2026-10-09 21:00:00');
        $long->update(['duration_min' => 900]);
        $report = app(ScheduleAuditService::class)->audit($event);
        $this->assertContains('court_overlap', array_column($report['issues'], 'code'));
        $this->assertTrue(collect($report['issues'])->contains('external', true));
        foreach (range(1, 22) as $index) $this->team($draw, $venue, '2026-10-10 09:00:00');
        $many = app(ScheduleAuditService::class)->audit($event);
        $this->assertCount(200, $many['issues']);
        $this->assertGreaterThan(0, $many['omitted_issues']);
        $this->assertSame($many['errors'] + $many['warnings'], count($many['issues']) + $many['omitted_issues']);
    }

    public function test_real_feeders_team_progression_programme_window_and_partial_players_are_reported(): void
    {
        [$event, $draw, $venue] = $this->scenario();
        $draw->update(['drawName' => '<script>private-code</script>']);
        $player = Player::factory()->create();
        $first = $this->team($draw, $venue, '2026-10-10 08:00:00', '1', $player);
        $region = \App\Models\TeamRegion::create(['region_name' => 'Region', 'short_name' => 'REG']);
        $first->update(['region1' => $region->id]);
        $next = $this->team($draw, $venue, '2026-10-10 09:15:00', '2');
        $next->update(['round_nr' => 2, 'region1' => $region->id]);
        $registration = Registration::factory()->create(); $registration->players()->attach($player);
        $child = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 2, 'registration2_id' => $registration->id]);
        $feeder = Fixture::factory()->create(['draw_id' => $draw->id, 'parent_fixture_id' => $child->id, 'registration2_id' => $registration->id]);
        foreach ([[$feeder, '08:00:00', '3'], [$child, '08:45:00', '4']] as [$fixture, $time, $court]) OrderOfPlay::create(['fixture_id' => $fixture->id, 'venue_id' => $venue->id, 'court' => $court, 'time' => '2026-10-10 '.$time, 'duration_minutes' => 60]);
        DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->update(['options' => json_encode(['player_rest' => 30, 'programme' => ['rounds' => [['draw_id' => $draw->id, 'round' => 2, 'day' => 1]], 'days' => [['start' => '2026-10-10 08:00:00', 'end' => '2026-10-10 09:00:00']]]])]);
        $response = $this->get(route('backend.event-venue-schedule.calendar.audit', $event))->assertOk();
        $codes = array_column($response->viewData('report')['issues'], 'code');
        foreach (['team_progression', 'feeder_order', 'outside_programme', 'unknown_players', 'player_overlap'] as $code) $this->assertContains($code, $codes);
        $response->assertDontSee('<script>private-code</script>', false)->assertSee('&lt;script&gt;private-code&lt;/script&gt;', false);
    }

    public function test_scoped_precursor_is_reported_when_latest_precursor_is_outside_selected_venue(): void
    {
        [$event, $draw, $venue] = $this->scenario();
        $otherVenue = Venue::forceCreate(['name' => 'Other courts']);
        $event->venues()->attach($otherVenue->id, ['num_courts' => 2]);
        $region = \App\Models\TeamRegion::create(['region_name' => 'Shared region', 'short_name' => 'SHR']);
        $scoped = $this->team($draw, $venue, '2026-10-10 09:00:00');
        $latest = $this->team($draw, $otherVenue, '2026-10-10 10:00:00');
        $later = $this->team($draw, $otherVenue, '2026-10-10 09:00:00', '2');
        foreach ([$scoped, $latest, $later] as $fixture) $fixture->update(['region1' => $region->id]);
        $later->update(['round_nr' => 2]);
        $report = app(ScheduleAuditService::class)->audit($event, ['venue_id' => $venue->id]);
        $findings = collect($report['issues'])->where('code', 'team_progression');
        $this->assertNotEmpty($findings);
        $this->assertTrue($findings->contains(fn ($issue) => collect($issue['matches'])->contains(fn ($match) => str_ends_with($match['url'], '#match-team-'.$scoped->id))));
    }

    public function test_resource_comparison_budget_ends_pathological_overlap_scan(): void
    {
        $method = new \ReflectionMethod(ScheduleAuditService::class, 'pairs');
        $row = ['start' => \Carbon\Carbon::parse('2026-10-10 08:00:00'), 'end' => \Carbon\Carbon::parse('2026-10-10 09:00:00'), 'own' => true];
        $comparisons = 0;
        $args = [array_fill(0, 1000, $row), 0, &$comparisons, static function () {}];
        $complete = $method->invokeArgs(app(ScheduleAuditService::class), $args);
        $this->assertFalse($complete);
        $this->assertSame(250001, $comparisons);
    }

}
