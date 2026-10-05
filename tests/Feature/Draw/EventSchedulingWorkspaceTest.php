<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, OrderOfPlay, Player, TeamFixture, TeamFixturePlayer, User, Venue};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventSchedulingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function setupEvent(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $event = Event::factory()->create(['name' => 'Scheduling verification event', 'start_date' => '2026-10-09', 'end_date' => '2026-10-11']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'locked' => false]);
        $venue = new Venue();
        $venue->forceFill(['name' => 'Main courts'])->save();
        $event->venues()->attach($venue->id, ['num_courts' => 2]);
        $draw->venues()->attach($venue->id, ['num_courts' => 2]);
        return [$event, $draw, $venue];
    }

    private function rubber(Draw $draw, array $fields = []): TeamFixture
    {
        return TeamFixture::create($fields + ['draw_id' => $draw->id, 'round_nr' => 1,
            'tie_nr' => 1, 'match_nr' => 1, 'fixture_type' => 1, 'match_status' => 0]);
    }

    private function placement(int $id, int $venue, array $fields = []): array
    {
        return $fields + ['fixture_id' => $id, 'scheduled_at' => '2026-10-10 08:30:00',
            'venue_id' => $venue, 'court' => '1', 'duration' => 60, 'court_gap' => 5, 'player_rest' => 30,
            'round_progression' => 'team_ready'];
    }

    public function test_published_individual_manual_edit_checks_the_whole_court_booking(): void
    {
        [$event, $draw, $venue] = $this->setupEvent();
        $match = Fixture::factory()->create(['draw_id' => $draw->id, 'match_status' => 0]);
        $this->rubber($draw, ['scheduled_at' => '2026-10-10 09:00:00', 'venue_id' => $venue->id,
            'court_label' => 'Court 1', 'duration_min' => 60]);
        $this->postJson(route('backend.event-venue-schedule.manual-assignment', $event), $this->placement($match->id, $venue->id))
            ->assertUnprocessable()->assertJsonPath('message', 'Schedule conflict: the court or a participant is already booked during this time.');
        $this->assertDatabaseCount('order_of_plays', 0);
        $this->postJson(route('backend.event-venue-schedule.manual-assignment', $event), $this->placement($match->id, $venue->id, ['court' => '2']))
            ->assertOk();
        $this->assertTrue((bool) $draw->fresh()->published);
    }

    public function test_team_and_individual_identifiers_do_not_cross_on_manual_edit_and_unapply(): void
    {
        [$event, $draw, $venue] = $this->setupEvent();
        $individual = Fixture::factory()->create(['draw_id' => $draw->id, 'match_status' => 0]);
        $team = $this->rubber($draw);
        $individual->forceFill(['id' => $team->id])->save();
        $this->assertSame($individual->id, $team->id);
        $this->postJson(route('backend.event-venue-schedule.manual-assignment', $event),
            $this->placement($team->id, $venue->id, ['fixture_kind' => 'team']))->assertOk()->assertJsonPath('assignment.fixture_kind', 'team');
        $this->assertDatabaseCount('order_of_plays', 0);
        $this->postJson(route('backend.event-venue-schedule.unapply', $event), ['fixture_kind' => 'team', 'fixture_id' => $team->id])->assertOk();
        $this->assertNull($team->fresh()->scheduled_at);
        $this->assertNotNull($individual->fresh());
        $team->update(['match_status' => 2]);
        $this->postJson(route('backend.event-venue-schedule.manual-assignment', $event),
            $this->placement($team->id, $venue->id, ['fixture_kind' => 'team']))->assertUnprocessable();
    }

    public function test_manual_team_venue_change_warns_without_requiring_confirmation(): void
    {
        [$event, $draw, $venue] = $this->setupEvent();
        $other = new Venue();
        $other->forceFill(['name' => 'Alternate courts'])->save();
        $draw->venues()->attach($other->id, ['num_courts' => 1]);
        $player = Player::factory()->create(['name' => 'Venue', 'surname' => 'Continuity']);
        $earlier = $this->rubber($draw, ['scheduled_at' => '2026-10-10 06:00:00', 'venue_id' => $venue->id,
            'court_label' => '1', 'duration_min' => 60]);
        $later = $this->rubber($draw, ['match_nr' => 2]);
        foreach ([$earlier, $later] as $rubber) TeamFixturePlayer::create(['team_fixture_id' => $rubber->id, 'team1_id' => $player->id]);
        $this->postJson(route('backend.event-venue-schedule.manual-assignment', $event),
            $this->placement($later->id, $other->id, ['fixture_kind' => 'team']))
            ->assertOk()->assertJsonCount(1, 'warnings');
        $this->assertSame($other->id, $later->fresh()->venue_id);
    }

    public function test_removing_a_team_booked_court_is_rejected(): void
    {
        [$event, $draw, $venue] = $this->setupEvent();
        DB::table('event_venue_courts')->insert(['event_id' => $event->id, 'venue_id' => $venue->id,
            'label' => '2', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $team = $this->rubber($draw, ['scheduled_at' => '2026-10-10 08:00:00', 'venue_id' => $venue->id,
            'court_label' => 'Court 2', 'duration_min' => 60]);
        $this->postJson(route('backend.event-venue-schedule.courts.configure', [$event, $venue]), ['courts' => 1, 'ball_type' => 'yellow'])
            ->assertUnprocessable();
        $this->assertNotNull($team->fresh()->scheduled_at);
        $this->assertDatabaseHas('event_venue_courts', ['event_id' => $event->id, 'venue_id' => $venue->id, 'label' => '2']);
    }

    public function test_shared_workspace_and_old_links_open_the_selected_draw(): void
    {
        [$event, $draw, $venue] = $this->setupEvent();
        $this->rubber($draw);
        $response = $this->get(route('backend.event-venue-schedule.index', ['event' => $event, 'draw_ids' => [$draw->id]]));
        $response->assertOk()->assertSee('Team round progression')->assertSee('Published schedules can be adjusted');
        $this->get(route('backend.individual-schedule.page', $draw))->assertRedirect(route('backend.event-venue-schedule.index', ['event' => $event->id, 'draw_ids' => [$draw->id]]));
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Team event', 'type' => \App\Models\EventType::TEAM]);
        $event->update(['eventType' => $type]);
        $this->get(route('backend.team-schedule.page', $draw))->assertRedirect(route('backend.event-venue-schedule.index', ['event' => $event->id, 'draw_ids' => [$draw->id]]));
        if (getenv('CT_SCHEDULE_BROWSER_FIXTURE') === '1') {
            file_put_contents(storage_path('framework/testing/scheduling-workspace.html'), $response->getContent());
        }
    }

    public function test_adaptation_notice_keeps_pending_matches_visible_until_they_are_scheduled(): void
    {
        [$event, $draw, $venue] = $this->setupEvent();
        $pending = $this->rubber($draw);
        $logs = \App\Models\DrawAuditLog::class;
        $logs::create(['draw_id' => $draw->id, 'action' => 'team_draw_adapted',
            'payload' => ['report' => ['added_fixture_ids' => [$pending->id], 'warnings' => ['A new team added a pairing.']]]]);
        // A later lineup change must not hide the previously added unscheduled match.
        $logs::create(['draw_id' => $draw->id, 'action' => 'team_draw_adapted',
            'payload' => ['report' => ['updated_lineups' => 1, 'warnings' => []]]]);
        $other = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $logs::create(['draw_id' => $other->id, 'action' => 'team_draw_adapted',
            'payload' => ['report' => ['warnings' => ['Other event private warning']]]]);

        $url = route('backend.event-venue-schedule.index', $event);
        $response = $this->get($url)->assertOk()->assertSee('data-schedule-adaptation-notice', false)
            ->assertSee('1 match to schedule.')->assertDontSee('Other event private warning');
        if (getenv('CT_ADAPTATION_BROWSER_FIXTURE') === '1') {
            file_put_contents(storage_path('framework/testing/scheduling-adaptation-workspace.html'), $response->getContent());
        }
        $this->assertNull($pending->fresh()->scheduled_at);
        $pending->update(['scheduled_at' => '2026-10-10 08:00:00', 'venue_id' => $venue->id,
            'court_label' => '1', 'duration_min' => 60]);
        $this->get($url)->assertOk()->assertDontSee('data-schedule-adaptation-notice', false);
    }
}
