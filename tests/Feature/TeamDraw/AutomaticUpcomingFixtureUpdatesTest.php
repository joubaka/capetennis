<?php

namespace Tests\Feature\TeamDraw;

use App\Domain\Teams\Services\ExternalTeamRosterService;
use App\Models\{CategoryEvent, Draw, Event, EventRegion, EventType, NoProfileTeamPlayer, Player, Team, TeamFixture, TeamPlayer, TeamRegion, User};
use App\Services\{TeamDrawAdaptationService, TeamParticipantHistoryService};
use App\Services\TeamSelection\{ImportedTeamRosterService, RosterOrderProtectionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutomaticUpcomingFixtureUpdatesTest extends TestCase
{
    use RefreshDatabase;

    private function competition(bool $imported = true): array
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $this->actingAs($actor);
        $type = new EventType();
        $type->forceFill(['name' => 'Inter-district teams', 'type' => EventType::TEAM])->save();
        $event = Event::factory()->create(['eventType' => $type->id, 'start_date' => now()->addWeek(), 'deadline' => 0, 'signUp' => true]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => false, 'published' => true]);
        $draw->forceFill(['team_category_id' => 1, 'team_draw_selection' => ['category_ids' => [$category->id], 'rubber_code' => 'singles'],
            'team_format_snapshot' => ['name' => 'Singles', 'rubbers' => [['sequence' => 1, 'rubber_code' => 'singles',
                'name' => 'Singles 1', 'player_count_per_team' => 1, 'home_positions' => [1], 'away_positions' => [1]]]]])->save();
        $teams = collect(range(1, 2))->map(function ($number) use ($category, $event, $imported) {
            $region = new TeamRegion();
            $region->forceFill(['region_name' => 'District '.$number])->save();
            $event->regions()->attach($region->id);
            $team = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $region->id, 'noProfile' => $imported, 'num_team_members' => 2]);
            foreach ([1, 2] as $rank) {
                if ($imported) NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Original'.$rank, 'surname' => 'District'.$number, 'rank' => $rank, 'pay_status' => 0]);
                TeamPlayer::create(['team_id' => $team->id, 'player_id' => $imported ? 0 : Player::factory()->create()->id, 'rank' => $rank, 'pay_status' => 0]);
            }
            return $team;
        });
        app(TeamDrawAdaptationService::class)->adaptDraw($draw, null, true, true);
        $fixture = TeamFixture::where('draw_id', $draw->id)->firstOrFail();
        $region = EventRegion::where('event_id', $event->id)->where('region_id', $teams->first()->region_id)->firstOrFail();
        return [$event, $draw, $teams->first(), $fixture, $actor, $region];
    }

    public function test_roster_fragment_keeps_imported_slot_identity_for_linked_players_after_generation(): void
    {
        [$event, , $team, $fixture] = $this->competition();
        $profile = Player::factory()->create(['name' => 'Linked', 'surname' => 'Roster Player']);
        $imported = $team->team_players_no_profile()->where('rank', 1)->firstOrFail();
        $imported->forceFill(['player_profile' => $profile->id])->save();
        $team->team_players()->where('rank', 1)->update(['player_id' => $profile->id]);
        $before = $fixture->fresh()->getAttributes();
        $rosterBefore = $imported->fresh()->getAttributes();
        $slotsBefore = $fixture->fixturePlayers()->get()->map->getAttributes()->all();

        $this->get(route('admin.events.teams', [$event, 'roster_region' => $team->region_id, 'panel' => 'players']),
            ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('Linked Roster Player')
            ->assertSee('Roster changes update upcoming fixtures automatically.')
            ->assertSee('Move up: Linked Roster Player', false)
            ->assertSee('name="slot_id" value="'.$imported->id.'"', false)
            ->assertSee('name="expected_player_id" value="'.$profile->id.'"', false)
            ->assertSee('Original2 District1');

        $this->assertSame($before, $fixture->fresh()->getAttributes());
        $this->assertSame($rosterBefore, $imported->fresh()->getAttributes());
        $this->assertSame($slotsBefore, $fixture->fixturePlayers()->get()->map->getAttributes()->all());
        $this->assertDatabaseCount('team_fixtures', 1);
    }

    public function test_open_registration_restores_roster_options_and_adapts_unpaid_replace_remove_fill_and_reserve(): void
    {
        [$event, $draw, $team, $fixture] = $this->competition();
        $first = $team->team_players_no_profile()->where('rank', 1)->firstOrFail();
        $this->get(route('admin.events.teams', [$event, 'roster_region' => $team->region_id, 'panel' => 'players']))
            ->assertOk()->assertSee('Replace this player')->assertSee('Correct the player’s name')->assertSee('Remove this unpaid player')
            ->assertSee('Move up: Original1 District1', false)->assertSee('Add player')->assertSee('Add reserve player');
        $this->postJson(route('backend.team.changePlayer'), ['event_id' => $event->id, 'team_id' => $team->id,
            'slot_id' => $first->id, 'expected_rank' => 1, 'expected_player_id' => 0,
            'name' => 'Replacement', 'surname' => 'Upcoming'])->assertOk();
        $replacement = $team->team_players_no_profile()->where('rank', 1)->firstOrFail();
        $this->assertEquals($replacement->id, $fixture->fixturePlayers()->firstOrFail()->team1_no_profile_id);
        $this->postJson(route('backend.team.removePlayer'), ['event_id' => $event->id, 'team_id' => $team->id,
            'slot_id' => $replacement->id, 'expected_rank' => 1, 'expected_player_id' => 0])->assertOk();
        $this->assertNull($fixture->fixturePlayers()->firstOrFail()->team1_no_profile_id);
        $this->postJson(route('backend.team.addPlayers'), ['event_id' => $event->id, 'team_id' => $team->id,
            'target_rank' => 1, 'name' => 'Filled', 'surname' => 'Upcoming'])->assertOk();
        $filled = $team->team_players_no_profile()->where('rank', 1)->firstOrFail();
        $this->assertEquals($filled->id, $fixture->fixturePlayers()->firstOrFail()->team1_no_profile_id);
        $this->postJson(route('backend.team.addPlayers'), ['event_id' => $event->id, 'team_id' => $team->id,
            'reserve' => 1, 'name' => 'Reserve', 'surname' => 'Upcoming'])->assertOk();
        $this->assertDatabaseHas('no_profile_team_players', ['team_id' => $team->id, 'rank' => 3, 'name' => 'Reserve']);
        $this->assertEquals($filled->id, $fixture->fixturePlayers()->firstOrFail()->team1_no_profile_id);
        $this->assertDatabaseCount('team_fixtures', 1);
        $this->assertFalse((bool) $draw->fresh()->oop_published);
        $this->assertDatabaseCount('team_payment_orders', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_open_roster_replacement_preserves_started_profile_match_and_updates_upcoming_match(): void
    {
        foreach ([1, 2] as $status) {
        [$event, $draw, $team, $fixture] = $this->competition(false);
        $opponent = Team::factory()->create(['category_event_id' => $team->category_event_id, 'noProfile' => false]);
        foreach ([1, 2] as $rank) TeamPlayer::create(['team_id' => $opponent->id, 'player_id' => Player::factory()->create()->id, 'rank' => $rank, 'pay_status' => 0]);
        app(TeamDrawAdaptationService::class)->adaptEvent($event);
        $upcoming = TeamFixture::where('draw_id', $draw->id)->where('id', '!=', $fixture->id)
            ->whereHas('teamTie', fn ($query) => $query->where('home_team_id', $team->id))->firstOrFail();
        $fixture->update(['match_status' => $status]);
        if ($status === 2) $fixture->fixtureResults()->create(['set_nr' => 1, 'team1_score' => 6, 'team2_score' => 2]);
        $before = $fixture->fresh()->getAttributes();
        $participants = $fixture->fixturePlayers()->get()->map->getAttributes()->all();
        $results = $fixture->fixtureResults()->get()->map->getAttributes()->all();
        $slot = $team->team_players()->where('rank', 1)->firstOrFail();
        $oldPlayer = $slot->player_id;
        $newPlayer = Player::factory()->create();
        $this->postJson(route('backend.team.changePlayer'), ['event_id' => $event->id, 'team_id' => $team->id,
            'slot_id' => $slot->id, 'expected_rank' => 1, 'expected_player_id' => $oldPlayer, 'player_id' => $newPlayer->id])->assertOk();
        $this->assertSame($before, $fixture->fresh()->getAttributes());
        $this->assertSame($participants, $fixture->fixturePlayers()->get()->map->getAttributes()->all());
        $this->assertSame($results, $fixture->fixtureResults()->get()->map->getAttributes()->all());
        $this->assertEquals($oldPlayer, $fixture->fixturePlayers()->firstOrFail()->team1_id);
        $this->assertEquals($newPlayer->id, $upcoming->fixturePlayers()->firstOrFail()->team1_id);
        $this->assertTrue($fixture->fixturePlayers()->firstOrFail()->player1()->whereKey($oldPlayer)->exists());
        }
    }

    public function test_imported_started_identity_cannot_be_deleted_and_closed_or_locked_draws_keep_protection(): void
    {
        [$event, $draw, $team, $fixture] = $this->competition();
        $slot = $team->team_players_no_profile()->where('rank', 1)->firstOrFail();
        $payload = ['event_id' => $event->id, 'team_id' => $team->id, 'slot_id' => $slot->id, 'expected_rank' => 1, 'expected_player_id' => 0];
        $fixture->update(['match_status' => 1]);
        $participants = $fixture->fixturePlayers()->get()->map->getAttributes()->all();
        $this->postJson(route('backend.team.changePlayer'), $payload + ['name' => 'Rejected', 'surname' => 'Replacement'])
            ->assertUnprocessable()->assertSee('competition replacement workflow');
        $this->assertSame($participants, $fixture->fixturePlayers()->get()->map->getAttributes()->all());
        $this->assertDatabaseHas('no_profile_team_players', ['id' => $slot->id, 'name' => $slot->name]);
        $this->assertDatabaseMissing('no_profile_team_players', ['name' => 'Rejected']);
        $fixture->update(['match_status' => 0]);
        $draw->update(['locked' => true]);
        $this->postJson(route('backend.team.removePlayer'), $payload)->assertUnprocessable();
        $this->assertDatabaseHas('no_profile_team_players', ['id' => $slot->id]);
        $draw->update(['locked' => false]);
        $event->update(['signUp' => false]);
        $this->postJson(route('backend.team.removePlayer'), $payload)->assertUnprocessable();
        $this->get(route('admin.events.teams', [$event, 'roster_region' => $team->region_id, 'panel' => 'players']))
            ->assertOk()->assertDontSee('Replace this player')->assertDontSee('Add reserve player');
    }

    public function test_open_roster_change_preserves_other_ties_and_their_bookings_in_the_same_draw(): void
    {
        [$event, $draw, $team] = $this->competition();
        foreach (['North', 'South'] as $name) {
            $extra = Team::factory()->create(['category_event_id' => $team->category_event_id, 'noProfile' => true, 'num_team_members' => 2]);
            foreach ([1, 2] as $rank) NoProfileTeamPlayer::create(['team_id' => $extra->id, 'rank' => $rank, 'name' => $name, 'surname' => 'Player'.$rank, 'pay_status' => 0]);
        }
        app(TeamDrawAdaptationService::class)->adaptEvent($event);
        $unrelated = $draw->fixtures()->whereHas('teamTie', fn ($query) => $query->where('home_team_id', '!=', $team->id)->where('away_team_id', '!=', $team->id))->firstOrFail();
        $venue = \App\Models\Venue::forceCreate(['name' => 'Retained court']);
        $unrelated->update(['scheduled_at' => $event->start_date->format('Y-m-d').' 09:00:00', 'venue_id' => $venue->id, 'court_label' => '1', 'duration_min' => 60, 'scheduled' => 1]);
        $before = $unrelated->fresh()->getAttributes();
        $lineups = $unrelated->fixturePlayers()->get()->map->getAttributes()->all();
        $slot = $team->team_players_no_profile()->where('rank', 1)->firstOrFail();
        $this->postJson(route('backend.team.changePlayer'), ['event_id' => $event->id, 'team_id' => $team->id,
            'slot_id' => $slot->id, 'expected_rank' => 1, 'expected_player_id' => 0, 'name' => 'Updated', 'surname' => 'Source'])->assertOk();
        $this->assertSame($before, $unrelated->fresh()->getAttributes());
        $this->assertSame($lineups, $unrelated->fixturePlayers()->get()->map->getAttributes()->all());
    }

    public function test_open_roster_growth_and_removal_adapt_derived_rubbers(): void
    {
        [$event, $draw, $team] = $this->competition();
        $team->update(['num_team_members' => 4]);
        $snapshot = $draw->team_format_snapshot; $snapshot['name'] = 'Roster positions · Singles';
        $draw->update(['team_format_snapshot' => $snapshot]);
        app(TeamDrawAdaptationService::class)->adaptEvent($event);
        $this->assertSame(2, $draw->fixtures()->count());
        $this->postJson(route('backend.team.addPlayers'), ['event_id' => $event->id, 'team_id' => $team->id, 'name' => 'Additional', 'surname' => 'Player'])->assertOk();
        $this->assertSame(3, $draw->fixtures()->count());
        $slot = $team->team_players_no_profile()->where('rank', 3)->firstOrFail();
        $this->postJson(route('backend.team.removePlayer'), ['event_id' => $event->id, 'team_id' => $team->id, 'slot_id' => $slot->id,
            'expected_rank' => 3, 'expected_player_id' => 0])->assertOk();
        $this->assertSame(2, $draw->fixtures()->count());
    }

    public function test_started_profile_without_verified_snapshot_rejects_replacement_without_losing_history(): void
    {
        [$event, , $team, $fixture] = $this->competition(false);
        $fixture->update(['match_status' => 1]);
        $row = $fixture->fixturePlayers()->firstOrFail();
        $row->forceFill(['participant_snapshot' => null])->save();
        $slot = $team->team_players()->where('rank', 1)->firstOrFail();
        $before = $slot->getAttributes();
        $lineup = $row->fresh()->getAttributes();
        $this->postJson(route('backend.team.changePlayer'), ['event_id' => $event->id, 'team_id' => $team->id, 'slot_id' => $slot->id,
            'expected_rank' => 1, 'expected_player_id' => $slot->player_id, 'player_id' => Player::factory()->create()->id])
            ->assertUnprocessable()->assertSee('no verified participant history');
        $this->assertSame($before, $slot->fresh()->getAttributes());
        $this->assertSame($lineup, $row->fresh()->getAttributes());
    }

    public function test_imported_rank_save_updates_upcoming_fixture_without_preview_and_stays_idempotent(): void
    {
        [$event, $draw, $team, $fixture, , $region] = $this->competition();
        $slots = $team->team_players_no_profile()->orderBy('rank')->get();
        $lineupId = $fixture->fixturePlayers()->firstOrFail()->id;
        $fixtureBefore = $fixture->getAttributes();
        $url = route('backend.team-selection.imported-players.reorder', [$event, $region, $team]);
        $data = ['slot_ids' => $slots->reverse()->pluck('id')->values()->all(), 'expected_ids' => $slots->pluck('id')->all()];
        $this->putJson($url, $data)->assertOk()->assertJsonMissingPath('preview');
        $row = $fixture->fixturePlayers()->firstOrFail();
        $this->assertSame($lineupId, $row->id);
        $this->assertEquals($slots->last()->id, $row->team1_no_profile_id);
        $this->assertSame(1, $row->participant_snapshot[1]['rank']);
        $this->assertSame($fixtureBefore, $fixture->fresh()->getAttributes());
        $this->assertTrue($event->fresh()->registrationIsOpen());
        $auditCount = DB::table('draw_audit_logs')->count();
        $this->putJson($url, ['slot_ids' => $data['slot_ids'], 'expected_ids' => $data['slot_ids']])->assertOk();
        $this->assertDatabaseCount('team_fixtures', 1);
        $this->assertDatabaseCount('draw_audit_logs', $auditCount);
        $this->putJson($url, $data)->assertUnprocessable();
        $this->assertEquals($slots->last()->id, $fixture->fixturePlayers()->firstOrFail()->team1_no_profile_id);
    }

    public function test_ordinary_move_and_name_correction_work_after_draw_generation(): void
    {
        [$event, , $team, $fixture] = $this->competition();
        $first = $team->team_players_no_profile()->orderBy('rank')->firstOrFail();
        $this->postJson(route('backend.team.editPlayerName'), ['event_id' => $event->id, 'team_id' => $team->id,
            'slot_id' => $first->id, 'expected_rank' => 1, 'expected_player_id' => 0, 'expected_name' => $first->name,
            'expected_surname' => $first->surname, 'name' => 'Corrected', 'surname' => 'Name'])->assertOk();
        $this->assertSame('Corrected Name', $fixture->fixturePlayers()->firstOrFail()->participant_snapshot[1]['name']);
        $this->postJson(route('team.order.player.list'), ['event_id' => $event->id, 'team_id' => $team->id,
            'direction' => 'down', 'slot_id' => $first->id, 'expected_rank' => 1, 'expected_player_id' => 0])->assertOk();
        $this->assertNotEquals($first->id, $fixture->fixturePlayers()->firstOrFail()->team1_no_profile_id);
        $this->assertTrue($event->fresh()->registrationIsOpen());
    }

    public function test_name_snapshot_uses_latest_correction_then_freezes_at_start_and_rejects_stale_scoring(): void
    {
        [$event, , $team, $fixture, $actor] = $this->competition();
        $slot = $team->team_players_no_profile()->firstOrFail();
        $history = app(TeamParticipantHistoryService::class);
        $revision = $history->revision($fixture);
        app(ImportedTeamRosterService::class)->rename($event, $slot, 'Corrected', 'Before start', $actor);
        $snapshot = $fixture->fixturePlayers()->firstOrFail()->participant_snapshot;
        $this->assertSame('Corrected Before start', $snapshot[1]['name']);
        $display = $fixture->fresh();
        app(\App\Services\TeamFixtureLineupPresenter::class)->prepare(collect([$display]), true);
        $this->assertSame('Corrected Before start', $display->lineup_display['home']['players'][0]['name']);
        $this->assertNotSame($revision, $history->revision($fixture->fresh()));
        try { $history->assertRevision($fixture->fresh(), $event->id, $revision); $this->fail('Outdated scoring revision accepted.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $expected) { $this->assertSame(409, $expected->getStatusCode()); }
        $fixture->update(['match_status' => 1]);
        app(ImportedTeamRosterService::class)->rename($event, $slot->fresh(), 'Changed', 'After start', $actor);
        $this->assertSame($snapshot, $fixture->fixturePlayers()->firstOrFail()->participant_snapshot);
        app(ImportedTeamRosterService::class)->move($event, $slot->fresh(), 'down', $actor);
        $this->assertSame($snapshot, $fixture->fixturePlayers()->firstOrFail()->participant_snapshot);
    }

    public function test_profile_name_edits_refresh_only_upcoming_snapshots_and_preserve_source_attestations(): void
    {
        [$event, $draw, $team, $fixture] = $this->competition(false);
        $player = $team->team_players()->orderBy('rank')->firstOrFail()->player;
        $before = $fixture->fixturePlayers()->firstOrFail()->participant_snapshot;
        $player->update(['name' => 'Profile', 'surname' => 'Corrected']);
        $after = $fixture->fixturePlayers()->firstOrFail()->participant_snapshot;
        $this->assertSame('Profile Corrected', $after[1]['name']);
        $before[1]['name'] = 'Profile Corrected';
        $this->assertSame($before, $after);
        $auditCount = DB::table('draw_audit_logs')->count();
        app(TeamParticipantHistoryService::class)->refreshEventNames($event->id);
        $this->assertDatabaseCount('draw_audit_logs', $auditCount);
        $draw->update(['locked' => true]);
        $player->update(['surname' => 'Locked']);
        $this->assertSame($after, $fixture->fixturePlayers()->firstOrFail()->participant_snapshot);
        $draw->update(['locked' => false]);
        $fixture->teamTie->update(['status' => 'completed']);
        $player->update(['surname' => 'Completed']);
        $this->assertSame($after, $fixture->fixturePlayers()->firstOrFail()->participant_snapshot);
    }

    public function test_result_rows_protect_names_even_when_match_status_is_still_unplayed(): void
    {
        [$event, , $team, $fixture, $actor] = $this->competition();
        $before = $fixture->fixturePlayers()->firstOrFail()->participant_snapshot;
        $fixture->fixtureResults()->create(['set_nr' => 1, 'team1_score' => 6, 'team2_score' => 2]);
        $results = $fixture->fixtureResults()->get()->map->getAttributes()->all();
        app(ImportedTeamRosterService::class)->rename($event, $team->team_players_no_profile()->firstOrFail(), 'Later', 'Correction', $actor);
        $this->assertSame($before, $fixture->fixturePlayers()->firstOrFail()->participant_snapshot);
        $this->assertSame($results, $fixture->fixtureResults()->get()->map->getAttributes()->all());
    }

    public function test_registration_remains_available_after_draws_and_updates_existing_upcoming_slots(): void
    {
        [$event, $draw, $team, $fixture, $actor] = $this->competition();
        $slot = $team->team_players_no_profile()->firstOrFail();
        $player = Player::factory()->create(['name' => $slot->name, 'surname' => $slot->surname]);
        $this->mock(\App\Services\PlayerEligibilityService::class)->shouldReceive('assertEligible')->andReturnNull();
        $lineupId = $fixture->fixturePlayers()->firstOrFail()->id;
        app(ExternalTeamRosterService::class)->claim($actor, $event, $team, $slot, $player);
        $registrationSlot = app(ExternalTeamRosterService::class)->assertSelectedPlayerCanRegister($event, $team, $player);
        $this->assertEquals($player->id, $registrationSlot->player_id);
        $this->assertTrue($event->fresh()->registrationIsOpen());
        $this->assertTrue((bool) $event->fresh()->signUp);
        $this->assertSame($lineupId, $fixture->fixturePlayers()->firstOrFail()->id);
        $this->assertEquals($player->id, $fixture->fixturePlayers()->firstOrFail()->team1_id);
        $this->assertNull($fixture->fixturePlayers()->firstOrFail()->team1_no_profile_id);
        $this->assertDatabaseCount('team_fixtures', 1);
        $this->assertDatabaseCount('team_payment_orders', 0);
    }

    public function test_cross_event_name_and_order_requests_never_change_fixtures(): void
    {
        [$event, , $team, $fixture, $actor, $region] = $this->competition();
        $slots = $team->team_players_no_profile()->orderBy('rank')->get();
        $before = $fixture->fixturePlayers()->firstOrFail()->getAttributes();
        $foreign = Event::factory()->create();
        $this->putJson(route('backend.team-selection.imported-players.reorder', [$foreign, $region, $team]),
            ['slot_ids' => $slots->reverse()->pluck('id')->values()->all(), 'expected_ids' => $slots->pluck('id')->all()])->assertNotFound();
        try { app(ImportedTeamRosterService::class)->rename($foreign, $slots->first(), 'Foreign', 'Edit', $actor); $this->fail('Foreign event accepted.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $expected) { $this->assertSame(404, $expected->getStatusCode()); }
        $this->actingAs(User::factory()->create())->putJson(route('backend.team-selection.imported-players.reorder', [$event, $region, $team]),
            ['slot_ids' => $slots->reverse()->pluck('id')->values()->all(), 'expected_ids' => $slots->pluck('id')->all()])->assertForbidden();
        $this->assertSame($before, $fixture->fixturePlayers()->firstOrFail()->getAttributes());
    }

    public function test_closed_registration_still_requires_order_confirmation_and_locked_draw_rejects_automatic_move(): void
    {
        [$event, $draw, $team, $fixture, , $region] = $this->competition();
        $slots = $team->team_players_no_profile()->orderBy('rank')->get();
        $url = route('backend.team-selection.imported-players.reorder', [$event, $region, $team]);
        $data = ['slot_ids' => $slots->reverse()->pluck('id')->values()->all(), 'expected_ids' => $slots->pluck('id')->all()];
        $event->update(['signUp' => false]);
        $this->putJson($url, $data)->assertUnprocessable();
        $event->update(['signUp' => true]);
        $draw->update(['locked' => true]);
        $this->putJson($url, $data)->assertUnprocessable();
        $this->assertEquals($slots->first()->id, $fixture->fixturePlayers()->firstOrFail()->team1_no_profile_id);
        $this->assertSame(1, (int) $slots->first()->fresh()->rank);
    }

    public function test_automatic_move_rejects_player_conflicts_and_rolls_back_rank_lineup_and_booking(): void
    {
        [$event, $draw, $team, $fixture] = $this->competition(false);
        $players = $team->team_players()->orderBy('rank')->get();
        $venue = DB::table('venues')->insertGetId(['name' => 'Shared courts']);
        $draw->venues()->attach($venue, ['num_courts' => 2]);
        $fixture->forceFill(['scheduled_at' => $event->start_date->format('Y-m-d').' 08:00', 'venue_id' => $venue,
            'court_label' => '1', 'duration_min' => 60, 'scheduled' => 1])->save();
        $otherDraw = Draw::factory()->create();
        $other = TeamFixture::create(['draw_id' => $otherDraw->id, 'match_nr' => 1, 'fixture_type' => 1, 'match_status' => 0,
            'scheduled_at' => $fixture->scheduled_at, 'venue_id' => $venue, 'court_label' => '2', 'duration_min' => 60]);
        $other->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => $players->last()->player_id]);
        $before = $fixture->fresh()->getAttributes();
        $lineup = $fixture->fixturePlayers()->firstOrFail()->getAttributes();
        $this->postJson(route('team.order.player.list'), ['event_id' => $event->id, 'team_id' => $team->id,
            'direction' => 'down', 'slot_id' => $players->first()->id, 'expected_rank' => 1,
            'expected_player_id' => $players->first()->player_id])->assertUnprocessable();
        $this->assertSame(1, (int) $players->first()->fresh()->rank);
        $this->assertSame($before, $fixture->fresh()->getAttributes());
        $this->assertSame($lineup, $fixture->fixturePlayers()->firstOrFail()->getAttributes());
    }
}
