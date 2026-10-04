<?php

namespace Tests\Feature\TeamDraw;

use App\Models\Draw;
use App\Models\Team;
use App\Models\TeamFixture;
use App\Models\TeamTie;
use App\Services\TeamEventRulesService;
use App\Services\TeamFixtureScoreService;
use App\Services\TeamRubberResultService;
use App\Services\TeamStandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TeamScoringRulesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Draw $draw;
    private TeamTie $tie;
    private TeamFixture $rubber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->draw = Draw::factory()->create();
        $this->draw->update(['team_scoring_rules' => app(TeamEventRulesService::class)->forDraw($this->draw)]);
        $this->tie = TeamTie::create(['draw_id' => $this->draw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'home_team_id' => Team::factory()->create()->id, 'away_team_id' => Team::factory()->create()->id, 'status' => TeamTie::STATUS_PUBLISHED, 'published_at' => now()]);
        $this->rubber = TeamFixture::create(['draw_id' => $this->draw->id, 'team_tie_id' => $this->tie->id,
            'match_nr' => 1, 'round_nr' => 1, 'rubber_sequence' => 1, 'rubber_code' => 'singles', 'fixture_type' => 1]);
    }

    private function save(array $scores): void
    {
        app(TeamFixtureScoreService::class)->save($this->rubber->fresh(), $scores);
    }

    public function test_partial_scores_do_not_complete_tie_or_award_points(): void
    {
        $this->save(['set1_home' => 6, 'set1_away' => 3]);
        $this->assertSame(2, $this->rubber->fresh()->match_status);
        $this->assertSame(TeamTie::STATUS_PUBLISHED, $this->tie->fresh()->status);
        $this->assertNull(app(TeamRubberResultService::class)->outcome($this->rubber->fresh())['winner']);
        $rows = app(TeamStandingsService::class)->forDraw($this->draw->fresh());
        $this->assertSame([0, 0], array_column($rows, 'points'));
        $this->assertSame([1, 1], array_column($rows, 'rank'));
    }

    public function test_deciding_set_counts_and_correction_delete_recompute_tie(): void
    {
        $this->save(['set1_home' => 6, 'set1_away' => 3, 'set2_home' => 2, 'set2_away' => 6, 'set3_home' => 6, 'set3_away' => 4]);
        $this->assertSame($this->tie->home_team_id, $this->tie->fresh()->winner_team_id);
        $this->assertSame(TeamTie::STATUS_COMPLETED, $this->tie->fresh()->status);
        $rows = app(TeamStandingsService::class)->forDraw($this->draw->fresh());
        $this->assertSame([2, 1], array_column($rows, 'points'));
        $this->assertSame([1, 1], array_column($rows, 'played'));
        $this->save(['set1_home' => 1, 'set1_away' => 6, 'set2_home' => 2, 'set2_away' => 6]);
        $this->assertSame($this->tie->away_team_id, $this->tie->fresh()->winner_team_id);
        $this->assertSame(2, $this->rubber->teamResults()->count());
        app(TeamFixtureScoreService::class)->delete($this->rubber->fresh());
        $this->assertSame(TeamTie::STATUS_PUBLISHED, $this->tie->fresh()->status);
        $this->assertNull($this->tie->fresh()->winner_team_id);
        $this->assertSame(0, $this->rubber->teamResults()->count());
    }

    public function test_invalid_scores_leave_existing_results_intact(): void
    {
        $this->save(['set1_home' => 6, 'set1_away' => 3]);
        foreach ([['set1_home' => 6], ['set1_home' => 6, 'set1_away' => 6], ['set2_home' => 6, 'set2_away' => 3],
            ['set1_home' => 6, 'set1_away' => 3, 'set2_home' => 6, 'set2_away' => 3, 'set3_home' => 6, 'set3_away' => 3],
            ['set1_home' => -1, 'set1_away' => 3], ['set1_home' => '1.5', 'set1_away' => 3]] as $scores) {
            try { $this->save($scores); $this->fail('Invalid score accepted.'); }
            catch (ValidationException $exception) { $this->assertNotEmpty($exception->errors()); }
            $this->assertSame(1, $this->rubber->teamResults()->count());
            $this->assertSame(6, (int) $this->rubber->teamResults()->first()->team1_score);
        }
    }

    public function test_custom_rules_and_doubles_close_loss_are_draw_scoped(): void
    {
        $this->rubber->update(['rubber_code' => 'doubles', 'fixture_type' => 2]);
        $this->save(['set1_home' => 9, 'set1_away' => 8]);
        $this->assertSame([2, 1], array_column(app(TeamStandingsService::class)->forDraw($this->draw->fresh()), 'points'));
        $other = Draw::factory()->create(['event_id' => $this->draw->event_id]);
        $this->assertSame([], app(TeamStandingsService::class)->forDraw($other));
        $rules = $this->draw->fresh()->team_scoring_rules;
        $rules['rubbers']['doubles']['straight_win'] = 7;
        $rules['tie_win'] = 4;
        $this->draw->update(['team_scoring_rules' => $rules]);
        $this->assertSame([11, 1], array_column(app(TeamStandingsService::class)->forDraw($this->draw->fresh()), 'points'));
    }

    public function test_drawn_tie_awards_event_bonus_and_repeated_save_is_idempotent(): void
    {
        $second = TeamFixture::create(['draw_id' => $this->draw->id, 'team_tie_id' => $this->tie->id,
            'match_nr' => 2, 'round_nr' => 1, 'rubber_sequence' => 2, 'rubber_code' => 'singles', 'fixture_type' => 1]);
        $scores = ['set1_home' => 6, 'set1_away' => 3, 'set2_home' => 6, 'set2_away' => 3];
        $this->save($scores);
        $this->save($scores);
        $this->assertSame(2, $this->rubber->teamResults()->count());
        $this->assertSame(TeamTie::STATUS_PUBLISHED, $this->tie->fresh()->status);
        app(TeamFixtureScoreService::class)->save($second, ['set1_home' => 1, 'set1_away' => 6, 'set2_home' => 2, 'set2_away' => 6]);
        $rules = $this->draw->fresh()->team_scoring_rules;
        $rules['tie_draw'] = 2;
        $this->draw->update(['team_scoring_rules' => $rules]);
        $rows = app(TeamStandingsService::class)->forDraw($this->draw->fresh());
        $this->assertSame([5, 5], array_column($rows, 'points'));
        $this->assertSame([1, 1], array_column($rows, 'draws'));
        $this->assertSame([1, 1], array_column($rows, 'rank'));
        $this->assertNull($this->tie->fresh()->winner_team_id);
        $rules['standings_order'] = ['points', 'game_difference'];
        $this->draw->update(['team_scoring_rules' => $rules]);
        $rows = app(TeamStandingsService::class)->forDraw($this->draw->fresh());
        $this->assertSame($this->tie->away_team_id, $rows[0]['team_id']);
        $this->assertSame([1, 2], array_column($rows, 'rank'));
    }

    public function test_locked_draw_rejects_correction_without_data_change(): void
    {
        $this->save(['set1_home' => 6, 'set1_away' => 3]);
        $this->draw->update(['locked' => true]);
        try { $this->save([]); $this->fail('Locked score changed.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        $this->assertSame(1, $this->rubber->teamResults()->count());
    }

    public function test_cross_draw_tie_relationship_rejects_scores(): void
    {
        $other = Draw::factory()->create();
        $this->tie->update(['draw_id' => $other->id]);
        try { $this->save(['set1_home' => 6, 'set1_away' => 3]); $this->fail('Cross-draw tie scored.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        $this->assertSame(0, $this->rubber->teamResults()->count());
    }

    public function test_public_fixture_scope_and_standings_exclude_private_ties(): void
    {
        $this->save(['set1_home' => 6, 'set1_away' => 3, 'set2_home' => 6, 'set2_away' => 3]);
        $this->assertSame(1, TeamFixture::where('draw_id', $this->draw->id)->publishedTeamTies()->count());
        $this->assertCount(2, app(TeamStandingsService::class)->forDraw($this->draw, true));
        $this->tie->update(['published_at' => null]);
        $this->assertSame(0, TeamFixture::where('draw_id', $this->draw->id)->publishedTeamTies()->count());
        $this->assertSame([], app(TeamStandingsService::class)->forDraw($this->draw, true));
        $this->assertCount(2, app(TeamStandingsService::class)->forDraw($this->draw));
        $this->draw->update(['team_scoring_rules' => null]);
        $this->assertSame(1, TeamFixture::where('draw_id', $this->draw->id)->publishedTeamTies()->count());
    }

    public function test_non_ajax_score_forms_redirect_and_completed_tie_can_be_corrected(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(\App\Models\User::factory()->create()->assignRole('super-user'));
        $this->post(route('frontend.fixtures.score.store', $this->rubber), ['set1_home' => 6, 'set1_away' => 3, 'set2_home' => 6, 'set2_away' => 3])->assertRedirect();
        $this->assertSame($this->tie->home_team_id, $this->tie->fresh()->winner_team_id);
        $this->postJson(route('frontend.fixtures.score.store', $this->rubber), ['set1_home' => 3, 'set1_away' => 6, 'set2_home' => 3, 'set2_away' => 6])->assertOk()->assertJsonPath('winner', 'away');
        $this->delete(route('frontend.fixtures.score.delete', $this->rubber))->assertRedirect();
        $this->assertSame(0, $this->rubber->teamResults()->count());
    }

    public function test_event_admin_can_correct_new_completed_tie_but_not_historical_or_locked(): void
    {
        \Illuminate\Support\Facades\DB::table('eventtypes')->insert(['id' => 3, 'name' => 'Team event', 'type' => \App\Models\EventType::TEAM]);
        $this->draw->update(['event_id' => \App\Models\Event::factory()->create(['eventType' => 3])->id]);
        $admin = \App\Models\User::factory()->create();
        \Illuminate\Support\Facades\DB::table('event_admins')->insert(['event_id' => $this->draw->event_id, 'user_id' => $admin->id]);
        $this->tie->update(['status' => TeamTie::STATUS_COMPLETED]);
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($admin)->allows('team-fixture.saveScore', $this->rubber->fresh()));
        $this->draw->update(['locked' => true]);
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($admin)->allows('team-fixture.saveScore', $this->rubber->fresh()));
        $this->draw->update(['locked' => false, 'team_scoring_rules' => null]);
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($admin)->allows('team-fixture.saveScore', $this->rubber->fresh()));
    }

    public function test_published_team_fixture_page_obeys_event_publication_gate(): void
    {
        $event = \App\Models\Event::factory()->create(['published' => false]);
        $this->draw->update(['event_id' => $event->id, 'published' => true]);
        $this->get(action([\App\Http\Controllers\Frontend\TeamFixtureFrontendController::class, 'index'], ['draw' => $this->draw->id]))->assertNotFound();
    }

    public function test_unpublished_canonical_tie_rejects_score_without_mutation(): void
    {
        foreach ([TeamTie::STATUS_DRAFT, TeamTie::STATUS_VALIDATED, TeamTie::STATUS_COMPLETED] as $status) {
            $this->tie->update(['published_at' => null, 'status' => $status]);
            try { $this->save(['set1_home' => 6, 'set1_away' => 3]); $this->fail('Unpublished tie scored.'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(409, $exception->getStatusCode());
                $this->assertSame('Validate and publish the tie before entering scores.', $exception->getMessage());
            }
            $this->assertSame(0, $this->rubber->teamResults()->count());
            $this->assertSame($status, $this->tie->fresh()->status);
            $this->assertNull($this->tie->fresh()->winner_team_id);
        }
    }

    public function test_historical_draw_keeps_score_lifecycle_and_tied_sets_have_no_winner(): void
    {
        $this->draw->update(['team_scoring_rules' => null]);
        $this->save(['set1_home' => 6, 'set1_away' => 3, 'set2_home' => 2, 'set2_away' => 6]);
        $this->assertSame(1, $this->rubber->fresh()->match_status);
        $this->assertSame(TeamTie::STATUS_PUBLISHED, $this->tie->fresh()->status);
        $this->assertNull(app(TeamRubberResultService::class)->outcome($this->rubber->fresh())['winner']);
    }
}
