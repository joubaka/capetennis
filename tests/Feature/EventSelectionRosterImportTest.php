<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, Event, EventAdmin, EventRegion, EventSelectionFinalization, EventSelectionReopening, EventSelectionRosterImport, EventType, NoProfileTeamPlayer, Player, Team, TeamPlayer, TeamRegion, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail, Queue};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventSelectionRosterImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_team_and_reserves_import_in_frozen_order_privately_unpaid_and_retry_does_not_duplicate(): void
    {
        [$source, $target, $final, $region, $actor, $players] = $this->scenario();
        Queue::fake(); Mail::fake();
        $url = route('backend.selection-import.index', ['event' => $target, 'source_event_id' => $source->id]);
        $response = $this->get($url)->assertOk()->assertSee('Reserve')->assertSee($players[1]->name)->assertDontSee($players[2]->name)->assertDontSee('Private selection note');
        if (getenv('CT_SELECTION_IMPORT_BROWSER_FIXTURE')) file_put_contents(storage_path('app/testing/selection-import-preview.html'), $response->getContent());
        $input = ['finalization_id' => $final->id, 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        $this->post(route('backend.selection-import.store', $target), $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('backend.selection-import.store', $target), $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('event_selection_roster_imports', 1);
        $this->assertDatabaseCount('team_payment_orders', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('team_selection_invitations', 0);
        $team = Team::whereHas('category', fn ($q) => $q->where('event_id', $target->id))->firstOrFail();
        $this->assertEquals($region->id, $team->region_id);
        $this->assertFalse((bool) $team->published);
        $this->assertEquals(1, $team->num_team_members); // Reserve is outside the main-team capacity.
        $this->assertSame([$players[1]->id, $players[0]->id], TeamPlayer::where('team_id', $team->id)->pluck('player_id')->all());
        $this->assertSame([0, 0], TeamPlayer::where('team_id', $team->id)->pluck('pay_status')->all());
        $this->assertDatabaseCount('no_profile_team_players', 2);
        $this->assertSame(['main', 'reserve'], array_column(EventSelectionRosterImport::first()->groups['10-boys']['players'], 'status'));
        $this->assertSame($final->groups, $final->fresh()->groups);
        $this->assertFalse($target->fresh()->results_published);
        Queue::assertNothingPushed(); Mail::assertNothingSent();
    }

    public function test_import_requires_manager_access_to_both_events_and_rejects_reopened_source(): void
    {
        [$source, $target, $final, $region, $actor] = $this->scenario();
        $input = ['finalization_id' => $final->id, 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        $url = route('backend.selection-import.store', $target);
        $outsider = User::factory()->create()->assignRole('admin');
        $this->actingAs($outsider)->postJson($url, $input)->assertForbidden();
        $outsider->adminEvents()->attach($target);
        $this->postJson($url, $input)->assertForbidden();
        $this->get(route('backend.selection-import.index', $target))->assertOk()->assertDontSee($source->name);
        $this->actingAs($actor);
        EventSelectionReopening::create(['finalization_id' => $final->id, 'reopened_by' => $actor->id, 'reason' => 'Review', 'reopened_at' => now()]);
        $this->postJson($url, $input)->assertConflict();
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
    }

    public function test_wrong_region_cross_event_team_occupied_team_and_missing_confirmation_make_no_changes(): void
    {
        [$source, $target, $final, $region, $actor, $players, $sourceTeam] = $this->scenario();
        $url = route('backend.selection-import.store', $target);
        $input = ['finalization_id' => $final->id, 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        $this->postJson($url, array_replace($input, ['confirm_import' => 0]))->assertUnprocessable();
        $foreignRegion = TeamRegion::create(['region_name' => 'Other']);
        $this->postJson($url, array_replace($input, ['region_id' => $foreignRegion->id]))->assertUnprocessable();
        $this->postJson($url, array_replace($input, ['mapping' => ['10-boys' => $sourceTeam->id]]))->assertUnprocessable();
        $category = CategoryEvent::create(['event_id' => $target->id, 'category_id' => $sourceTeam->category->category_id, 'entry_fee' => 0]);
        $occupied = new Team(['name' => 'Occupied', 'region_id' => $region->id, 'category_event_id' => $category->id, 'num_team_members' => 2, 'published' => false, 'year' => 2026]);
        $occupied->forceFill(['user_id' => $actor->id, 'personal_team' => false])->save();
        TeamPlayer::create(['team_id' => $occupied->id, 'player_id' => $players[0]->id, 'rank' => 1, 'pay_status' => 1]);
        $this->postJson($url, array_replace($input, ['mapping' => ['10-boys' => $occupied->id]]))->assertUnprocessable();
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
        $this->assertDatabaseCount('no_profile_team_players', 0);
        $this->assertDatabaseHas('team_players', ['team_id' => $occupied->id, 'player_id' => $players[0]->id, 'pay_status' => 1]);
    }

    public function test_imported_identity_is_resolved_from_actual_participant_not_historical_anchor_and_relink_is_rejected(): void
    {
        [$source, $target, $final, $region, $actor, $players, $sourceTeam] = $this->scenario();
        $person = NoProfileTeamPlayer::create(['team_id' => $sourceTeam->id, 'name' => 'External', 'surname' => 'Selected', 'rank' => 1, 'pay_status' => 1]);
        $groups = $final->groups;
        $key = 'imported:'.$sourceTeam->id.':99999';
        $groups['10-boys']['snapshot'] = [['id' => $key, 'name' => 'External Selected', 'source_team_ids' => [$sourceTeam->id], 'source_participants' => [['team_id' => $sourceTeam->id, 'type' => 'imported', 'id' => $person->id, 'profile_id' => null]]]];
        $groups['10-boys']['selected_keys'] = [$key];
        $groups['10-boys']['selection_statuses'] = [$key => ['position' => 1, 'status' => 'main']];
        DB::table('event_selection_finalizations')->where('id', $final->id)->update(['groups' => json_encode($groups)]);
        $input = ['finalization_id' => $final->id, 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        $person->update(['player_profile' => $players[0]->id]);
        $this->postJson(route('backend.selection-import.store', $target), $input)->assertUnprocessable();
        $person->update(['player_profile' => null]);
        $this->post(route('backend.selection-import.store', $target), $input)->assertRedirect()->assertSessionHasNoErrors();
        $copy = NoProfileTeamPlayer::where('team_id', '!=', $sourceTeam->id)->firstOrFail();
        $this->assertSame('External', $copy->name); $this->assertEquals(0, $copy->pay_status); $this->assertNull($copy->player_profile); $this->assertNull($copy->claimed_by_user_id);
    }

    public function test_linked_imported_snapshot_without_ratings_preserves_attested_profile_and_legacy_copy_stays_unlinked(): void
    {
        [$source, $target, $final, $region, $actor, $players, $sourceTeam] = $this->scenario();
        $person = NoProfileTeamPlayer::create(['team_id' => $sourceTeam->id, 'name' => 'Linked', 'surname' => 'External', 'player_profile' => $players[0]->id, 'rank' => 1, 'pay_status' => 1]);
        $key = 'imported:'.$sourceTeam->id.':'.$person->id;
        $groups = ['10-boys' => ['snapshot' => [['id' => $key, 'name' => 'Linked External', 'source_team_ids' => [$sourceTeam->id], 'source_participants' => [['team_id' => $sourceTeam->id, 'type' => 'imported', 'id' => $person->id, 'profile_id' => $players[0]->id]]]],
            'selected_keys' => [$key], 'selection_statuses' => [$key => ['position' => 1, 'status' => 'main']]]];
        DB::table('event_selection_finalizations')->where('id', $final->id)->update(['groups' => json_encode($groups)]);
        $preview = app(\App\Services\EventSelectionRosterImportService::class)->preview($target, $final->fresh(), $actor);
        $this->assertEquals($players[0]->id, $preview['groups']['10-boys']['players'][0]['profile_id']);
        unset($groups['10-boys']['snapshot'][0]['source_participants'][0]['profile_id']);
        DB::table('event_selection_finalizations')->where('id', $final->id)->update(['groups' => json_encode($groups)]);
        $preview = app(\App\Services\EventSelectionRosterImportService::class)->preview($target, $final->fresh(), $actor);
        $this->assertNull($preview['groups']['10-boys']['players'][0]['profile_id']);
    }

    public function test_canonical_result_draft_finalize_and_import_preserves_linked_imported_identity_without_ratings(): void
    {
        [$source, $target, $oldFinal, $region, $actor, $players, $sourceTeam] = $this->scenario();
        DB::table('event_selection_finalizations')->where('id', $oldFinal->id)->delete();
        $source->regions()->attach($region);
        $source->team_result_settings = ['team_size' => 1, 'reserve_count' => 0]; $source->save();
        $sourceTeam->update(['noProfile' => true]);
        $member = NoProfileTeamPlayer::create(['team_id' => $sourceTeam->id, 'name' => 'Canonical', 'surname' => 'Imported', 'rank' => 1, 'player_profile' => $players[0]->id, 'pay_status' => 1]);
        $away = new Team(['name' => 'Away u/10 Boys', 'category_event_id' => $sourceTeam->category_event_id, 'region_id' => $region->id, 'published' => true, 'num_team_members' => 1, 'year' => 2026]);
        $away->forceFill(['user_id' => $actor->id, 'personal_team' => false])->save();
        TeamPlayer::create(['team_id' => $away->id, 'player_id' => $players[1]->id, 'rank' => 1, 'pay_status' => 1]);
        $draw = \App\Models\Draw::factory()->create(['event_id' => $source->id, 'category_event_id' => $sourceTeam->category_event_id]);
        $fixture = \App\Models\TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'numSets' => 3, 'round_nr' => 1, 'match_nr' => 1]);
        $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_no_profile_id' => $member->id, 'team2_id' => $players[1]->id]);
        foreach ([1, 2] as $set) $fixture->teamResults()->create(['set_nr' => $set, 'team1_score' => 6, 'team2_score' => 1]);
        $ranking = app(\App\Services\TeamResultRankingService::class)->ranking($source, '10-boys', [$region->id], ['singles']);
        $key = 'imported:'.$sourceTeam->id.':'.$member->id;
        $row = $ranking->firstWhere('id', $key);
        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('rating_player_id', $row);
        $this->assertSame($players[0]->id, $row['source_participants'][0]['profile_id']);
        app(\App\Services\TeamResultSelectionDraftService::class)->save($source, ['group_key' => '10-boys', 'version' => 0, 'region_ids' => [$region->id], 'formats' => ['singles'], 'selected_keys' => [$key], 'reasons' => []], $actor->id);
        $final = app(\App\Services\EventSelectionFinalizationService::class)->finalize($source->fresh(), $actor, ['10-boys' => 1], 1, 0);
        $this->post(route('backend.selection-import.store', $target), ['finalization_id' => $final->id, 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $team = Team::whereHas('category', fn ($q) => $q->where('event_id', $target->id))->firstOrFail();
        $this->assertDatabaseHas('team_players', ['team_id' => $team->id, 'player_id' => $players[0]->id, 'rank' => 1, 'pay_status' => 0]);
        $this->assertDatabaseHas('no_profile_team_players', ['team_id' => $team->id, 'player_profile' => $players[0]->id, 'name' => 'Canonical', 'pay_status' => 0]);
    }

    public function test_duplicate_destination_and_target_eligibility_reject_atomically(): void
    {
        [$source, $target, $final, $region, $actor, $players, $sourceTeam] = $this->scenario();
        $url = route('backend.selection-import.store', $target);
        $input = ['finalization_id' => $final->id, 'region_id' => $region->id, 'mapping' => ['10-boys' => 0], 'confirm_import' => 1];
        $this->mock(\App\Services\PlayerEligibilityService::class, function ($mock) {
            $mock->shouldReceive('assertEligible')->once()->andThrow(new \RuntimeException('Active event restriction'));
        });
        $this->postJson($url, $input)->assertUnprocessable()->assertJsonValidationErrors('mapping');
        $this->assertSame(0, Team::whereHas('category', fn ($q) => $q->where('event_id', $target->id))->count());
        $this->assertDatabaseCount('no_profile_team_players', 0);
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
        $this->app->forgetInstance(\App\Services\PlayerEligibilityService::class);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $target->id]);
        $otherTeam = new Team(['name' => 'Other category team', 'category_event_id' => $otherCategory->id, 'region_id' => $region->id, 'published' => false, 'num_team_members' => 1, 'year' => 2026]);
        $otherTeam->forceFill(['user_id' => $actor->id, 'personal_team' => false])->save();
        TeamPlayer::create(['team_id' => $otherTeam->id, 'player_id' => $players[0]->id, 'rank' => 1, 'pay_status' => 0]);
        $this->postJson($url, $input)->assertUnprocessable();
        $this->assertDatabaseCount('event_selection_roster_imports', 0);
        $this->assertDatabaseCount('no_profile_team_players', 0);
        $this->assertSame(1, Team::whereHas('category', fn ($q) => $q->where('event_id', $target->id))->count());
    }

    private function scenario(): array
    {
        Role::findOrCreate('admin', 'web');
        $actor = User::factory()->create()->assignRole('admin');
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Selection import test', 'code' => 'selection-import-test', 'type' => EventType::TEAM]);
        $source = Event::factory()->create(['name' => 'Platteland selection source', 'eventType' => $type, 'results_published' => false]);
        $target = Event::factory()->create(['name' => 'Interdistricts destination', 'eventType' => $type, 'results_published' => false]);
        $actor->adminEvents()->attach([$source->id, $target->id]); $this->actingAs($actor);
        $region = TeamRegion::create(['region_name' => 'Platteland', 'short_name' => 'PL']);
        (new EventRegion)->forceFill(['event_id' => $target->id, 'region_id' => $region->id, 'ordering' => 1])->save();
        $category = Category::factory()->create(['name' => 'u/10 Boys']);
        $categoryEvent = CategoryEvent::factory()->create(['event_id' => $source->id, 'category_id' => $category->id]);
        $team = new Team(['name' => 'Source u/10 Boys', 'category_event_id' => $categoryEvent->id, 'region_id' => $region->id, 'num_team_members' => 3, 'published' => true, 'year' => 2026]);
        $team->forceFill(['user_id' => $actor->id, 'personal_team' => false])->save();
        $players = Player::factory()->count(3)->create();
        $snapshot = $players->map(fn ($player, $index) => ['id' => $player->id, 'name' => $player->name.' '.$player->surname, 'source_team_ids' => [$team->id], 'source_participants' => [['team_id' => $team->id, 'type' => 'profile', 'id' => $player->id]], 'draft_position' => $index + 1])->all();
        $final = EventSelectionFinalization::create(['event_id' => $source->id, 'finalized_by' => $actor->id, 'draft_versions' => ['10-boys' => 1], 'fingerprint' => str_repeat('a', 64), 'team_size' => 1, 'reserve_count' => 1, 'finalized_at' => now(),
            'groups' => ['10-boys' => ['snapshot' => $snapshot, 'selected_keys' => [(string)$players[1]->id, (string)$players[0]->id], 'selection_statuses' => [(string)$players[1]->id => ['position' => 1, 'status' => 'main'], (string)$players[0]->id => ['position' => 2, 'status' => 'reserve']], 'reasons' => ['Private selection note']]]]);
        return [$source, $target, $final, $region, $actor, $players, $team];
    }
}
