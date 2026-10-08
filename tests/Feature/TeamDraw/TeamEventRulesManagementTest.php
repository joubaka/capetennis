<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{Draw, Event, EventType, Team, TeamEventFormat, TeamEventFormatRubber, User};
use App\Services\{TeamDrawGenerationService, TeamEventRulesService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamEventRulesManagementTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        DB::table('eventtypes')->updateOrInsert(['id' => 3], ['name' => 'Team', 'type' => EventType::TEAM]);
        return Event::factory()->create(['eventType' => 3]);
    }

    public function test_rules_require_authentication_and_event_scope(): void
    {
        $event = $this->event();
        $other = $this->event();
        $rules = app(TeamEventRulesService::class)->defaults();
        $this->putJson(route('backend.team-rules.update', $event), ['rules' => $rules])->assertUnauthorized();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin)->putJson(route('backend.team-rules.update', $other), ['rules' => $rules])->assertForbidden();
        $this->assertDatabaseCount('team_event_rules', 0);
        $this->putJson(route('backend.team-rules.update', $event), ['rules' => $rules])->assertOk();
        $this->assertDatabaseCount('team_event_rules', 1);
        $response = $this->get(route('backend.team-rules.edit', $event))->assertOk()->assertSee('Advanced rubber points')->assertSee('Create a pairing format');
        $this->assertSame(1, substr_count($response->getContent(), 'name="rules[rubbers][singles][close_loss]"'));
        if (getenv('CT_BATCHES1822_QA')) { file_put_contents(storage_path('app/batches1822-qa/rules.html'), $response->getContent()); }
    }

    public function test_invalid_rules_leave_existing_configuration_intact(): void
    {
        $event = $this->event();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $user = User::factory()->create()->assignRole('super-user');
        $rules = app(TeamEventRulesService::class)->defaults();
        app(TeamEventRulesService::class)->save($event, $rules);
        $invalid = $rules;
        $invalid['rubbers']['singles']['sets_to_win'] = 3;
        $invalid['standings_order'] = ['points', 'points'];
        $this->actingAs($user)->putJson(route('backend.team-rules.update', $event), ['rules' => $invalid])->assertUnprocessable();
        $this->assertEquals($rules, app(TeamEventRulesService::class)->forEvent($event));
    }

    public function test_event_rule_changes_only_apply_to_later_draw_snapshots(): void
    {
        $event = $this->event();
        $format = TeamEventFormat::factory()->create(['event_id' => $event->id]);
        TeamEventFormatRubber::create(['format_id' => $format->id, 'sequence' => 1,
            'rubber_code' => 'singles', 'name' => 'Singles', 'player_count_per_team' => 1, 'is_required' => true]);
        $teams = Team::factory()->count(2)->create();
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $service = app(TeamEventRulesService::class);
        app(TeamDrawGenerationService::class)->generate($draw, $teams, $format);
        $snapshot = $draw->fresh()->team_scoring_rules;
        $changed = $service->defaults();
        $changed['rubbers']['singles']['straight_win'] = 9;
        $service->save($event, $changed);
        $this->assertSame($snapshot, $service->forDraw($draw->fresh()));
        $newDraw = Draw::factory()->create(['event_id' => $event->id]);
        app(TeamDrawGenerationService::class)->generate($newDraw, $teams, $format);
        $this->assertEquals(9, $newDraw->fresh()->team_scoring_rules['rubbers']['singles']['straight_win']);
        $historical = Draw::factory()->create(['event_id' => $event->id]);
        $this->assertEquals($service->defaults(), $service->forDraw($historical));
    }

    public function test_public_standings_only_include_published_ties(): void
    {
        $event = $this->event();
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true,
            'team_scoring_rules' => app(TeamEventRulesService::class)->defaults()]);
        $category = \App\Models\CategoryEvent::factory()->create(['event_id' => $event->id]);
        $teams = Team::factory()->count(2)->create(['category_event_id' => $category->id]);
        $event->forceFill(['standings_published' => true])->save();
        $tie = \App\Models\TeamTie::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'home_team_id' => $teams[0]->id, 'away_team_id' => $teams[1]->id, 'status' => 'completed']);
        $this->get(route('frontend.team-draw.standings', $draw))->assertOk()->assertDontSee($teams[0]->name);
        $tie->update(['published_at' => now()]);
        $this->get(route('frontend.team-draw.standings', $draw))->assertOk()->assertSee($teams[0]->name);
        $draw->update(['published' => false]);
        $this->get(route('frontend.team-draw.standings', $draw))->assertForbidden();
        $event->update(['published' => false]);
        $this->get(route('frontend.team-draw.standings', $draw))->assertNotFound();
    }

    public function test_regenerating_legacy_draw_does_not_adopt_event_rules(): void
    {
        $event = $this->event();
        $format = TeamEventFormat::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'team_event_format_id' => $format->id]);
        $teams = Team::factory()->count(2)->create();
        \App\Models\TeamTie::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'home_team_id' => $teams[0]->id, 'away_team_id' => $teams[1]->id, 'status' => 'draft']);
        $rules = app(TeamEventRulesService::class)->defaults();
        $rules['tie_win'] = 10;
        app(TeamEventRulesService::class)->save($event, $rules);
        app(\App\Services\TeamDrawRegenerationService::class)->regenerate($draw, $teams, $format);
        $this->assertNull($draw->fresh()->team_scoring_rules);
        $this->assertNull($draw->fresh()->team_format_snapshot);
        $this->assertSame(1, $draw->teamTies()->count());
    }

    public function test_operations_publish_score_correct_and_standings_workflow(): void
    {
        $event = $this->event();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $format = TeamEventFormat::factory()->create(['event_id' => $event->id, 'min_roster_size' => 2, 'max_roster_size' => 2]);
        TeamEventFormatRubber::create(['format_id' => $format->id, 'sequence' => 1, 'rubber_code' => 'singles',
            'name' => 'Singles', 'player_count_per_team' => 1, 'home_positions' => [1], 'away_positions' => [2], 'is_required' => true]);
        $teams = Team::factory()->count(2)->create();
        foreach ($teams as $team) {
            foreach ([1, 2] as $rank) {
                \App\Models\NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Imported', 'surname' => (string) $rank, 'rank' => $rank, 'pay_status' => 0]);
            }
        }
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $tie = app(TeamDrawGenerationService::class)->generate($draw, $teams, $format)->first();
        $rubber = app(\App\Services\TeamTieGenerationService::class)->generateFromFormat($tie, $format)->first();
        $this->get(route('backend.team-draw.operations', $draw))->assertOk()->assertSee('Validate tie');
        $this->postJson(route('team-draw.ties.validate', $tie))->assertOk();
        $this->postJson(route('team-draw.ties.publish', $tie))->assertOk();
        $scores = ['set1_home' => 6, 'set1_away' => 3, 'set2_home' => 6, 'set2_away' => 2];
        $this->postJson(route('frontend.fixtures.score.store', $rubber), $scores)->assertOk()->assertJsonPath('winner', 'home');
        $this->assertSame('completed', $tie->fresh()->status);
        $this->get(route('backend.team-draw.standings', $draw))->assertOk()->assertSee($teams[0]->name);
        $this->postJson(route('frontend.fixtures.score.store', $rubber), ['set1_home' => 1, 'set1_away' => 6, 'set2_home' => 2, 'set2_away' => 6])->assertOk()->assertJsonPath('winner', 'away');
        $this->assertDatabaseCount('team_fixture_results', 2);
        $this->assertSame($tie->away_team_id, $tie->fresh()->winner_team_id);
    }

    public function test_manual_fixture_creation_cannot_extend_a_snapshotted_format(): void
    {
        $event = $this->event();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $category = \App\Models\CategoryEvent::factory()->create(['event_id' => $event->id]);
        $teams = Team::factory()->count(2)->create(['category_event_id' => $category->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'team_format_snapshot' => ['id' => 1]]);
        $this->postJson(route('backend.team-fixtures.store'), ['draw_id' => $draw->id,
            'home_team_id' => $teams[0]->id, 'away_team_id' => $teams[1]->id, 'round_nr' => 1, 'tie_nr' => 1])->assertConflict();
        $this->assertDatabaseCount('team_ties', 0);
        $this->assertDatabaseCount('team_fixtures', 0);
    }
}
