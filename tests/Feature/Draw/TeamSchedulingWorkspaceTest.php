<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, TeamFixture, User, Venue};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamSchedulingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $event = Event::factory()->create(['name' => 'Fictional Team Scheduling Preview', 'start_date' => '2026-11-06', 'end_date' => '2026-11-08']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $draws = collect();
        foreach ([10, 12] as $age) {
            $venue = Venue::forceCreate(['name' => 'Under '.$age.' courts']);
            $event->venues()->attach($venue->id, ['num_courts' => 4]);
            foreach (['singles', 'reverse_singles', 'doubles', 'mixed_doubles'] as $code) {
                $draw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'Under '.$age.' '.str_replace('_', ' ', $code)]);
                $draw->forceFill(['team_category_id' => 1, 'team_draw_selection' => ['rubber_code' => $code]])->save();
                $draw->venues()->attach($venue->id, ['num_courts' => 4]);
                TeamFixture::create(['draw_id' => $draw->id, 'round_nr' => 1, 'match_nr' => 1, 'fixture_type' => 1,
                    'rubber_code' => $code, 'rubber_sequence' => 1, 'player_count_per_team' => 1]);
                $draws->push($draw);
            }
        }
        $this->actingAs($admin);
        return [$event, $draws];
    }

    public function test_team_root_exposes_complete_age_workflow_without_a_mode_switch(): void
    {
        [$event] = $this->scenario();
        $response = $this->get(route('backend.event-venue-schedule.index', $event))->assertOk()
            ->assertSee('schedule-workspace" data-team-programme-default="true"', false)
            ->assertSeeInOrder(['1. Venues &amp; courts', '2. Select age groups &amp; assign courts', '3. Arrange playing days &amp; timing', '4. Preview &amp; save'], false)
            ->assertSee('Schedule selected matches for one day')
            ->assertSee('Under 10 courts')->assertSee('Under 12 courts');
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
        $this->assertDatabaseCount('event_venue_schedule_drafts', 0);
        if ($artifact = getenv('CT_TEAM_WORKSPACE_HTML')) {
            file_put_contents($artifact, $response->getContent());
        }
    }

    public function test_fixed_day_and_unknown_age_keep_the_match_selection_workflow(): void
    {
        [$event, $draws] = $this->scenario();
        $this->get(route('backend.event-venue-schedule.index', ['event' => $event->id, 'date' => '2026-11-06']))->assertOk()
            ->assertSee('schedule-workspace day-batch-mode" data-team-programme-default="false"', false);
        foreach ($draws as $draw) $draw->forceFill(['drawName' => 'Unclassified team draw'])->save();
        $this->get(route('backend.event-venue-schedule.index', $event))->assertOk()
            ->assertSee('schedule-workspace day-batch-mode" data-team-programme-default="false"', false);
    }

    public function test_selected_unknown_age_does_not_inherit_an_unselected_supported_programme(): void
    {
        [$event, $draws] = $this->scenario();
        $unknown = $draws->first();
        $unknown->forceFill(['drawName' => 'Unclassified team draw'])->save();
        $this->get(route('backend.event-venue-schedule.index', ['event' => $event->id, 'draw_ids' => [$unknown->id]]))->assertOk()
            ->assertSee('schedule-workspace day-batch-mode" data-team-programme-default="false"', false);
    }
}
