<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{CategoryEvent, Draw, Event, Player, Team, TeamFixture, TeamPlayer, TeamTie};
use App\Services\TeamDrawAdaptationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamDrawAdaptationTest extends TestCase
{
    use RefreshDatabase;

    private function setupDraw(): array
    {
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => false, 'published' => true]);
        $draw->forceFill(['team_category_id' => 1, 'team_draw_selection' => ['category_ids' => [$category->id], 'rubber_code' => 'singles'],
            'team_format_snapshot' => ['name' => 'Singles', 'rubbers' => [['sequence' => 1, 'rubber_code' => 'singles',
                'name' => 'Singles 1', 'player_count_per_team' => 1, 'home_positions' => [1], 'away_positions' => [1]]]]])->save();
        $teams = collect(range(1, 2))->map(function () use ($category) {
            $team = Team::factory()->create(['category_event_id' => $category->id]);
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => Player::factory()->create()->id, 'rank' => 1, 'pay_status' => 0]);
            return $team;
        });
        return [$event, $category, $draw, $teams];
    }

    public function test_roster_change_refreshes_only_upcoming_slots_and_retains_valid_booking_and_identity(): void
    {
        [$event, $category, $draw, $teams] = $this->setupDraw();
        $service = app(TeamDrawAdaptationService::class);
        $service->adaptDraw($draw, null, true, true);
        $fixture = TeamFixture::where('draw_id', $draw->id)->firstOrFail();
        $slotId = $fixture->fixturePlayers->first()->id;
        $venue = DB::table('venues')->insertGetId(['name' => 'Same venue']);
        $draw->venues()->attach($venue, ['num_courts' => 1]);
        $fixture->forceFill(['venue_id' => $venue, 'court_label' => '1', 'scheduled_at' => '2026-10-10 08:00', 'duration_min' => 60, 'scheduled' => 1])->save();
        $new = Player::factory()->create();
        $teams->first()->team_players()->first()->update(['player_id' => $new->id]);
        $report = $service->adaptDraw($draw, null, true, true);
        $this->assertSame(1, $report['updated_lineups']);
        $this->assertSame($slotId, $fixture->fixturePlayers()->first()->id);
        $this->assertEquals($new->id, $fixture->fixturePlayers()->first()->team1_id);
        $this->assertSame('08:00', $fixture->fresh()->scheduled_at->format('H:i'));
        $fixture->forceFill(['match_status' => 1])->save();
        $snapshot = $fixture->fixturePlayers()->first()->toArray();
        $teams->first()->team_players()->first()->update(['player_id' => Player::factory()->create()->id]);
        $service->adaptDraw($draw, null, true, true);
        $this->assertSame($snapshot, $fixture->fixturePlayers()->first()->toArray());
    }

    public function test_category_added_team_appends_pairs_without_recreating_old_tie_and_repeat_is_idempotent(): void
    {
        [$event, $category, $draw] = $this->setupDraw();
        $service = app(TeamDrawAdaptationService::class);
        $service->adaptDraw($draw, null, true, true);
        $old = $draw->teamTies()->firstOrFail();
        Team::factory()->create(['category_event_id' => $category->id]);
        $report = $service->adaptDraw($draw, null, true, true);
        $this->assertCount(2, $report['added_fixture_ids']);
        $this->assertCount(2, $report['review_tie_ids']);
        $this->assertNotContains($old->id, $report['review_tie_ids']);
        $this->assertSame(2, TeamTie::whereIn('id', $report['review_tie_ids'])->where('status', TeamTie::STATUS_DRAFT)->count());
        $this->assertTrue(collect($report['warnings'])->contains(fn ($warning) => str_contains($warning, 'review and publication')));
        $this->assertDatabaseHas('team_ties', ['id' => $old->id, 'round_nr' => $old->round_nr]);
        $this->assertSame(3, $draw->teamTies()->count());
        $this->assertSame([], $service->adaptDraw($draw)['added_fixture_ids']);
        $foreign = CategoryEvent::factory()->create();
        Team::factory()->create(['category_event_id' => $foreign->id]);
        $service->adaptEvent($event);
        $this->assertSame(3, $draw->teamTies()->count());
    }

    public function test_obsolete_team_removes_only_unplayed_rubbers_and_retains_history(): void
    {
        [$event, $category, $draw, $teams] = $this->setupDraw();
        $service = app(TeamDrawAdaptationService::class);
        $service->adaptDraw($draw, null, true, true);
        $tie = $draw->teamTies()->firstOrFail();
        $played = $tie->rubbers()->firstOrFail();
        $played->forceFill(['match_status' => 1])->save();
        $pending = TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id, 'round_nr' => 1,
            'tie_nr' => 1, 'rubber_sequence' => 2, 'match_nr' => 2, 'fixture_type' => 1, 'match_status' => 0]);
        $teams->last()->update(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id]);
        $report = $service->adaptDraw($draw, null, true, true);
        $this->assertContains($pending->id, $report['removed_fixture_ids']);
        $this->assertContains($played->id, $report['retained_protected_ids']);
        $this->assertDatabaseMissing('team_fixtures', ['id' => $pending->id]);
        $this->assertDatabaseHas('team_fixtures', ['id' => $played->id]);
    }

    public function test_new_shared_player_conflict_returns_affected_unplayed_booking_to_planning(): void
    {
        [$event, $category, $draw, $teams] = $this->setupDraw();
        $service = app(TeamDrawAdaptationService::class);
        $service->adaptDraw($draw, null, true, true);
        $fixture = TeamFixture::where('draw_id', $draw->id)->firstOrFail();
        $venue = DB::table('venues')->insertGetId(['name' => 'Shared courts']);
        $draw->venues()->attach($venue, ['num_courts' => 2]);
        $fixture->forceFill(['venue_id' => $venue, 'court_label' => '1', 'scheduled_at' => '2026-10-10 08:00', 'duration_min' => 60, 'scheduled' => 1])->save();
        $new = Player::factory()->create();
        $other = Draw::factory()->create();
        $booked = TeamFixture::create(['draw_id' => $other->id, 'round_nr' => 1, 'match_nr' => 1, 'fixture_type' => 1,
            'venue_id' => $venue, 'court_label' => '2', 'scheduled_at' => '2026-10-10 08:00', 'duration_min' => 60, 'match_status' => 0]);
        $booked->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => $new->id]);
        $teams->first()->team_players()->first()->update(['player_id' => $new->id]);
        $report = $service->adaptDraw($draw, null, true, true);
        $this->assertSame([$fixture->id], $report['cleared_fixture_ids']);
        $this->assertNull($fixture->fresh()->scheduled_at);
        $this->assertNotNull($booked->fresh()->scheduled_at);
        $this->assertNotEmpty($report['warnings']);
    }

    public function test_noop_does_not_clear_existing_bookings_or_bootstrap_empty_draws(): void
    {
        [$event, $category, $draw] = $this->setupDraw();
        $service = app(TeamDrawAdaptationService::class);
        $service->adaptEvent($event);
        $this->assertDatabaseCount('team_ties', 0);
        $service->adaptDraw($draw, null, true, true);
        $fixture = TeamFixture::where('draw_id', $draw->id)->firstOrFail();
        // A legacy booking without venue allocation is deliberately left alone during a no-op.
        $fixture->forceFill(['scheduled_at' => '2026-10-10 08:00', 'court_label' => '1', 'scheduled' => 1])->save();
        $report = $service->adaptEvent($event)[$draw->id];
        $this->assertSame([], $report['cleared_fixture_ids']);
        $this->assertNotNull($fixture->fresh()->scheduled_at);
    }

    public function test_authorized_ordinary_roster_update_adapts_lineup_in_the_same_request(): void
    {
        [$event, $category, $draw, $teams] = $this->setupDraw();
        app(TeamDrawAdaptationService::class)->adaptDraw($draw, null, true, true);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(\App\Models\User::factory()->create()->assignRole('super-user'));
        $member = $teams->first()->team_players()->first();
        $player = Player::factory()->create();
        $this->postJson(route('team.insert.player'), ['pivot' => $member->id, 'player' => $player->id])
            ->assertOk()->assertJsonPath('adaptation.'.$draw->id.'.updated_lineups', 1);
        $fixture = TeamFixture::where('draw_id', $draw->id)->firstOrFail();
        $this->assertEquals($player->id, $fixture->fixturePlayers()->first()->team1_id);
        $this->actingAs(\App\Models\User::factory()->create());
        $this->postJson(route('team.insert.player'), ['pivot' => $member->id, 'player' => Player::factory()->create()->id])->assertForbidden();
        $this->assertEquals($player->id, $member->fresh()->player_id);
    }

    public function test_cleared_prerequisite_clears_only_its_unplayed_dependents(): void
    {
        [$event, $category, $draw, $teams] = $this->setupDraw();
        $third = Team::factory()->create(['category_event_id' => $category->id]);
        TeamPlayer::create(['team_id' => $third->id, 'player_id' => Player::factory()->create()->id, 'rank' => 1, 'pay_status' => 0]);
        $service = app(TeamDrawAdaptationService::class);
        $service->adaptDraw($draw, null, true, true);
        $fixtures = TeamFixture::where('draw_id', $draw->id)->orderBy('round_nr')->get();
        $venue = DB::table('venues')->insertGetId(['name' => 'Courts']);
        $draw->venues()->attach($venue, ['num_courts' => 2]);
        foreach ($fixtures as $index => $fixture) {
            $fixture->forceFill(['venue_id' => $venue, 'court_label' => '1', 'scheduled_at' => '2026-10-10 '.sprintf('%02d', 8 + $index * 2).':00', 'duration_min' => 60, 'scheduled' => 1])->save();
        }
        $incoming = Player::factory()->create();
        $foreign = TeamFixture::create(['draw_id' => Draw::factory()->create()->id, 'round_nr' => 1, 'match_nr' => 1,
            'fixture_type' => 1, 'venue_id' => $venue, 'court_label' => '2', 'scheduled_at' => '2026-10-10 08:00', 'duration_min' => 60]);
        $foreign->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => $incoming->id]);
        $teams->first()->team_players()->first()->update(['player_id' => $incoming->id]);
        $report = $service->adaptDraw($draw);
        $this->assertCount(3, $report['cleared_fixture_ids']);
        $this->assertSame(0, TeamFixture::where('draw_id', $draw->id)->whereNotNull('scheduled_at')->count());
        $this->assertNotNull($foreign->fresh()->scheduled_at);
    }

    public function test_derived_doubles_rows_grow_and_shrink_without_recreating_booked_or_played_rubbers(): void
    {
        [$event, $category, $draw, $teams] = $this->setupDraw();
        $draw->forceFill(['team_draw_selection' => ['category_ids' => [$category->id], 'rubber_code' => 'doubles'],
            'team_format_snapshot' => ['name' => 'Roster positions · Doubles', 'rubbers' => [['sequence' => 1,
                'rubber_code' => 'doubles', 'name' => 'Doubles 1', 'player_count_per_team' => 2,
                'home_positions' => [1, 2], 'away_positions' => [1, 2], 'is_required' => true]]]])->save();
        foreach ($teams as $team) TeamPlayer::create(['team_id' => $team->id, 'player_id' => Player::factory()->create()->id, 'rank' => 2, 'pay_status' => 0]);
        $service = app(TeamDrawAdaptationService::class);
        $service->adaptDraw($draw, null, true, true);
        $tie = $draw->teamTies()->firstOrFail();
        $tie->forceFill(['status' => TeamTie::STATUS_PUBLISHED, 'published_at' => now()])->save();
        $fixture = $tie->rubbers()->firstOrFail();
        $fixture->forceFill(['scheduled_at' => '2026-10-10 08:00', 'court_label' => '1', 'scheduled' => 1])->save();
        $added = TeamPlayer::create(['team_id' => $teams->first()->id, 'player_id' => Player::factory()->create()->id, 'rank' => 3, 'pay_status' => 0]);
        $report = $service->adaptDraw($draw);
        $this->assertCount(1, $report['added_fixture_ids']);
        $this->assertSame(2, $tie->rubbers()->count());
        $this->assertSame('08:00', $fixture->fresh()->scheduled_at->format('H:i'));
        $this->assertSame(TeamTie::STATUS_DRAFT, $tie->fresh()->status);
        $this->assertNotEmpty($report['warnings']);
        $added->delete();
        $report = $service->adaptDraw($draw);
        $this->assertCount(1, $report['removed_fixture_ids']);
        $this->assertSame(1, $tie->rubbers()->count());
        $this->assertSame($fixture->id, $tie->rubbers()->first()->id);
        $fixture->forceFill(['match_status' => 1])->save();
        TeamPlayer::create(['team_id' => $teams->first()->id, 'player_id' => Player::factory()->create()->id, 'rank' => 3, 'pay_status' => 0]);
        $report = $service->adaptDraw($draw);
        $this->assertSame([], $report['added_fixture_ids']);
        $this->assertTrue($report['review_required']);
        $this->assertSame(1, $tie->rubbers()->count());
    }

    public function test_completed_tie_format_and_standings_survive_derived_roster_growth(): void
    {
        [$event, $category, $draw, $teams] = $this->setupDraw();
        $snapshot = $draw->team_format_snapshot;
        $snapshot['name'] = 'Roster positions · Singles';
        $draw->forceFill(['team_format_snapshot' => $snapshot])->save();
        $service = app(TeamDrawAdaptationService::class);
        $service->adaptDraw($draw, null, true, true);
        $tie = $draw->teamTies()->firstOrFail();
        $fixture = $tie->rubbers()->firstOrFail();
        app(\App\Services\TeamFixtureScoreService::class)->save($fixture, ['set1_home' => 6, 'set1_away' => 2, 'set2_home' => 6, 'set2_away' => 3,
            'participant_revision' => app(\App\Services\TeamParticipantHistoryService::class)->revision($fixture)]);
        $before = app(\App\Services\TeamStandingsService::class)->forDraw($draw->fresh());
        $resultCount = $fixture->teamResults()->count();
        $this->assertTrue(app(\App\Services\TeamStandingsService::class)->tieOutcome($tie->fresh())['complete']);
        TeamPlayer::create(['team_id' => $teams->first()->id, 'player_id' => Player::factory()->create()->id, 'rank' => 2, 'pay_status' => 0]);
        $report = $service->adaptDraw($draw);
        $this->assertTrue($report['review_required']);
        $this->assertCount(1, $tie->fresh()->format_snapshot['rubbers']);
        $this->assertCount(2, $draw->fresh()->team_format_snapshot['rubbers']);
        $this->assertTrue(app(\App\Services\TeamTieValidationService::class)->requiredRubbersPresent($tie->fresh()));
        $this->assertSame($before, app(\App\Services\TeamStandingsService::class)->forDraw($draw->fresh()));
        $this->assertSame($resultCount, $fixture->teamResults()->count());
        $migration = require database_path('migrations/2026_10_05_030000_add_team_tie_format_snapshot.php');
        try {
            $migration->down();
            $this->fail('Recorded historical format snapshots were allowed to be dropped.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('must be preserved', $exception->getMessage());
        }
        $this->assertCount(1, $tie->fresh()->format_snapshot['rubbers']);
    }

    public function test_new_earlier_rubber_returns_later_dependencies_to_planning_but_keeps_first_booking(): void
    {
        [$event, $category, $draw, $teams] = $this->setupDraw();
        $snapshot = $draw->team_format_snapshot;
        $snapshot['name'] = 'Roster positions · Singles';
        $draw->forceFill(['team_format_snapshot' => $snapshot])->save();
        $third = Team::factory()->create(['category_event_id' => $category->id]);
        TeamPlayer::create(['team_id' => $third->id, 'player_id' => Player::factory()->create()->id, 'rank' => 1, 'pay_status' => 0]);
        $service = app(TeamDrawAdaptationService::class);
        $service->adaptDraw($draw, null, true, true);
        $fixtures = TeamFixture::where('draw_id', $draw->id)->orderBy('round_nr')->get();
        $venue = DB::table('venues')->insertGetId(['name' => 'Courts']);
        $draw->venues()->attach($venue, ['num_courts' => 1]);
        foreach ($fixtures as $index => $fixture) {
            $fixture->forceFill(['venue_id' => $venue, 'court_label' => '1', 'scheduled_at' => '2026-10-10 '.sprintf('%02d', 8 + $index * 2).':00', 'duration_min' => 60, 'scheduled' => 1])->save();
        }
        TeamPlayer::create(['team_id' => $teams->first()->id, 'player_id' => Player::factory()->create()->id, 'rank' => 2, 'pay_status' => 0]);
        $report = $service->adaptDraw($draw);
        $this->assertCount(3, $report['added_fixture_ids']);
        $this->assertCount(2, $report['cleared_fixture_ids']);
        $this->assertSame('08:00', $fixtures->first()->fresh()->scheduled_at->format('H:i'));
        $this->assertNull($fixtures->get(1)->fresh()->scheduled_at);
        $this->assertNull($fixtures->get(2)->fresh()->scheduled_at);
    }

    public function test_new_upcoming_tie_can_be_explicitly_validated_and_published_in_an_unlocked_published_draw(): void
    {
        [$event, $category, $draw] = $this->setupDraw();
        app(TeamDrawAdaptationService::class)->adaptDraw($draw, null, true, true);
        $tie = $draw->teamTies()->firstOrFail();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(\App\Models\User::factory()->create()->assignRole('super-user'));
        $this->postJson(route('team-draw.ties.validate', $tie))->assertOk();
        $this->postJson(route('team-draw.ties.publish', $tie))->assertOk();
        $this->assertSame(TeamTie::STATUS_PUBLISHED, $tie->fresh()->status);
        $this->assertTrue((bool) $draw->fresh()->published);
        $this->postJson(route('team-draw.ties.validate', $tie))->assertStatus(409);
        $tie->refresh()->forceFill(['status' => TeamTie::STATUS_DRAFT, 'published_at' => null])->save();
        $tie->rubbers()->first()->forceFill(['match_status' => 1])->save();
        $this->postJson(route('team-draw.ties.validate', $tie))->assertStatus(409);
        $this->assertSame(TeamTie::STATUS_DRAFT, $tie->fresh()->status);
    }

    public function test_known_source_v2_draw_without_snapshot_freezes_attached_format_and_refreshes_roster(): void
    {
        [$event, $category, $draw, $teams] = $this->setupDraw();
        $service = app(TeamDrawAdaptationService::class);
        $service->adaptDraw($draw, null, true, true);
        $tie = $draw->teamTies()->firstOrFail();
        $format = \App\Models\TeamEventFormat::create(['event_id' => $event->id, 'name' => 'Legacy singles', 'min_roster_size' => 1, 'max_roster_size' => 12]);
        $format->rubbers()->create(['sequence' => 1, 'rubber_code' => 'singles', 'name' => 'Singles 1',
            'player_count_per_team' => 1, 'home_positions' => [1], 'away_positions' => [1], 'is_required' => true]);
        $draw->forceFill(['team_format_snapshot' => null, 'team_event_format_id' => $format->id])->save();
        $tie->forceFill(['format_snapshot' => null])->save();
        $incoming = Player::factory()->create();
        $teams->first()->team_players()->first()->update(['player_id' => $incoming->id]);
        $report = $service->adaptDraw($draw);
        $this->assertSame(1, $report['updated_lineups']);
        $this->assertEquals($incoming->id, $tie->rubbers()->first()->fixturePlayers()->first()->team1_id);
        $this->assertSame('Legacy singles', $draw->fresh()->team_format_snapshot['name']);
    }

    public function test_established_uncategorized_members_are_retained_without_discovering_global_teams(): void
    {
        [$event, $category, $draw, $teams] = $this->setupDraw();
        $service = app(TeamDrawAdaptationService::class);
        $service->adaptDraw($draw, null, true, true);
        $tieId = $draw->teamTies()->firstOrFail()->id;
        $draw->forceFill(['team_draw_selection' => null])->save();
        foreach ($teams as $team) $team->update(['category_event_id' => null]);
        Team::factory()->create(['category_event_id' => null]);
        $incoming = Player::factory()->create();
        $teams->first()->team_players()->first()->update(['player_id' => $incoming->id]);
        $report = $service->adaptEvent($event)[$draw->id];
        $this->assertSame([], $report['removed_fixture_ids']);
        $this->assertSame(1, $report['updated_lineups']);
        $this->assertSame(1, $draw->teamTies()->count());
        $this->assertSame($tieId, $draw->teamTies()->first()->id);
        $this->assertSame(2, $draw->teams_in_draw()->count());
    }
}
