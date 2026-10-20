<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{Category, CategoryEvent, Draw, Event, EventType, NoProfileTeamPlayer, Team, TeamFixture, TeamRegion, TeamTie, User};
use App\Services\{TeamDrawAdaptationService, TeamDrawGenerationService, TeamDrawScoringPublicationService, TeamEventRulesService, TeamFixtureScoreService, TeamPlacementCompetitionService, TeamStandingsService, TeamTieValidationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamPlacementCompetitionTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $admin;
    private array $categories = [];
    private TeamPlacementCompetitionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('eventtypes')->insert(['id' => 3, 'name' => 'Team', 'type' => EventType::TEAM]);
        $this->event = Event::factory()->create(['eventType' => 3]);
        DB::table('draw_types')->insert(['drawTypeName' => 'Team - Mixed Doubles', 'btn_color' => 'primary', 'type' => 'team']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
        foreach (['Boys', 'Girls'] as $gender) {
            $category = Category::factory()->create(['name' => 'u/15 '.$gender]);
            $this->categories[] = CategoryEvent::factory()->create(['event_id' => $this->event->id, 'category_id' => $category->id])->id;
        }
        foreach (['East', 'West', 'North', 'South'] as $name) {
            $region = TeamRegion::create(['region_name' => $name, 'short_name' => $name]);
            foreach ($this->categories as $index => $category) {
                $team = Team::factory()->create(['category_event_id' => $category, 'region_id' => $region->id,
                    'name' => $name.($index ? ' Girls' : ' Boys'), 'num_team_members' => 6]);
                for ($rank = 1; $rank <= 6; $rank++) NoProfileTeamPlayer::create(['team_id' => $team->id, 'rank' => $rank,
                    'name' => $name, 'surname' => 'Player '.$index.' '.$rank, 'pay_status' => 0]);
            }
        }
        $this->service = app(TeamPlacementCompetitionService::class);
    }

    private function input(): array
    {
        return ['category_ids' => $this->categories, 'round_robin' => $this->service->defaults(6, false), 'playoff' => $this->service->defaults(6, true)];
    }

    public function test_readiness_blocks_empty_sources_and_does_not_resolve_foreign_playoffs(): void
    {
        $draw = $this->service->create($this->event, $this->input());
        $foreign = Draw::factory()->create();
        $selection = $draw->team_draw_selection;
        $selection['competition']['playoff_draw_id'] = $foreign->id;
        $draw->team_draw_selection = $selection;
        $readiness = app(\App\Services\TeamPlayoffReadinessService::class)->forDraw($draw);
        $this->assertNull($readiness['playoff']);
        $this->assertFalse($readiness['can_create']);
        $this->assertContains('Linked playoff is unavailable; review competition setup.', $readiness['blockers']);
        $selection['competition']['playoff_draw_id'] = 999999999;
        $draw->team_draw_selection = $selection;
        $this->assertContains('Linked playoff is unavailable; review competition setup.', app(\App\Services\TeamPlayoffReadinessService::class)->forDraw($draw)['blockers']);
        $draw->team_draw_selection = ['competition' => $selection['competition']];
        $draw->setRelation('teams_in_draw', collect());
        $readiness = app(\App\Services\TeamPlayoffReadinessService::class)->forDraw($draw);
        $this->assertFalse($readiness['can_create']);
        $this->assertFalse($readiness['ranks_resolved']);
        $this->assertContains('At least two valid selected sides are required.', $readiness['blockers']);
        $this->assertDatabaseCount('draws', 2);
    }

    public function test_custom_formats_create_one_composite_group_and_replay_without_duplicates(): void
    {
        $input = $this->input();
        $input['round_robin'][0]['name'] = 'Opening boys doubles';
        $setup = $this->actingAs($this->admin)->get(route('team-draw.competition.setup', $this->event))->assertOk()->assertSee('To be decided');
        if ($directory = getenv('CT_PLACEMENT_QA_OUTPUT')) {
            if (!is_dir($directory)) mkdir($directory, 0777, true);
            file_put_contents($directory.'/setup.html', str_replace(['http://localhost/', 'https://localhost/'], 'http://localhost/ct/public/', $setup->getContent()));
        }
        $response = $this->actingAs($this->admin)->postJson(route('team-draw.competition.store', $this->event), $input)->assertOk();
        $draw = Draw::firstOrFail();
        $this->assertSame('0', (string) $draw->gender, 'The legacy integer gender column must receive its mixed code.');
        $this->assertSame('mixed', app(TeamStandingsService::class)->drawGender($draw));
        $response->assertJsonPath('url', route('draw.guide.show', [$this->event, $draw]));
        $guide = app(\App\Services\Draw\DrawSetupGuide::class)->state($draw->fresh());
        $this->assertSame(4, $guide['next'], 'Undecided scoring must allow programme scheduling before publication.');
        $this->assertFalse($guide['steps'][5]['complete']);
        $this->assertNull($guide['linkedPlayoff']);
        $guidePage = $this->get(route('draw.guide.show', [$this->event, $draw]))->assertOk()
            ->assertSee('Programme &amp; schedule', false)->assertSee('Playoff pairings and times remain pending');
        if ($directory = getenv('CT_PLACEMENT_QA_OUTPUT')) file_put_contents($directory.'/guide.html', str_replace(['http://localhost/', 'https://localhost/'], 'http://localhost/ct/public/', $guidePage->getContent()));
        $this->assertSame(6, $draw->teamTies()->count());
        $this->assertSame(72, $draw->fixtures()->count());
        $this->assertCount(4, $draw->team_draw_selection['mixed_sides']);
        $this->assertCount(24, $draw->team_draw_selection['competition']['playoff_format']['rubbers']);
        foreach ($draw->teamTies as $tie) app(TeamTieValidationService::class)->assertTieComplete($tie);
        $operations = $this->actingAs($this->admin)->get(route('backend.team-draw.operations', $draw))->assertOk()->assertSee('To be decided');
        if ($directory = getenv('CT_PLACEMENT_QA_OUTPUT')) file_put_contents($directory.'/operations.html', str_replace(['http://localhost/', 'https://localhost/'], 'http://localhost/ct/public/', $operations->getContent()));
        $this->assertTrue($draw->team_scoring_rules['undecided']);
        $readiness = app(\App\Services\TeamPlayoffReadinessService::class)->forDraw($draw);
        $this->assertFalse($readiness['can_create']);
        $this->assertSame(6, $readiness['expected_encounters']);
        $this->assertSame(0, $readiness['completed_encounters']);
        $this->assertSame(72, $readiness['total_rubbers']);
        $this->assertSame(['singles' => 12, 'doubles' => 6, 'mixed_doubles' => 6], $readiness['format_counts']);
        $guidePage->assertSee('Playoff readiness')->assertSee('24 matches per encounter')->assertSee('Creation and scheduling pending.');
        $operations->assertSee('data-playoff-readiness="'.$draw->id.'"', false);
        $this->assertNull($draw->fresh()->team_draw_selection['competition']['playoff_draw_id'] ?? null);
        $this->assertSame([], app(TeamStandingsService::class)->forDraw($draw));
        $this->service->create($this->event, $input);
        $this->assertDatabaseCount('draws', 1);
        $this->assertDatabaseCount('team_ties', 6);
        $input['round_robin'][0]['name'] = 'Changed request';
        $this->actingAs($this->admin)->postJson(route('team-draw.competition.store', $this->event), $input)->assertUnprocessable();
    }

    public function test_undecided_scoring_blocks_publication_score_writes_and_qualification(): void
    {
        $draw = $this->service->create($this->event, $this->input());
        $tie = $draw->teamTies()->firstOrFail();
        $this->actingAs($this->admin)->postJson(route('team-draw.ties.validate', $tie))->assertOk();
        $this->actingAs($this->admin)->postJson(route('team-draw.ties.publish', $tie))->assertConflict();
        $this->actingAs($this->admin)->postJson(route('team-draw.competition.playoffs', $draw))->assertUnprocessable();
        try { app(TeamDrawScoringPublicationService::class)->enableLocked($draw); $this->fail('Publication should be blocked.'); }
        catch (\RuntimeException $exception) { $this->assertStringContainsString('Scoring is to be decided', $exception->getMessage()); }
        try { app(TeamFixtureScoreService::class)->save($draw->fixtures()->first(), []); $this->fail('Scoring should be blocked.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        $this->assertDatabaseCount('team_fixture_results', 0);
    }

    public function test_complete_published_group_generates_only_placement_pairs_and_freezes_source_results(): void
    {
        $draw = $this->service->create($this->event, $this->input());
        $rules = app(TeamEventRulesService::class)->defaults();
        $rules['standings_order'] = ['tie_wins', 'points'];
        $this->actingAs($this->admin)->post(route('team-draw.competition.scoring', $draw), ['rules' => $rules])->assertRedirect();
        $draw->refresh();
        foreach ($draw->teamTies()->with('rubbers')->get() as $tie) {
            app(TeamTieValidationService::class)->assertTieComplete($tie);
            $tie->update(['status' => TeamTie::STATUS_PUBLISHED, 'published_at' => now()]);
            foreach ($tie->rubbers as $rubber) {
                $homeWins = $tie->home_team_id < $tie->away_team_id;
                app(TeamFixtureScoreService::class)->save($rubber, ['set1_home' => $homeWins ? 6 : 0, 'set1_away' => $homeWins ? 0 : 6]);
            }
        }
        $before = app(TeamStandingsService::class)->forDraw($draw);
        $readiness = app(\App\Services\TeamPlayoffReadinessService::class)->forDraw($draw->fresh());
        $this->assertTrue($readiness['can_create']);
        $this->assertSame(6, $readiness['completed_encounters']);
        $this->assertSame(72, $readiness['completed_rubbers']);
        $draw->update(['locked' => true]);
        $this->assertFalse(app(\App\Services\TeamPlayoffReadinessService::class)->forDraw($draw->fresh())['can_create']);
        $draw->update(['locked' => false]);
        $draw->update(['published' => 1]);
        $this->actingAs($this->admin)->post(route('team-draw.competition.playoffs', $draw))->assertRedirect();
        $playoff = Draw::findOrFail($draw->fresh()->team_draw_selection['competition']['playoff_draw_id']);
        $this->assertSame('0', (string) $playoff->gender);
        $this->assertSame('mixed', app(TeamStandingsService::class)->drawGender($playoff));
        $this->assertSame(2, $playoff->teamTies()->count());
        $this->assertSame(48, $playoff->fixtures()->count());
        $pairs = $playoff->teamTies()->orderBy('tie_nr')->get()->map(fn ($tie) => [$tie->home_team_id, $tie->away_team_id])->all();
        $this->assertSame([[$before[0]['team_id'], $before[1]['team_id']], [$before[2]['team_id'], $before[3]['team_id']]], $pairs);
        foreach ($playoff->teamTies as $tie) app(TeamTieValidationService::class)->assertTieComplete($tie);
        $this->assertSame($playoff->id, $this->service->playoffs($draw)->id);
        $readiness = app(\App\Services\TeamPlayoffReadinessService::class)->forDraw($draw->fresh());
        $this->assertFalse($readiness['can_create']);
        $this->assertSame($playoff->id, $readiness['playoff']->id);
        $this->assertNull(app(\App\Services\TeamPlayoffReadinessService::class)->forDraw($playoff));
        app(TeamDrawAdaptationService::class)->adaptDraw($playoff);
        $this->assertSame(2, $playoff->teamTies()->count());
        $this->assertSame($before, app(TeamStandingsService::class)->forDraw($draw->fresh()));
        try { app(TeamFixtureScoreService::class)->delete($draw->fixtures()->first()); $this->fail('Qualified results must be frozen.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        try { app(TeamDrawGenerationService::class)->generate($playoff, app(\App\Services\TeamDrawSideResolver::class)->teams($playoff)); $this->fail('Playoffs must not become round robin.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
    }

    public function test_cross_event_categories_and_unrelated_user_are_rejected(): void
    {
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('team-draw.competition.setup', $this->event))->assertForbidden();
        $this->actingAs($stranger)->postJson(route('team-draw.competition.store', $this->event), $this->input())->assertForbidden();
        $other = CategoryEvent::factory()->create();
        $input = $this->input(); $input['category_ids'][0] = $other->id;
        $this->actingAs($this->admin)->postJson(route('team-draw.competition.store', $this->event), $input)->assertUnprocessable();
        $this->assertDatabaseCount('draws', 0);
    }

    public function test_size_defaults_scale_without_double_counting_roster_sources_and_reject_invalid_pairings(): void
    {
        foreach ([2, 4, 6, 8, 12] as $size) {
            $rr = $this->service->defaults($size, false);
            $this->assertCount($size * 2, $rr);
            $mixed = array_filter($rr, fn ($row) => $row['rubber_code'] === 'mixed_doubles');
            $this->assertCount($size, $mixed);
            $positions = array_merge(...array_column($mixed, 'home_positions')); sort($positions);
            $this->assertSame(range(1, $size * 2), $positions);
            $this->assertCount($size * 4, $this->service->defaults($size, true));
        }
        $input = $this->input(); $input['round_robin'][0]['home_positions'] = [1, 2];
        $this->actingAs($this->admin)->postJson(route('team-draw.competition.store', $this->event), $input)->assertUnprocessable();
        $this->assertDatabaseCount('draws', 0);
        Team::first()->update(['num_team_members' => 5]);
        $this->actingAs($this->admin)->postJson(route('team-draw.competition.store', $this->event), $this->input())->assertUnprocessable();
    }

    public function test_eight_player_rosters_generate_complete_editable_formats(): void
    {
        foreach (Team::all() as $team) {
            $team->update(['num_team_members' => 8]);
            foreach ([7, 8] as $rank) NoProfileTeamPlayer::create(['team_id' => $team->id, 'rank' => $rank, 'name' => 'Reserve', 'surname' => 'Player '.$rank, 'pay_status' => 0]);
        }
        $group = $this->service->groups($this->event)[0];
        $this->assertSame(8, $group['size']);
        $draw = $this->service->create($this->event, ['category_ids' => $this->categories,
            'round_robin' => $group['round_robin'], 'playoff' => $group['playoff']]);
        $this->assertSame(96, $draw->fixtures()->count());
        $this->assertCount(32, $draw->team_draw_selection['competition']['playoff_format']['rubbers']);
        foreach ($draw->teamTies as $tie) app(TeamTieValidationService::class)->assertTieComplete($tie);
    }

    public function test_incomplete_and_tied_standings_cannot_qualify_teams(): void
    {
        $draw = $this->service->create($this->event, $this->input());
        $rules = app(TeamEventRulesService::class)->defaults();
        // Zero points deliberately leaves all four sides sharing first place.
        foreach ($rules['rubbers'] as &$rubber) foreach (['straight_win', 'deciding_win', 'loss', 'deciding_loss', 'close_loss'] as $key) $rubber[$key] = 0;
        unset($rubber);
        $this->service->configureScoring($draw, $rules);
        $this->actingAs($this->admin)->postJson(route('team-draw.competition.playoffs', $draw))->assertUnprocessable()->assertSee('Complete every round-robin tie');
        foreach ($draw->teamTies()->with('rubbers')->get() as $tie) {
            $tie->update(['status' => TeamTie::STATUS_PUBLISHED, 'published_at' => now()]);
            foreach ($tie->rubbers as $rubber) app(TeamFixtureScoreService::class)->save($rubber, ['set1_home' => 6, 'set1_away' => 0]);
        }
        $this->actingAs($this->admin)->postJson(route('team-draw.competition.playoffs', $draw))->assertUnprocessable()->assertSee('Resolve tied standings');
        $readiness = app(\App\Services\TeamPlayoffReadinessService::class)->forDraw($draw->fresh());
        $this->assertFalse($readiness['can_create']);
        $this->assertFalse($readiness['ranks_resolved']);
        $this->assertContains('Resolve tied standings using the agreed scoring criteria.', $readiness['blockers']);
        $this->assertDatabaseCount('draws', 1);
        $this->assertDatabaseCount('team_ties', 6);
    }

    public function test_roster_changes_refresh_existing_phase_lineups_and_require_publication_review(): void
    {
        $draw = $this->service->create($this->event, $this->input());
        $tie = $draw->teamTies()->firstOrFail();
        $tie->update(['status' => TeamTie::STATUS_PUBLISHED, 'published_at' => now()]);
        $team = $tie->homeTeam;
        $original = $team->team_players_no_profile()->where('rank', 1)->firstOrFail();
        $original->delete();
        $replacement = NoProfileTeamPlayer::create(['team_id' => $team->id, 'rank' => 1, 'name' => 'New', 'surname' => 'Player', 'pay_status' => 0]);
        $report = app(TeamDrawAdaptationService::class)->adaptDraw($draw);
        $this->assertGreaterThan(0, $report['updated_lineups']);
        $this->assertSame(6, $draw->teamTies()->count());
        $this->assertSame(72, $draw->fixtures()->count());
        $this->assertNull($tie->fresh()->published_at);
        $this->assertSame(TeamTie::STATUS_DRAFT, $tie->fresh()->status);
        $this->assertTrue($tie->rubbers()->whereHas('fixturePlayers', fn ($q) => $q->where('team1_no_profile_id', $replacement->id))->exists());
    }

    public function test_publication_rejects_imported_girl_in_boys_doubles(): void
    {
        $draw = $this->service->create($this->event, $this->input());
        $tie = $draw->teamTies()->firstOrFail();
        $girlId = $draw->team_draw_selection['mixed_sides'][$tie->home_team_id]['girls'];
        $girl = NoProfileTeamPlayer::where('team_id', $girlId)->firstOrFail();
        $rubber = $tie->rubbers()->where('gender_rule', 'male')->firstOrFail();
        $rubber->fixturePlayers()->first()->update(['team1_no_profile_id' => $girl->id]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('correct boys or girls source roster');
        app(TeamTieValidationService::class)->assertTieComplete($tie);
    }

    public function test_missing_source_clears_dependent_bookings_without_rebuilding_phase_pairs(): void
    {
        $draw = $this->service->create($this->event, $this->input());
        $firstTie = $draw->teamTies()->where('round_nr', 1)->firstOrFail();
        $sourceId = $firstTie->home_team_id;
        $prerequisite = $firstTie->rubbers()->firstOrFail();
        $laterTie = $draw->teamTies()->where('round_nr', '>', 1)
            ->where('home_team_id', '!=', $sourceId)->where('away_team_id', '!=', $sourceId)->firstOrFail();
        $later = $laterTie->rubbers()->firstOrFail();
        $later->update(['scheduled_at' => '2026-10-24 10:00:00', 'court_label' => '1',
            'scheduled' => 1, 'duration_min' => 60, 'gap_minutes' => 10]);

        // The missing source pre-clears its matches before the common booking pass.
        Team::findOrFail($sourceId)->update(['category_event_id' => null]);
        $calendar = \Mockery::mock(new \App\Services\Scheduling\UnifiedTeamScheduleService);
        $calendar->shouldReceive('nodes')->once()->andReturn([
            'team:'.$later->id => ['fixture' => $later->fresh(), 'dependencies' => ['team:'.$prerequisite->id]],
        ]);
        $calendar->shouldReceive('manualError')->once()->andReturn('Schedule the preceding team tie first.');
        $this->app->instance(\App\Services\Scheduling\UnifiedTeamScheduleService::class, $calendar);

        $report = app(TeamDrawAdaptationService::class)->adaptDraw($draw);
        $this->assertContains($prerequisite->id, $report['cleared_fixture_ids']);
        $this->assertContains($later->id, $report['cleared_fixture_ids']);
        $this->assertNull($later->fresh()->scheduled_at);
        $this->assertSame(0, (int) $later->fresh()->scheduled);
        $this->assertSame(6, $draw->teamTies()->count());
        $this->assertDatabaseHas('draw_audit_logs', ['draw_id' => $draw->id, 'action' => 'team_schedule_adaptation_cleared']);
    }
}
