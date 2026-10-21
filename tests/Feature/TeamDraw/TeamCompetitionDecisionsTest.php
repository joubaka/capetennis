<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{Category, CategoryEvent, Draw, DrawAuditLog, Event, EventType, NoProfileTeamPlayer, Team, TeamFixtureResult, TeamRegion, TeamTie, User};
use App\Services\{TeamCompetitionDecisionService, TeamEventRulesService, TeamPlacementCompetitionService, TeamPlacementResultsService, TeamPlayoffReadinessService, TeamStandingsService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamCompetitionDecisionsTest extends TestCase
{
    use RefreshDatabase;

    private Draw $draw;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('eventtypes')->insert(['id' => 3, 'name' => 'Team', 'type' => EventType::TEAM]);
        DB::table('draw_types')->insert(['drawTypeName' => 'Team - Mixed Doubles', 'btn_color' => 'primary', 'type' => 'team']);
        $event = Event::factory()->create(['eventType' => 3, 'published' => true, 'standings_published' => true]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $this->admin->id]);
        $categories = [];
        foreach (['Boys', 'Girls'] as $gender) $categories[] = CategoryEvent::factory()->create(['event_id' => $event->id,
            'category_id' => Category::factory()->create(['name' => 'U/15 '.$gender])->id])->id;
        foreach (['East', 'West', 'North', 'South'] as $name) {
            $region = TeamRegion::create(['region_name' => $name, 'short_name' => $name]);
            $event->regions()->attach($region->id);
            foreach ($categories as $category) {
                $team = Team::factory()->create(['category_event_id' => $category, 'region_id' => $region->id, 'name' => $name, 'num_team_members' => 6]);
                foreach (range(1, 6) as $rank) NoProfileTeamPlayer::create(['team_id' => $team->id, 'rank' => $rank, 'name' => 'Fictional', 'surname' => $name.' '.$rank, 'pay_status' => 0]);
            }
        }
        $service = app(TeamPlacementCompetitionService::class);
        $this->draw = $service->create($event, ['category_ids' => $categories, 'round_robin' => $service->defaults(6, false), 'playoff' => $service->defaults(6, true)]);
        $rules = app(TeamEventRulesService::class)->defaults();
        $rules['standings_order'] = ['points', 'tie_wins', 'rubber_difference', 'set_difference', 'game_difference'];
        $this->draw->update(['team_scoring_rules' => $rules, 'published' => true]);
        $this->actingAs($this->admin);
    }

    private function input(?Draw $draw = null): array
    {
        $draw ??= $this->draw->fresh();
        return ['order' => $draw->teams_in_draw()->pluck('teams.id')->reverse()->values()->all(),
            'reason' => 'Private disqualification evidence', 'accept_incomplete' => '1',
            'token' => app(TeamCompetitionDecisionService::class)->token($draw), 'request_id' => (string) Str::uuid()];
    }

    public function test_admin_can_override_incomplete_standings_and_exclude_a_side_before_progressing(): void
    {
        $input = $this->input(); $input['excluded'] = [$input['order'][0]];
        $url = route('team-draw.decision.standings', $this->draw);
        $this->post($url, $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, DrawAuditLog::where('action', 'organiser_standings')->count());
        $this->assertTrue(app(TeamPlayoffReadinessService::class)->forDraw($this->draw->fresh())['can_create']);
        $rows = app(TeamStandingsService::class)->forDraw($this->draw->fresh());
        $this->assertSame($input['order'], array_column($rows, 'team_id'));
        $this->assertTrue($rows[0]['qualification_excluded']);
        $playoff = app(TeamPlacementCompetitionService::class)->playoffs($this->draw->fresh());
        $this->assertNotContains($input['excluded'][0], $playoff->teams_in_draw()->pluck('teams.id')->all());
        $this->assertSame(0, TeamFixtureResult::count());
        $this->postJson($url, $this->input())->assertUnprocessable()->assertJsonValidationErrors('decision');
        $this->assertSame($this->admin->id, DrawAuditLog::where('action', 'organiser_standings')->first()->user_id);
    }

    public function test_decisions_require_event_admin_valid_sides_and_explicit_incomplete_confirmation(): void
    {
        $url = route('team-draw.decision.standings', $this->draw);
        $input = $this->input(); unset($input['accept_incomplete']);
        $this->postJson($url, $input)->assertUnprocessable();
        $input = $this->input(); $input['order'][0] = $input['order'][1];
        $this->postJson($url, $input)->assertUnprocessable();
        $input = $this->input(); $input['order'][0] = Team::factory()->create()->id;
        $this->postJson($url, $input)->assertUnprocessable();
        $stranger = User::factory()->create()->assignRole('admin');
        $this->actingAs($stranger)->postJson($url, $this->input())->assertForbidden();
        $this->assertSame(0, DrawAuditLog::where('action', 'organiser_standings')->count());
    }

    public function test_changed_scores_invalidate_a_decision_and_concurrent_admin_changes_are_rejected(): void
    {
        $url = route('team-draw.decision.standings', $this->draw);
        $input = $this->input();
        $this->post($url, $input)->assertRedirect()->assertSessionHasNoErrors();
        $other = $input; $other['reason'] = 'Another organiser ruling'; $other['request_id'] = (string) Str::uuid();
        $this->postJson($url, $other)->assertUnprocessable();
        $rubber = $this->draw->fixtures()->first();
        TeamFixtureResult::create(['team_fixture_id' => $rubber->id, 'set_nr' => 1, 'team1_score' => 6, 'team2_score' => 0]);
        $this->assertFalse(app(TeamCompetitionDecisionService::class)->standingsDecision($this->draw->fresh())['valid']);
        $this->assertFalse(app(TeamPlayoffReadinessService::class)->forDraw($this->draw->fresh())['can_create']);
    }

    private function tiedPlayoff(): array
    {
        $this->post(route('team-draw.decision.standings', $this->draw), $this->input())->assertRedirect();
        $playoff = app(TeamPlacementCompetitionService::class)->playoffs($this->draw->fresh());
        $tie = $playoff->teamTies()->first();
        $tie->update(['status' => TeamTie::STATUS_COMPLETED, 'published_at' => now()]);
        foreach ($tie->rubbers as $index => $rubber) {
            $home = $index % 2 === 0;
            $sets = in_array($rubber->rubber_code, ['singles', 'reverse_singles'], true) ? 2 : 1;
            foreach (range(1, $sets) as $set) TeamFixtureResult::create(['team_fixture_id' => $rubber->id, 'set_nr' => $set, 'team1_score' => $home ? 6 : 2, 'team2_score' => $home ? 2 : 6]);
        }
        return [$playoff, $tie];
    }

    public function test_twelve_all_playoff_uses_separate_audited_deciding_score_and_hides_private_reason(): void
    {
        [$playoff, $tie] = $this->tiedPlayoff();
        $this->assertSame(12, app(TeamStandingsService::class)->tieOutcome($tie->fresh())['home']);
        $before = TeamFixtureResult::count();
        $input = $this->input($playoff) + ['winner_team_id' => $tie->away_team_id, 'method' => 'mixed_tiebreak', 'home_score' => 8, 'away_score' => 10, 'participants' => 'Home boy and girl; away boy and girl'];
        $url = route('team-draw.decision.playoff', [$playoff, $tie]);
        $this->post($url, $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, $input)->assertRedirect()->assertSessionHasNoErrors();
        $freshPlayoff = $playoff->fresh();
        $saved = $freshPlayoff->team_draw_selection['decisions']['playoff_'.$tie->id];
        $this->assertSame($saved['fingerprint'], app(TeamCompetitionDecisionService::class)->fingerprint($freshPlayoff, $tie->id));
        $this->assertNotNull(app(TeamCompetitionDecisionService::class)->playoffDecision($tie->fresh()));
        $checkTie = $tie->fresh(); $checkTie->setRelation('draw', $freshPlayoff);
        $this->assertSame($tie->away_team_id, app(TeamStandingsService::class)->tieOutcome($checkTie)['winner_team_id']);
        $allResults = app(TeamPlacementResultsService::class)->forDraw($freshPlayoff);
        $result = collect($allResults)->firstWhere('tie_id', $tie->id);
        $this->assertNotNull($result, json_encode($allResults));
        $this->assertNotNull($result['winner_name'], json_encode($result));
        $this->assertSame(['home' => 8, 'away' => 10], $result['deciding_score']);
        $this->assertSame($before, TeamFixtureResult::count());
        $this->assertSame(1, DrawAuditLog::where('action', 'organiser_playoff_'.$tie->id)->count());
        $public = view('frontend.fixtures.partials.team-placement-results', ['placementSections' => [['draw' => $playoff, 'results' => [$result]]]])->render();
        $this->assertStringContainsString('8–10', $public);
        $this->assertStringNotContainsString('Private disqualification evidence', $public);
        $rubber = $tie->rubbers()->first(); $rubber->teamResults()->first()->update(['team1_score' => 7]);
        $this->assertNull(app(TeamPlacementResultsService::class)->forDraw($playoff->fresh())[0]['winner_name']);
    }

    public function test_disqualification_awards_unpaired_third_and_preserves_private_public_final_order(): void
    {
        $input = $this->input(); $input['excluded'] = [$input['order'][0]];
        $this->post(route('team-draw.decision.standings', $this->draw), $input)->assertRedirect()->assertSessionHasNoErrors();
        $public = app(\App\Services\EventStandingsService::class)->forEvent($this->draw->event);
        $this->assertSame($input['order'], array_column($public['sections'][0]['rows'], 'team_id'));
        $this->assertSame([1, 2, 3, 4], array_column($public['ageStandings']['U15'], 'rank'));
        $playoff = app(TeamPlacementCompetitionService::class)->playoffs($this->draw->fresh());
        $placements = app(TeamPlacementResultsService::class)->forDraw($playoff);
        $third = collect($placements)->firstWhere('position', 3);
        $this->assertTrue($third['automatic']);
        $this->assertSame($input['order'][3], $playoff->team_draw_selection['competition']['automatic_third_team_id']);
        $this->assertSame(1, $playoff->teamTies()->count());
        NoProfileTeamPlayer::where('team_id', $input['order'][1])->orderBy('id')->first()->update(['rank' => 99]);
        $this->assertTrue(app(TeamCompetitionDecisionService::class)->standingsDecision($this->draw->fresh())['valid']);
        $this->assertSame($input['order'], array_column(app(TeamStandingsService::class)->forDraw($this->draw->fresh()), 'team_id'));
    }

    public function test_legacy_three_side_public_third_uses_pairings_even_when_final_is_unpublished(): void
    {
        $input = $this->input(); $input['excluded'] = [$input['order'][0]];
        $this->post(route('team-draw.decision.standings', $this->draw), $input)->assertRedirect()->assertSessionHasNoErrors();
        $playoff = app(TeamPlacementCompetitionService::class)->playoffs($this->draw->fresh());
        $selection = $playoff->team_draw_selection;
        unset($selection['competition']['automatic_third_team_id']);
        $playoff->team_draw_selection = $selection; $playoff->published = true; $playoff->save();
        $this->assertArrayNotHasKey('automatic_third_team_id', $playoff->fresh()->team_draw_selection['competition']);
        $results = app(TeamPlacementResultsService::class)->forDraw($playoff->fresh(), true);
        $this->assertCount(1, $results);
        $this->assertSame(3, $results[0]['position']);
        $team = Team::findOrFail($input['order'][3]);
        $this->assertSame(app(\App\Services\TeamDrawSideResolver::class)->label($playoff, $team), $results[0]['winner_name']);
    }

    public function test_super_user_can_confirm_two_eligible_sides_without_inventing_third_place(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $input = $this->input(); $input['excluded'] = array_slice($input['order'], 0, 2);
        $this->post(route('team-draw.decision.standings', $this->draw), $input)->assertRedirect()->assertSessionHasNoErrors();
        $playoff = app(TeamPlacementCompetitionService::class)->playoffs($this->draw->fresh());
        $this->assertSame(2, $playoff->teams_in_draw()->count());
        $this->assertCount(1, app(TeamPlacementResultsService::class)->forDraw($playoff));
    }

    public function test_other_encounter_scores_do_not_invalidate_a_playoff_decision_and_ruling_is_audited(): void
    {
        [$playoff, $tie] = $this->tiedPlayoff();
        $input = $this->input($playoff) + ['winner_team_id' => $tie->home_team_id, 'method' => 'ruling'];
        $this->post(route('team-draw.decision.playoff', [$playoff, $tie]), $input)->assertRedirect()->assertSessionHasNoErrors();
        $other = $playoff->teamTies()->whereKeyNot($tie->id)->first()->rubbers()->first();
        TeamFixtureResult::create(['team_fixture_id' => $other->id, 'set_nr' => 1, 'team1_score' => 6, 'team2_score' => 2]);
        $unrelatedSide = $playoff->teamTies()->whereKeyNot($tie->id)->first()->home_team_id;
        NoProfileTeamPlayer::where('team_id', $unrelatedSide)->orderBy('id')->first()->update(['rank' => 99]);
        $this->assertNotNull(app(TeamCompetitionDecisionService::class)->playoffDecision($tie->fresh()));
        $this->assertSame('ruling', collect(app(TeamPlacementResultsService::class)->forDraw($playoff->fresh()))->firstWhere('tie_id', $tie->id)['decision_method']);
    }

    public function test_sets_then_games_method_uses_real_encounter_totals(): void
    {
        [$playoff, $tie] = $this->tiedPlayoff();
        $winningHomeRubber = $tie->rubbers()->orderBy('rubber_sequence')->first();
        $winningHomeRubber->teamResults()->where('set_nr', 1)->update(['team1_score' => 7]);
        $input = $this->input($playoff) + ['winner_team_id' => $tie->home_team_id, 'method' => 'sets_games'];
        $this->post(route('team-draw.decision.playoff', [$playoff, $tie]), $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($tie->home_team_id, app(TeamStandingsService::class)->tieOutcome($tie->fresh())['winner_team_id']);
    }

    public function test_playoff_validation_preserves_only_submitted_encounter_input(): void
    {
        [$playoff, $tie] = $this->tiedPlayoff();
        $input = $this->input($playoff) + ['decision_tie_id' => $tie->id, 'winner_team_id' => $tie->away_team_id,
            'method' => 'mixed_tiebreak', 'home_score' => 9, 'away_score' => 10, 'participants' => 'Four named deciding players'];
        $this->from(route('backend.team-draw.standings', $playoff))->post(route('team-draw.decision.playoff', [$playoff, $tie]), $input)
            ->assertRedirect()->assertSessionHasErrors('decision')->assertSessionHasInput('participants', 'Four named deciding players')
            ->assertSessionHasInput('home_score', 9)->assertSessionHasInput('method', 'mixed_tiebreak');
        $this->get(route('backend.team-draw.standings', $playoff))->assertOk()->assertSee('Four named deciding players')->assertSee('Private disqualification evidence');
    }

    public function test_public_http_results_show_decision_without_private_reason_or_players_evidence(): void
    {
        [$playoff, $tie] = $this->tiedPlayoff();
        $input = $this->input($playoff) + ['winner_team_id' => $tie->away_team_id, 'method' => 'mixed_tiebreak', 'home_score' => 8, 'away_score' => 10, 'participants' => 'Private deciding player evidence'];
        $this->post(route('team-draw.decision.playoff', [$playoff, $tie]), $input)->assertRedirect()->assertSessionHasNoErrors();
        $playoff->published = true; $playoff->save();
        auth()->logout();
        $this->get(route('frontend.team-draw.standings', $playoff))->assertOk()->assertSee('8–10')
            ->assertDontSee('Private disqualification evidence')->assertDontSee('Private deciding player evidence');
        $this->get(route('frontend.events.standings', $playoff->event))->assertOk()->assertSee('8–10')
            ->assertDontSee('Private disqualification evidence')->assertDontSee('Private deciding player evidence');
    }

    public function test_playoff_rejects_invalid_tiebreak_score_foreign_tie_and_false_sets_games_winner(): void
    {
        [$playoff, $tie] = $this->tiedPlayoff();
        $input = $this->input($playoff) + ['winner_team_id' => $tie->away_team_id, 'method' => 'mixed_tiebreak', 'home_score' => 9, 'away_score' => 10, 'participants' => 'Four players'];
        $this->postJson(route('team-draw.decision.playoff', [$playoff, $tie]), $input)->assertUnprocessable();
        $input['method'] = 'sets_games';
        $this->postJson(route('team-draw.decision.playoff', [$playoff, $tie]), $input)->assertUnprocessable();
        $this->postJson(route('team-draw.decision.playoff', [$this->draw, $tie]), $input)->assertNotFound();
    }
}
