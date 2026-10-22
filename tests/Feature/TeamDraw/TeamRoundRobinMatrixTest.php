<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{CategoryEvent, Draw, DrawType, Event, EventType, Player, Team, TeamFixture, TeamFixturePlayer, TeamTie, User};
use App\Services\{TeamEventRulesService, TeamRoundRobinMatrixService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamRoundRobinMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Draw $draw;
    private TeamTie $tie;
    private TeamFixture $rubber;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('eventtypes')->insert(['id' => 3, 'name' => 'Team', 'type' => EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => 3, 'name' => 'Round Robin QA']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $type = DrawType::forceCreate(['drawTypeName' => 'Team round robin', 'type' => 'team', 'btn_color' => 'primary']);
        $this->draw = Draw::factory()->create(['event_id' => $event->id, 'drawType_id' => $type->id,
            'drawName' => 'Under 15 team round robin', 'team_format_snapshot' => ['pairing_format' => 'round_robin'],
            'team_scoring_rules' => app(TeamEventRulesService::class)->defaults()]);
        $teams = collect(['Oak High School', 'Pine College', 'Coastal Academy'])->map(fn ($name) =>
            Team::factory()->create(['category_event_id' => $category->id, 'name' => $name]));
        $this->tie = TeamTie::create(['draw_id' => $this->draw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'home_team_id' => $teams[0]->id, 'away_team_id' => $teams[1]->id, 'status' => 'published', 'published_at' => now()]);
        $this->rubber = $this->rubber($this->tie, 1);
        $other = TeamTie::create(['draw_id' => $this->draw->id, 'round_nr' => 2, 'tie_nr' => 1,
            'home_team_id' => $teams[1]->id, 'away_team_id' => $teams[2]->id, 'status' => 'published', 'published_at' => now()]);
        $this->rubber($other, 2);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
    }

    private function rubber(TeamTie $tie, int $match): TeamFixture
    {
        $rubber = TeamFixture::create(['draw_id' => $tie->draw_id, 'team_tie_id' => $tie->id,
            'round_nr' => $tie->round_nr, 'match_nr' => $match, 'rubber_sequence' => 1,
            'rubber_code' => 'singles', 'fixture_type' => 1]);
        TeamFixturePlayer::create(['team_fixture_id' => $rubber->id, 'slot_no' => 1,
            'team1_id' => Player::factory()->create()->id, 'team2_id' => Player::factory()->create()->id]);
        return $rubber;
    }

    public function test_matrix_is_default_and_selected_tie_uses_existing_score_workflow(): void
    {
        $url = route('frontend.fixtures.enter-scores', $this->draw);
        $matrix = $this->get($url)->assertOk()->assertViewIs('frontend.fixtures.team-round-robin-scoring')
            ->assertSee('data-team-round-robin-matrix', false)->assertSee('Not scheduled')
            ->assertSee(route('frontend.fixtures.enter-scores', ['draw' => $this->draw->id, 'tie' => $this->tie->id]), false);
        $scores = $this->get($url.'?tie='.$this->tie->id)->assertOk()->assertViewIs('frontend.fixtures.enter-score');
        $this->assertSame([$this->rubber->id], $scores->viewData('fixtures')->pluck('id')->all());
        $originalRules = $this->draw->team_scoring_rules;
        $this->postJson(route('frontend.fixtures.score.store', $this->rubber),
            ['set1_home' => 6, 'set1_away' => 2, 'set2_home' => 6, 'set2_away' => 3])->assertOk()->assertJsonPath('success', true);
        $updated = $this->get($url)->assertOk()->assertSee('Complete');
        $data = $updated->viewData('matrix');
        $this->assertSame(1, $data['cells'][$this->tie->home_team_id][$this->tie->away_team_id][0]['for']);
        $this->assertSame(0, $data['cells'][$this->tie->away_team_id][$this->tie->home_team_id][0]['for']);
        $this->assertSame(3, collect($data['rows'])->firstWhere('team_id', $this->tie->home_team_id)['points']);
        $this->assertEquals($originalRules, $this->draw->fresh()->team_scoring_rules);
        $this->assertDatabaseCount('team_fixture_results', 2);
        $this->assertDatabaseCount('team_ties', 2);
        $this->assertDatabaseHas('draw_audit_logs', ['draw_id' => $this->draw->id, 'action' => 'score_saved']);
        $correction = ['set1_home' => 2, 'set1_away' => 6, 'set2_home' => 3, 'set2_away' => 6];
        $this->postJson(route('frontend.fixtures.score.store', $this->rubber), $correction)->assertOk();
        $this->postJson(route('frontend.fixtures.score.store', $this->rubber), $correction)->assertOk();
        $this->assertDatabaseCount('team_fixture_results', 2);
        $corrected = $this->get($url)->assertOk()->viewData('matrix');
        $this->assertSame(1, $corrected['cells'][$this->tie->away_team_id][$this->tie->home_team_id][0]['for']);
        $this->assertDatabaseHas('draw_audit_logs', ['draw_id' => $this->draw->id, 'action' => 'score_corrected']);
        $workspace = $this->get(route('frontend.scoring.workspace', ['event' => $this->draw->event_id, 'draw' => $this->draw->id]))->assertOk();
        $this->assertCount(1, $workspace->viewData('roundRobinMatrices'));
        $workspace->assertSee('data-round-robin-match-list', false);
        $this->assertLessThan(strpos($workspace->getContent(), 'data-round-robin-match-list'),
            strpos($workspace->getContent(), 'data-team-round-robin-matrix'));
        $eventStandings = $this->get(route('admin.events.standings', $this->draw->event_id))->assertOk()
            ->assertSee('data-team-round-robin-matrix', false);
        $this->assertLessThan(strpos($eventStandings->getContent(), 'standings by region / school'),
            strpos($eventStandings->getContent(), 'data-team-round-robin-matrix'));
        if (getenv('CT_MATRIX_QA')) {
            $path = storage_path('app/local-qa/team-matrix');
            if (!is_dir($path)) { mkdir($path, 0777, true); }
            foreach (['matrix' => $updated, 'scores' => $scores, 'workspace' => $workspace, 'standings' => $eventStandings] as $name => $response) {
                file_put_contents($path.'/'.$name.'.html', $response->getContent());
            }
        }
    }

    public function test_foreign_tie_is_rejected_and_ordinary_user_cannot_access_scoring(): void
    {
        $otherDraw = Draw::factory()->create(['event_id' => Event::factory()->create(['eventType' => 3])->id]);
        $foreign = TeamTie::create(['draw_id' => $otherDraw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'home_team_id' => $this->tie->home_team_id, 'away_team_id' => $this->tie->away_team_id, 'status' => 'draft']);
        $this->get(route('frontend.fixtures.enter-scores', ['draw' => $this->draw->id, 'tie' => $foreign->id]))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('frontend.fixtures.enter-scores', $this->draw))->assertForbidden();
        $this->assertDatabaseCount('team_fixture_results', 0);
    }

    public function test_playoffs_and_knockout_draws_retain_existing_match_view(): void
    {
        $this->draw->forceFill(['team_draw_selection' => ['competition' => ['phase' => 'playoff']]])->save();
        $this->get(route('frontend.fixtures.enter-scores', $this->draw))->assertOk()->assertViewIs('frontend.fixtures.enter-score');
        $type = DrawType::forceCreate(['drawTypeName' => 'Team knockout', 'type' => 'team', 'btn_color' => 'primary']);
        $this->draw->forceFill(['team_draw_selection' => null, 'team_format_snapshot' => null, 'drawType_id' => $type->id])->save();
        $this->assertFalse(app(TeamRoundRobinMatrixService::class)->supports($this->draw->fresh()));
        $this->get(route('frontend.fixtures.enter-scores', $this->draw))->assertOk()->assertViewIs('frontend.fixtures.enter-score');
    }

    public function test_published_matrix_excludes_draft_ties_and_repeated_encounters_are_preserved(): void
    {
        $repeat = TeamTie::create(['draw_id' => $this->draw->id, 'round_nr' => 3, 'tie_nr' => 1,
            'home_team_id' => $this->tie->home_team_id, 'away_team_id' => $this->tie->away_team_id, 'status' => 'draft']);
        $service = app(TeamRoundRobinMatrixService::class);
        $all = $service->forDraw($this->draw);
        $this->assertCount(2, $all['cells'][$this->tie->home_team_id][$this->tie->away_team_id]);
        $published = $service->forDraw($this->draw, publishedOnly: true);
        $this->assertCount(1, $published['cells'][$this->tie->home_team_id][$this->tie->away_team_id]);
        $this->assertSame($this->tie->id, $published['cells'][$this->tie->home_team_id][$this->tie->away_team_id][0]['tie']->id);
    }

    public function test_started_match_without_scores_is_in_progress(): void
    {
        $this->rubber->update(['match_status' => \App\Domain\Draws\Enums\FixtureState::STATUS_PARTIAL]);
        $matrix = app(TeamRoundRobinMatrixService::class)->forDraw($this->draw);
        $this->assertSame('In progress', $matrix['cells'][$this->tie->home_team_id][$this->tie->away_team_id][0]['status']);
        $this->assertDatabaseCount('team_fixture_results', 0);
    }

    public function test_venue_restricted_scorer_retains_only_authorized_match_list(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'score-keeper', 'guard_name' => 'web']);
        $user = User::factory()->create()->assignRole('score-keeper');
        $venue = \App\Models\Venue::forceCreate(['name' => 'Assigned court']);
        $otherVenue = \App\Models\Venue::forceCreate(['name' => 'Private court']);
        $this->rubber->update(['venue_id' => $venue->id]);
        TeamFixture::where('draw_id', $this->draw->id)->where('id', '!=', $this->rubber->id)->update(['venue_id' => $otherVenue->id]);
        \App\Models\EventConvenor::create(['event_id' => $this->draw->event_id, 'user_id' => $user->id,
            'role' => 'score-keeper', 'venue_id' => $venue->id]);
        $this->actingAs($user);
        $response = $this->get(route('frontend.fixtures.enter-scores', $this->draw))->assertOk()
            ->assertViewIs('frontend.fixtures.enter-score')->assertDontSee('data-team-round-robin-matrix', false);
        $this->assertSame([$this->rubber->id], $response->viewData('fixtures')->pluck('id')->all());
        $workspace = $this->get(route('frontend.scoring.workspace', ['event' => $this->draw->event_id, 'draw' => $this->draw->id]))
            ->assertOk()->assertDontSee('data-team-round-robin-matrix', false);
        $this->assertSame([], $workspace->viewData('roundRobinMatrices'));
    }
}
