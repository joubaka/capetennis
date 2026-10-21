<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{Category, CategoryEvent, Draw, DrawAuditLog, Event, EventType, NoProfileTeamPlayer, Team, TeamFixture, TeamRegion, TeamTie, User};
use App\Services\{TeamCompetitionDecisionService, TeamDrawAdaptationService, TeamDrawScoringPublicationService, TeamEventRulesService, TeamFixtureScoreService, TeamPlacementCompetitionService, TeamPlayoffReadinessService, TeamTieValidationService};
use App\Services\Scheduling\{EventVenueScheduleService, UnifiedTeamScheduleService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamDraftPlacementPlayoffsTest extends TestCase
{
    use RefreshDatabase;

    private function competition(string $age = '15'): array
    {
        DB::table('eventtypes')->insertOrIgnore(['id' => 3, 'name' => 'Team', 'type' => EventType::TEAM]);
        DB::table('draw_types')->insertOrIgnore(['id' => 100, 'drawTypeName' => 'Team - Mixed Doubles', 'btn_color' => 'primary', 'type' => 'team']);
        $event = Event::factory()->create(['eventType' => 3]);
        $admin = User::factory()->create()->assignRole(Role::findOrCreate('admin', 'web'));
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $categories = [];
        foreach (['Boys', 'Girls'] as $gender) $categories[] = CategoryEvent::factory()->create(['event_id' => $event->id,
            'category_id' => Category::factory()->create(['name' => 'U/'.$age.' '.$gender])->id])->id;
        foreach (['North', 'South', 'East', 'West'] as $name) {
            $region = TeamRegion::create(['region_name' => $name, 'short_name' => $name]);
            $event->regions()->attach($region->id);
            foreach ($categories as $category) {
                $team = Team::factory()->create(['category_event_id' => $category, 'region_id' => $region->id, 'name' => $name, 'num_team_members' => 6]);
                foreach (range(1, 6) as $rank) NoProfileTeamPlayer::create(['team_id' => $team->id, 'rank' => $rank, 'name' => 'Fictional', 'surname' => $name.' '.$rank, 'pay_status' => 0]);
            }
        }
        $service = app(TeamPlacementCompetitionService::class);
        $draw = $service->create($event, ['category_ids' => $categories, 'round_robin' => $service->defaults(6, false), 'playoff' => $service->defaults(6, true)]);
        $draw->update(['team_scoring_rules' => app(TeamEventRulesService::class)->defaults(), 'published' => true]);
        $venue = DB::table('venues')->insertGetId(['name' => 'QA venue']);
        $draw->venues()->attach($venue, ['num_courts' => 12]);
        DB::table('event_venues')->insert(['event_id' => $event->id, 'venue_id' => $venue, 'num_courts' => 12]);
        $draw->fixtures()->update(['scheduled_at' => '2026-10-23 08:00:00', 'venue_id' => $venue, 'court_label' => '1', 'duration_min' => 30, 'scheduled' => true]);
        $this->actingAs($admin);
        return [$draw->fresh(), $admin, $venue];
    }

    private function decision(Draw $draw, array $excluded = []): void
    {
        app(TeamCompetitionDecisionService::class)->saveStandings($draw->fresh(), ['order' => $draw->teams_in_draw()->pluck('teams.id')->reverse()->values()->all(),
            'excluded' => $excluded, 'reason' => 'Audited referee confirmation for QA', 'accept_incomplete' => true,
            'token' => app(TeamCompetitionDecisionService::class)->token($draw->fresh()), 'request_id' => (string) Str::uuid()]);
    }

    public static function ages(): array { return [['15'], ['19']]; }

    #[DataProvider('ages')]
    public function test_draft_schedule_is_filled_in_place_for_each_age_group(string $age): void
    {
        [$source, , $venue] = $this->competition($age);
        $service = app(TeamPlacementCompetitionService::class);
        $this->post(route('team-draw.competition.draft-playoffs', $source))->assertRedirect();
        $draft = Draw::findOrFail($source->fresh()->team_draw_selection['competition']['draft_playoff_draw_id']);
        $this->assertTrue($draft->isPlacementDraft());
        $this->assertSame(48, $draft->fixtures()->count());
        $this->assertSame(2, $draft->teamTies()->whereNull('home_team_id')->whereNull('away_team_id')->count());
        $this->assertSame(0, DB::table('team_fixture_players')->whereIn('team_fixture_id', $draft->fixtures()->pluck('id'))->count());
        $this->assertSame($draft->id, $service->prepareDraft($source->fresh())->id);
        $this->assertSame(1, DrawAuditLog::where('action', 'team_playoff_draft_prepared')->count());
        $this->get(route('backend.team-draw.operations', $draft))->assertOk()->assertSee('U/'.$age.' round-robin 1st')->assertSee('U/'.$age.' round-robin 4th');
        $this->get(route('backend.event-venue-schedule.index', ['event' => $source->event_id, 'draw_ids' => [$draft->id]]))->assertOk()
            ->assertSee('competition_phase')->assertSee('Placement playoffs · 1st/2nd and 3rd/4th');
        $fixture = $draft->fixtures()->orderBy('id')->first();
        app(UnifiedTeamScheduleService::class)->assign($source->event, ['fixture_id' => $fixture->id, 'scheduled_at' => '2026-10-24 09:00:00',
            'venue_id' => $venue, 'court' => '2', 'duration' => 45, 'court_gap' => 7]);
        $before = $fixture->fresh()->only(['id', 'scheduled_at', 'venue_id', 'court_label', 'duration_min', 'gap_minutes', 'scheduled']);
        $fixtureIds = $draft->fixtures()->orderBy('id')->pluck('id')->all();
        $tieIds = $draft->teamTies()->orderBy('id')->pluck('id')->all();
        app(TeamDrawAdaptationService::class)->adaptDraw($draft);
        $this->assertEquals($before, $fixture->fresh()->only(array_keys($before)));
        $this->decision($source);
        $playoff = $service->playoffs($source->fresh());
        $this->assertSame($draft->id, $playoff->id);
        $this->assertFalse($playoff->isPlacementDraft());
        $this->assertSame($fixtureIds, $playoff->fixtures()->orderBy('id')->pluck('id')->all());
        $this->assertSame($tieIds, $playoff->teamTies()->orderBy('id')->pluck('id')->all());
        $this->assertEquals($before, $fixture->fresh()->only(array_keys($before)));
        $this->assertGreaterThan(0, $fixture->fresh()->fixturePlayers()->count());
        foreach ($playoff->teamTies as $tie) app(TeamTieValidationService::class)->assertTieComplete($tie);
        $this->assertSame($draft->id, $service->playoffs($source->fresh())->id);
        $this->assertSame(0, (int) $playoff->published);
        $this->assertFalse((bool) $playoff->oop_published);
        $this->assertFalse((bool) $fixture->fresh()->clash_flag);
    }

    public function test_pending_fixtures_cannot_score_validate_publish_or_freeze_source_standings(): void
    {
        [$source] = $this->competition();
        $this->decision($source);
        $draft = app(TeamPlacementCompetitionService::class)->prepareDraft($source->fresh());
        $this->assertTrue(app(TeamCompetitionDecisionService::class)->standingsDecision($source->fresh())['valid']);
        $this->assertTrue(app(TeamPlayoffReadinessService::class)->forDraw($source->fresh())['can_create']);
        try { app(TeamTieValidationService::class)->assertTieComplete($draft->teamTies()->first()); $this->fail('Draft lineups cannot validate.'); } catch (\InvalidArgumentException $e) { $this->assertStringContainsString('qualified teams', $e->getMessage()); }
        try { app(TeamDrawScoringPublicationService::class)->enableLocked($draft); $this->fail('Draft cannot enable scoring.'); } catch (\RuntimeException $e) { $this->assertStringContainsString('qualifiers', $e->getMessage()); }
        try { app(TeamFixtureScoreService::class)->save($draft->fixtures()->first(), ['set1_home' => 6, 'set1_away' => 1]); $this->fail('Draft cannot score.'); } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409, $e->getStatusCode()); }
        try { app(\App\Domain\Draws\Services\DrawPublicationService::class)->publish($draft); $this->fail('Draft cannot publish.'); } catch (\RuntimeException $e) { $this->assertStringContainsString('qualifiers', $e->getMessage()); }
        $this->assertFalse($source->fresh()->team_draw_selection['competition']['playoff_draw_id'] ?? false);
    }

    public function test_preparation_and_qualification_enforce_event_admin_and_original_readiness(): void
    {
        [$source] = $this->competition();
        $outsider = User::factory()->create()->assignRole('admin');
        $this->actingAs($outsider)->postJson(route('team-draw.competition.draft-playoffs', $source))->assertForbidden();
        $draft = app(TeamPlacementCompetitionService::class)->prepareDraft($source);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try { app(TeamPlacementCompetitionService::class)->playoffs($source->fresh()); }
        finally { $this->assertTrue($draft->fresh()->isPlacementDraft()); $this->assertSame(48, $draft->fixtures()->count()); }
    }

    public function test_disqualification_releases_only_unused_placement_booking(): void
    {
        [$source, , $venue] = $this->competition();
        $draft = app(TeamPlacementCompetitionService::class)->prepareDraft($source);
        $unused = $draft->teamTies()->where('tie_nr', 2)->first()->rubbers()->first();
        $unused->update(['scheduled_at' => '2026-10-24 12:00:00', 'venue_id' => $venue, 'court_label' => '1', 'duration_min' => 45, 'scheduled' => true]);
        $this->decision($source, [$source->teams_in_draw()->first()->id]);
        $playoff = app(TeamPlacementCompetitionService::class)->playoffs($source->fresh());
        $this->assertSame(24, $playoff->fixtures()->count());
        $this->assertNull(TeamFixture::find($unused->id));
        $this->assertNotEmpty($playoff->team_draw_selection['competition']['automatic_third_team_id']);
        $this->assertSame(1, DrawAuditLog::where('action', 'unused_placement_bookings_released')->count());
    }

    public function test_placeholder_position_conflicts_and_round_robin_feeders_are_checked(): void
    {
        [$source, , $venue] = $this->competition();
        $draft = app(TeamPlacementCompetitionService::class)->prepareDraft($source);
        $calendar = app(UnifiedTeamScheduleService::class);
        $rubbers = $draft->teamTies()->where('tie_nr', 1)->first()->rubbers()->orderBy('rubber_sequence')->get();
        $first = $rubbers[0];
        $samePosition = $rubbers[6]; // Boys doubles includes boys singles position 1.
        $data = ['venue_id' => $venue, 'court' => '1', 'scheduled_at' => '2026-10-24 09:00:00', 'duration' => 45];
        $calendar->assign($source->event, ['fixture_id' => $first->id] + $data);
        $this->assertNotNull($calendar->manualError($source->event, $samePosition, array_replace($data, ['court' => '2'])));
        $otherTie = $draft->teamTies()->where('tie_nr', 2)->first()->rubbers()->first();
        $this->assertNull($calendar->manualError($source->event, $otherTie, array_replace($data, ['court' => '2'])));
        $sourceFixture = $source->fixtures()->reorder()->orderByDesc('round_nr')->first();
        $this->assertStringContainsString('playoff programme', $calendar->manualError($source->event, $sourceFixture,
            array_replace($data, ['scheduled_at' => '2026-10-24 10:00:00'])));
        $this->assertStringContainsString('playoff programme', $calendar->removalError($source->event, [$sourceFixture->id]));
        $sourceFixture->update(['scheduled_at' => null]);
        $this->assertStringContainsString('preceding team tie', $calendar->manualError($source->event, $samePosition, array_replace($data, ['scheduled_at' => '2026-10-24 11:00:00'])));
    }

    public function test_auto_preview_schedules_all_placeholder_rubbers_and_protects_publication(): void
    {
        [$source, , $venue] = $this->competition();
        $draft = app(TeamPlacementCompetitionService::class)->prepareDraft($source);
        $preview = app(EventVenueScheduleService::class)->preview($source->event, ['draw_ids' => [$draft->id], 'venue_ids' => [$venue],
            'start' => '2026-10-24 08:00:00', 'end' => '2026-10-24 18:00:00', 'duration' => 45, 'wave_minutes' => 45, 'court_gap' => 5, 'player_rest' => 10]);
        $this->assertCount(48, $preview['matches']);
        $fixture = $draft->fixtures()->first();
        $fixture->update(['scheduled_at' => '2026-10-24 09:00:00', 'venue_id' => $venue, 'court_label' => '1', 'duration_min' => 45]);
        try { app(\App\Services\Scheduling\SchedulePublicationService::class)->publish($source->event, ['draw_id' => $draft->id]); $this->fail('Draft must remain private.'); }
        catch (\InvalidArgumentException $e) { $this->assertStringContainsString('proposed playoff', $e->getMessage()); }
        $this->decision($source);
        app(TeamPlacementCompetitionService::class)->playoffs($source->fresh());
        $fixture->forceFill(['clash_flag' => true])->save();
        try { app(\App\Services\Scheduling\SchedulePublicationService::class)->publish($source->event, ['draw_id' => $draft->id]); $this->fail('Conflicted schedule cannot publish.'); }
        catch (\InvalidArgumentException $e) { $this->assertStringContainsString('conflicts', $e->getMessage()); }
        $this->assertDatabaseCount('published_schedule_assignments', 0);
    }

    public function test_draw_scoped_scheduler_rejects_another_draw_fixture_and_returns_validation_errors(): void
    {
        [$source, , $venue] = $this->competition();
        $draft = app(TeamPlacementCompetitionService::class)->prepareDraft($source);
        $data = ['fixture_id' => $draft->fixtures()->first()->id, 'venue_id' => $venue,
            'scheduled_at' => '2026-10-24 09:00:00', 'court_label' => '1', 'duration_min' => 45];
        try { app(\App\Services\TeamScheduleService::class)->save($source, $data); $this->fail('Draw scope must be retained.'); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException) { $this->assertTrue(true); }
        $data['scheduled_at'] = '2026-10-23 07:00:00';
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\TeamScheduleService::class)->save($draft, $data);
    }

    public function test_qualification_retains_bookings_and_flags_a_saved_player_rest_only_conflict(): void
    {
        [$source, , $venue] = $this->competition();
        $draft = app(TeamPlacementCompetitionService::class)->prepareDraft($source);
        $fixture = $draft->fixtures()->first();
        app(UnifiedTeamScheduleService::class)->assign($source->event, ['fixture_id' => $fixture->id, 'scheduled_at' => '2026-10-24 09:00:00',
            'venue_id' => $venue, 'court' => '2', 'duration' => 45]);
        $before = $fixture->fresh()->only(['scheduled_at', 'venue_id', 'court_label', 'duration_min']);
        $homeId = $source->teams_in_draw()->pluck('teams.id')->reverse()->first();
        $player = NoProfileTeamPlayer::where('team_id', $homeId)->where('rank', 1)->firstOrFail();
        $otherDraw = Draw::factory()->create(['event_id' => $source->event_id]);
        $otherFixture = TeamFixture::create(['draw_id' => $otherDraw->id, 'fixture_type' => 1, 'round_nr' => 1, 'match_nr' => 1,
            'scheduled_at' => '2026-10-24 08:00:00', 'venue_id' => $venue, 'court_label' => '3', 'duration_min' => 45, 'scheduled' => true]);
        $otherFixture->fixturePlayers()->create(['slot_no' => 1, 'team1_no_profile_id' => $player->id]);
        DB::table('event_venue_schedule_drafts')->insert(['event_id' => $source->event_id, 'options' => json_encode(['player_rest' => 30])]);
        $this->decision($source);
        $playoff = app(TeamPlacementCompetitionService::class)->playoffs($source->fresh());
        $this->assertEquals($before, $fixture->fresh()->only(array_keys($before)));
        $this->assertTrue((bool) $fixture->fresh()->clash_flag, 'The 15-minute gap is shorter than the saved 30-minute player rest.');
        try { app(\App\Domain\Draws\Services\DrawPublicationService::class)->publish($playoff); $this->fail('Rest conflicts must block draw publication.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('schedule conflicts', $e->getMessage()); }
        try { app(\App\Services\Scheduling\SchedulePublicationService::class)->publish($source->event, ['draw_id' => $playoff->id]); $this->fail('Rest conflicts must block schedule publication.'); }
        catch (\InvalidArgumentException $e) { $this->assertStringContainsString('conflicts', $e->getMessage()); }
        $this->assertDatabaseCount('published_schedule_assignments', 0);
    }
}
