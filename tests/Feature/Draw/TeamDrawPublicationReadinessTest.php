<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\DrawReadinessService;
use App\Models\{CategoryEvent, Draw, Event, Fixture, Registration, Team, TeamFixture, TeamFixtureResult, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamDrawPublicationReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function draw(bool $team = true): Draw
    {
        $event = Event::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => false]);
        if ($team) {
            $draw->forceFill(['team_category_id' => 1])->save();
            $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
            $draw->teams_in_draw()->attach(Team::factory()->count(2)->create(['category_event_id' => $category->id])->pluck('id'));
        }
        return $draw;
    }

    private function rubber(Draw $draw, int $match, int $status = 0): TeamFixture
    {
        return TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'round_nr' => 1,
            'match_nr' => $match, 'numSets' => 3, 'match_status' => $status]);
    }

    public function test_generated_team_rubbers_can_publish_without_individual_fixtures(): void
    {
        $draw = $this->draw();
        $first = $this->rubber($draw, 1);
        $second = $this->rubber($draw, 2, 1);
        TeamFixtureResult::create(['team_fixture_id' => $first->id, 'set_nr' => 1, 'team1_score' => 6, 'team2_score' => 3]);
        TeamFixtureResult::create(['team_fixture_id' => $first->id, 'set_nr' => 2, 'team1_score' => 6, 'team2_score' => 4]);
        $readiness = app(DrawReadinessService::class)->for($draw);
        $this->assertTrue($readiness['ready_to_publish']);
        $this->assertSame(2, $readiness['fixture_count']);
        $this->assertSame(1, $readiness['scored_count']);
        $this->assertSame('2 participants assigned', $readiness['checks']['participants']['label']);
        $this->assertDatabaseCount('fixtures', 0);
        $this->postJson(route('draw.toggle.publish', $draw->id))->assertOk()->assertJsonPath('published', true);
        $this->assertTrue((bool) $draw->fresh()->published);
        $this->assertDatabaseHas('draw_audit_logs', ['draw_id' => $draw->id, 'action' => 'published']);
        $this->assertDatabaseCount('team_fixtures', 2);
        $this->assertDatabaseCount('team_fixture_results', 2);
        $this->assertSame(1, (int) $second->fresh()->match_status);
    }

    public function test_team_draw_with_individual_rows_but_no_team_rubbers_is_not_ready(): void
    {
        $draw = $this->draw();
        Fixture::factory()->create(['draw_id' => $draw->id]);
        $readiness = app(DrawReadinessService::class)->for($draw);
        $this->assertFalse($readiness['ready_to_publish']);
        $this->assertSame(0, $readiness['fixture_count']);
        $this->postJson(route('draw.toggle.publish', $draw->id))->assertStatus(422)->assertJsonPath('success', false);
        $this->assertFalse((bool) $draw->fresh()->published);
        $this->assertDatabaseCount('draw_audit_logs', 0);
    }

    public function test_empty_team_draw_cannot_be_published(): void
    {
        $draw = $this->draw();
        $this->postJson(route('draw.toggle.publish', $draw->id))->assertStatus(422)->assertJsonPath('success', false);
        $this->assertFalse((bool) $draw->fresh()->published);
        $this->assertDatabaseCount('draw_audit_logs', 0);
    }

    public function test_team_rubbers_without_assigned_teams_do_not_bypass_participant_readiness(): void
    {
        $draw = $this->draw();
        $draw->teams_in_draw()->detach();
        $this->rubber($draw, 1);
        $readiness = app(DrawReadinessService::class)->for($draw);
        $this->assertFalse($readiness['ready_to_publish']);
        $this->assertFalse($readiness['checks']['participants']['ok']);
        $this->postJson(route('draw.toggle.publish', $draw->id))->assertStatus(422);
        $this->assertFalse((bool) $draw->fresh()->published);
    }

    public function test_individual_readiness_ignores_team_rubbers_and_team_results(): void
    {
        $draw = $this->draw(false);
        $registration = Registration::factory()->create();
        $draw->registrations()->attach($registration->id);
        $draw->settings()->create(['workflow' => 'round_robin']);
        Fixture::factory()->create(['draw_id' => $draw->id]);
        $rubber = $this->rubber($draw, 1, 1);
        TeamFixtureResult::create(['team_fixture_id' => $rubber->id, 'set_nr' => 1, 'team1_score' => 6, 'team2_score' => 3]);
        $readiness = app(DrawReadinessService::class)->for($draw);
        $this->assertTrue($readiness['ready_to_publish']);
        $this->assertSame(1, $readiness['fixture_count']);
        $this->assertSame(0, $readiness['scored_count']);
    }

    public function test_foreign_event_admin_cannot_publish_team_draw(): void
    {
        $draw = $this->draw();
        $this->rubber($draw, 1);
        $otherAdmin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => Event::factory()->create()->id, 'user_id' => $otherAdmin->id]);
        $this->actingAs($otherAdmin)->postJson(route('draw.toggle.publish', $draw->id))->assertForbidden();
        $this->assertFalse((bool) $draw->fresh()->published);
    }
}
