<?php

namespace Tests\Feature\TeamSelection;

use App\Models\{CategoryEvent, Event, EventAdmin, EventRegion, EventRegionManager, EventType, NoProfileTeamPlayer, Player, Team, TeamPlayer, TeamPaymentOrder, TeamRegion, User};
use App\Services\TeamSelection\RegionManagerAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail, Queue};
use Tests\TestCase;

class RegionalRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_regional_player_list_only_has_three_jobs_and_event_admin_keeps_existing_workspace(): void
    {
        [$event, $region, $team, $manager] = $this->roster();
        $this->actingAs($manager)->get(route('admin.events.teams', $event))->assertOk()
            ->assertViewIs('backend.adminPage.show')->assertSee('Add player')
            ->assertDontSee('Publish all teams')->assertDontSee('Clothing setup')->assertDontSee('Event email log');
        EventAdmin::create(['event_id' => $event->id, 'user_id' => $manager->id]);
        $this->get(route('admin.events.teams', $event))->assertOk()->assertViewIs('backend.adminPage.show');
    }

    public function test_profile_roster_add_move_remove_is_scoped_audited_and_duplicate_safe(): void
    {
        Queue::fake(); Mail::fake();
        [$event, $region, $team, $manager] = $this->roster();
        $first = Player::factory()->create(['name' => 'Jean', 'surname' => 'Joubert']);
        $second = Player::factory()->create();
        $this->actingAs($manager)->getJson(route('backend.team.availablePlayers', ['event_id' => $event->id, 'team_id' => $team->id, 'q' => 'Jean Joubert']))->assertOk()->assertJsonPath('players.0.id', $first->id);
        foreach ([$first, $second] as $player) $this->rosterChange($team, ['action' => 'add', 'player_id' => $player->id])->assertOk();
        $this->rosterChange($team, ['action' => 'add', 'player_id' => $first->id])->assertUnprocessable();
        $slots = TeamPlayer::where('team_id', $team->id)->get();
        $this->assertCount(2, $slots);
        $slot = $slots->firstWhere('player_id', $second->id);
        $this->rosterChange($team, ['action' => 'move', 'direction' => 'up', 'slot_id' => $slot->id, 'expected_player_id' => $slot->player_id, 'expected_rank' => 2])->assertOk();
        $this->assertSame(1, (int) $slot->fresh()->rank);
        $this->assertSame(2, (int) $slots->firstWhere('player_id', $first->id)->fresh()->rank);
        $this->rosterChange($team, ['action' => 'remove', 'slot_id' => $slot->id, 'expected_player_id' => $slot->player_id, 'expected_rank' => 2])->assertUnprocessable();
        $this->rosterChange($team, ['action' => 'remove', 'slot_id' => $slot->id, 'expected_player_id' => $slot->player_id, 'expected_rank' => 1])->assertOk();
        $this->rosterChange($team, ['action' => 'remove', 'slot_id' => $slot->id, 'expected_player_id' => $slot->player_id, 'expected_rank' => 1])->assertNotFound();
        $this->assertSame(0, (int) $slot->fresh()->player_id);
        $replacement = Player::factory()->create();
        $this->rosterChange($team, ['action' => 'add', 'player_id' => $replacement->id])->assertOk();
        $this->rosterChange($team, ['action' => 'remove', 'slot_id' => $slot->id, 'expected_player_id' => $second->id, 'expected_rank' => 1])->assertUnprocessable();
        $this->rosterChange($team, ['action' => 'move', 'direction' => 'down', 'slot_id' => $slot->id, 'expected_player_id' => $second->id, 'expected_rank' => 1])->assertUnprocessable();
        $this->assertSame($replacement->id, (int) $slot->fresh()->player_id);
        $this->assertSame(0, TeamPaymentOrder::count());
        $this->assertTrue((bool) $team->fresh()->published);
        $this->assertSame(5, DB::table('activity_log')->where('description', 'updated team roster')->count());
        Queue::assertNothingPushed(); Mail::assertNothingSent();
    }

    public function test_no_profile_roster_supports_add_move_remove_without_profile_or_payment(): void
    {
        Queue::fake();
        [$event, $region, $team, $manager] = $this->roster(true);
        $this->actingAs($manager);
        foreach (['First', 'Second'] as $name) $this->rosterChange($team, ['action' => 'add', 'name' => $name, 'surname' => 'Player'])->assertOk();
        $this->rosterChange($team, ['action' => 'add', 'name' => 'First', 'surname' => 'Player'])->assertUnprocessable();
        $second = NoProfileTeamPlayer::where('team_id', $team->id)->where('rank', 2)->firstOrFail();
        $this->rosterChange($team, ['action' => 'move', 'direction' => 'up', 'slot_id' => $second->id, 'expected_player_id' => 0, 'expected_rank' => 2])->assertOk();
        $this->assertSame(1, (int) $second->fresh()->rank);
        $this->rosterChange($team, ['action' => 'remove', 'slot_id' => $second->id, 'expected_player_id' => 0, 'expected_rank' => 1])->assertOk();
        $this->rosterChange($team, ['action' => 'remove', 'slot_id' => $second->id, 'expected_player_id' => 0, 'expected_rank' => 1])->assertNotFound();
        $this->assertSame(1, NoProfileTeamPlayer::where('team_id', $team->id)->count());
        $this->assertSame(0, TeamPlayer::count());
        Queue::assertNothingPushed();
    }

    public function test_other_region_event_and_paid_roster_are_protected_and_extras_denied(): void
    {
        [$event, $region, $team, $manager] = $this->roster();
        [, $otherRegion, $otherTeam] = $this->roster();
        $this->actingAs($manager)->postJson(route('backend.team.addPlayers'), ['team_id' => $otherTeam->id, 'event_id' => $event->id, 'player_ids' => [Player::factory()->create()->id]])->assertNotFound();
        $this->postJson(route('backend.team.addPlayers'), ['team_id' => $team->id, 'event_id' => $otherRegion->event_id, 'player_ids' => [Player::factory()->create()->id]])->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('admin.events.teams', $event))->assertForbidden();
        $slot = app(\App\Domain\Payments\Services\TeamPaymentService::class)->createUnpaidTeamPlayerSlot($team, Player::factory()->create(), 1);
        app(\App\Domain\Payments\Services\TeamPaymentService::class)->updateTeamPlayerSlot($slot, ['pay_status' => 1]);
        $this->actingAs($manager)->rosterChange($team, ['action' => 'remove', 'slot_id' => $slot->id, 'expected_player_id' => $slot->player_id, 'expected_rank' => 1])->assertUnprocessable();
        $this->assertSame(1, (int) $slot->fresh()->pay_status);
        $this->assertFalse(app(RegionManagerAccessService::class)->canManage($manager, $region));
        $this->assertTrue(app(RegionManagerAccessService::class)->canManageRoster($manager, $region));
        $this->put(route('backend.team-selection.manager.assign', [$event, $region]), ['manager_user_id' => $manager->id])->assertForbidden();
        $this->post(route('backend.team-selection.teams.publish-all', [$event, $region]))->assertForbidden();
        $this->get(route('backend.event-communications.index', $event))->assertForbidden();
    }

    public function test_linked_imported_slots_keep_mirrors_and_block_inflight_then_release_unpaid_reservation(): void
    {
        Queue::fake();
        [$event, $region, $team, $manager] = $this->roster(true);
        $payments = app(\App\Domain\Payments\Services\TeamPaymentService::class);
        $first = Player::factory()->create(); $second = Player::factory()->create();
        $firstSlot = NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => $first->name, 'surname' => $first->surname, 'rank' => 1, 'player_profile' => $first->id, 'pay_status' => 0]);
        $secondSlot = NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => $second->name, 'surname' => $second->surname, 'rank' => 2, 'player_profile' => $second->id, 'pay_status' => 0]);
        $mirror = $payments->createUnpaidTeamPlayerSlot($team, $first, 1);
        $payments->createUnpaidTeamPlayerSlot($team, $second, 2);
        $this->actingAs($manager)->rosterChange($team, ['action' => 'move', 'direction' => 'down', 'slot_id' => $firstSlot->id, 'expected_player_id' => $first->id, 'expected_rank' => 1])->assertOk();
        $this->assertSame(2, (int) $firstSlot->fresh()->rank); $this->assertSame(2, (int) $mirror->fresh()->rank);
        $order = TeamPaymentOrder::create(['team_id' => $team->id, 'event_id' => $event->id, 'player_id' => $first->id, 'user_id' => $manager->id, 'total_amount' => 100, 'wallet_reserved' => 40, 'payfast_amount_due' => 60, 'pay_status' => false, 'payfast_handed_off_at' => now()]);
        $payload = ['action' => 'remove', 'slot_id' => $firstSlot->id, 'expected_player_id' => $first->id, 'expected_rank' => 2];
        $this->rosterChange($team, $payload)->assertUnprocessable();
        $this->assertSame(40.0, (float) $order->fresh()->wallet_reserved);
        $this->assertNotNull($firstSlot->fresh());
        $order->update(['payfast_handed_off_at' => null]);
        $this->rosterChange($team, $payload)->assertOk();
        $this->assertNull($firstSlot->fresh());
        $this->assertSame(0, (int) $mirror->fresh()->player_id);
        $this->assertSame(0.0, (float) $order->fresh()->wallet_reserved);
        $this->assertNotNull($order->fresh()->withdrawn_at);
        $this->assertSame(100.0, (float) $order->fresh()->total_amount);
        $this->assertSame(1, TeamPaymentOrder::count());
        Queue::assertNothingPushed();
    }

    public function test_canonical_admin_bulk_add_supports_unregioned_teams_append_and_atomic_rollback(): void
    {
        [$event, $region, $team, $admin] = $this->roster();
        EventAdmin::create(['event_id' => $event->id, 'user_id' => $admin->id]);
        $team->update(['region_id' => null, 'num_team_members' => 1]);
        $first = Player::factory()->create(); $second = Player::factory()->create();
        $this->actingAs($admin)->postJson(route('backend.team.addPlayers'), ['team_id' => $team->id, 'player_ids' => [$first->id, $second->id]])->assertOk();
        $this->assertSame(2, TeamPlayer::where('team_id', $team->id)->count());
        $third = Player::factory()->create();
        $this->postJson(route('backend.team.addPlayers'), ['team_id' => $team->id, 'player_ids' => [$third->id, $first->id]])->assertUnprocessable();
        $this->assertSame(2, TeamPlayer::where('team_id', $team->id)->count());
        $this->assertDatabaseMissing('team_players', ['team_id' => $team->id, 'player_id' => $third->id]);
        $slots = TeamPlayer::where('team_id', $team->id)->get();
        $this->postJson(route('team.order.player.list'), ['team_id' => $team->id, 'order' => [
            ['team_player_id' => $slots->last()->id, 'position' => 1], ['team_player_id' => $slots->first()->id, 'position' => 2],
        ]])->assertOk();
        $this->assertSame(1, (int) $slots->last()->fresh()->rank);
        $this->assertSame(2, (int) $slots->first()->fresh()->rank);
        $replacement = Player::factory()->create();
        $this->postJson(route('backend.team.changePlayer'), ['team_id' => $team->id, 'slot_id' => $slots->last()->id, 'expected_rank' => 1, 'expected_player_id' => $second->id, 'player_id' => $replacement->id])->assertOk();
        $this->assertSame($replacement->id, (int) $slots->last()->fresh()->player_id);
        $this->assertSame(1, (int) $slots->last()->fresh()->rank);
        $this->assertSame(2, TeamPlayer::where('team_id', $team->id)->count());
        $this->assertSame(0, NoProfileTeamPlayer::count());
    }

    public function test_linked_raw_player_can_be_changed_to_one_typed_name_at_the_same_rank(): void
    {
        Queue::fake(); Mail::fake();
        [$event, $region, $team, $manager] = $this->roster(true);
        $player = Player::factory()->create(['name' => 'Linked', 'surname' => 'Actual']);
        $old = NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Imported', 'surname' => 'Original', 'rank' => 2, 'player_profile' => $player->id, 'pay_status' => 0]);
        $sibling = NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Sibling', 'surname' => 'Player', 'rank' => 3, 'pay_status' => 0]);
        $mirror = app(\App\Domain\Payments\Services\TeamPaymentService::class)->createUnpaidTeamPlayerSlot($team, $player, 2);
        $this->actingAs($manager)->get(route('admin.events.teams', $event))->assertOk()->assertSee('Linked Actual')->assertDontSee('Imported Original')->assertSee('Change player');
        $this->assertSame($player->id, (int) $old->fresh()->player_profile);
        $payload = ['team_id' => $team->id, 'event_id' => $event->id, 'slot_id' => $old->id, 'expected_rank' => 2, 'expected_player_id' => $player->id, 'name' => 'New', 'surname' => 'Player'];
        $this->postJson(route('backend.team.changePlayer'), $payload)->assertOk();
        $new = NoProfileTeamPlayer::where('team_id', $team->id)->where('rank', 2)->firstOrFail();
        $this->assertNotSame($old->id, $new->id);
        $this->assertNull($new->player_profile);
        $this->assertSame('New Player', $new->name.' '.$new->surname);
        $this->assertSame(0, (int) $mirror->fresh()->player_id);
        $this->assertSame(2, (int) $mirror->fresh()->rank);
        $this->assertSame(3, (int) $sibling->fresh()->rank);
        $this->assertSame(2, NoProfileTeamPlayer::where('team_id', $team->id)->count());
        $this->assertNotNull($player->fresh());
        $this->postJson(route('backend.team.changePlayer'), $payload)->assertNotFound();
        $this->get(route('admin.events.teams', $event))->assertOk()->assertSee('New Player')->assertDontSee('Linked Actual')->assertDontSee('Imported Original');
        $this->assertSame(1, DB::table('activity_log')->where('description', 'changed team roster player')->count());
        Queue::assertNothingPushed(); Mail::assertNothingSent();
    }

    public function test_change_player_is_atomic_and_preserves_paid_or_handed_off_players(): void
    {
        Queue::fake();
        [$event, $region, $team, $manager] = $this->roster(true);
        $player = Player::factory()->create();
        $old = NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Old', 'surname' => 'Player', 'rank' => 2, 'player_profile' => $player->id, 'pay_status' => 0]);
        NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Duplicate', 'surname' => 'Player', 'rank' => 3, 'pay_status' => 0]);
        $mirror = app(\App\Domain\Payments\Services\TeamPaymentService::class)->createUnpaidTeamPlayerSlot($team, $player, 2);
        $order = TeamPaymentOrder::create(['team_id' => $team->id, 'event_id' => $event->id, 'player_id' => $player->id, 'user_id' => $manager->id, 'total_amount' => 100, 'wallet_reserved' => 40, 'payfast_amount_due' => 60, 'pay_status' => false]);
        $payload = ['team_id' => $team->id, 'event_id' => $event->id, 'slot_id' => $old->id, 'expected_rank' => 2, 'expected_player_id' => $player->id, 'name' => 'Duplicate', 'surname' => 'Player'];
        $count = DB::table('activity_log')->count();
        $this->actingAs($manager)->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable();
        $this->assertSame(40.0, (float) $order->fresh()->wallet_reserved);
        $this->assertNull($order->fresh()->withdrawn_at);
        $this->assertSame($player->id, (int) $old->fresh()->player_profile);
        $this->assertSame($player->id, (int) $mirror->fresh()->player_id);
        $this->assertSame($count, DB::table('activity_log')->count());
        $payload['name'] = 'Replacement';
        $order->update(['payfast_handed_off_at' => now()]);
        $this->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable();
        $order->update(['payfast_handed_off_at' => null, 'pay_status' => true]);
        $this->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable();
        $this->assertSame($player->id, (int) $old->fresh()->player_profile);
        $this->assertSame(2, NoProfileTeamPlayer::where('team_id', $team->id)->count());
        $this->assertSame(1, TeamPaymentOrder::count());
        Queue::assertNothingPushed();
    }

    public function test_unlinked_name_edit_preserves_slot_rank_mirror_and_audits_for_regional_and_admin(): void
    {
        Queue::fake(); Mail::fake();
        [$event, $region, $team, $manager] = $this->roster(true);
        $slot = NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Typoo', 'surname' => 'Unlinked', 'rank' => 2, 'pay_status' => 0]);
        $payments = app(\App\Domain\Payments\Services\TeamPaymentService::class);
        $mirror = $payments->createUnpaidTeamPlayerSlot($team, Player::factory()->create(), 2);
        $payments->updateTeamPlayerSlot($mirror, ['player_id' => 0]);
        $beforeMirror = $mirror->fresh()->getAttributes();
        $this->actingAs($manager)->get(route('admin.events.teams', $event))->assertOk()->assertSee('Edit name')->assertSee('Save name')->assertSee('value="Typoo"', false);
        $payload = ['event_id' => $event->id, 'team_id' => $team->id, 'slot_id' => $slot->id, 'expected_rank' => 2,
            'expected_player_id' => 0, 'expected_name' => 'Typoo', 'expected_surname' => 'Unlinked', 'name' => '  Typo  ', 'surname' => 'Unlinked'];
        $this->postJson(route('backend.team.editPlayerName'), $payload)->assertOk();
        $this->assertSame('Typo', $slot->fresh()->name);
        $this->assertSame(2, (int) $slot->fresh()->rank);
        $this->assertSame($beforeMirror, $mirror->fresh()->getAttributes());
        $this->assertSame(1, NoProfileTeamPlayer::where('team_id', $team->id)->count());
        $this->assertSame(0, TeamPaymentOrder::count());
        $audit = DB::table('activity_log')->where('description', 'regional manager corrected imported roster name')->first();
        $this->assertNotNull($audit);
        $this->assertSame($manager->id, (int) $audit->causer_id);
        $this->assertSame('Typoo', json_decode($audit->properties, true)['before']['name']);
        $this->postJson(route('backend.team.editPlayerName'), $payload)->assertUnprocessable();
        EventAdmin::create(['event_id' => $event->id, 'user_id' => $manager->id]);
        $team->update(['category_event_id' => null, 'year' => (int) $event->start_date->year]);
        $this->postJson(route('backend.team.editPlayerName'), array_replace($payload, ['expected_name' => 'Typo', 'name' => 'Corrected']))->assertOk();
        $this->assertSame('Corrected', $slot->fresh()->name);
        $this->assertSame($slot->id, NoProfileTeamPlayer::sole()->id);
        Queue::assertNothingPushed(); Mail::assertNothingSent();
    }

    public function test_name_edit_rejects_linked_mirrors_duplicates_stale_and_other_scopes(): void
    {
        [$event, $region, $team, $manager] = $this->roster(true);
        $slot = NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Typoo', 'surname' => 'Unlinked', 'rank' => 1, 'pay_status' => 0]);
        NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Existing', 'surname' => 'Unlinked', 'rank' => 2, 'pay_status' => 0]);
        $payload = ['event_id' => $event->id, 'team_id' => $team->id, 'slot_id' => $slot->id, 'expected_rank' => 1,
            'expected_player_id' => 0, 'expected_name' => 'Typoo', 'expected_surname' => 'Unlinked', 'name' => 'Corrected', 'surname' => 'Unlinked'];
        $this->actingAs($manager);
        foreach ([['name' => 'Existing'], ['name' => '  '], ['name' => str_repeat('x', 101)], ['expected_rank' => 2], ['expected_name' => 'Stale']] as $bad) {
            $this->postJson(route('backend.team.editPlayerName'), array_replace($payload, $bad))->assertUnprocessable();
        }
        [, $otherRegion, $otherTeam] = $this->roster(true);
        $this->postJson(route('backend.team.editPlayerName'), array_replace($payload, ['event_id' => $otherRegion->event_id]))->assertNotFound();
        $this->postJson(route('backend.team.editPlayerName'), array_replace($payload, ['team_id' => $otherTeam->id, 'event_id' => $otherRegion->event_id]))->assertForbidden();
        $otherTeam->update(['category_event_id' => $team->category_event_id]);
        $siblingRegion = new EventRegion();
        $siblingRegion->forceFill(['event_id' => $event->id, 'region_id' => $otherTeam->region_id, 'ordering' => 2])->save();
        $this->postJson(route('backend.team.editPlayerName'), array_replace($payload, ['team_id' => $otherTeam->id]))->assertForbidden();
        $this->postJson(route('backend.team.editPlayerName'), array_replace($payload, ['slot_id' => 999999]))->assertNotFound();
        $player = Player::factory()->create();
        $mirror = app(\App\Domain\Payments\Services\TeamPaymentService::class)->createUnpaidTeamPlayerSlot($team, $player, 1);
        $this->postJson(route('backend.team.editPlayerName'), $payload)->assertUnprocessable();
        $mirror->delete();
        $slot->update(['player_profile' => $player->id]);
        $this->postJson(route('backend.team.editPlayerName'), $payload)->assertUnprocessable();
        $this->assertSame('Typoo', $slot->fresh()->name);
        $this->assertSame($player->id, (int) $slot->fresh()->player_profile);
        $this->assertSame(2, NoProfileTeamPlayer::where('team_id', $team->id)->count());
        $this->assertSame(0, DB::table('activity_log')->where('description', 'regional manager corrected imported roster name')->count());
    }

    public function test_raw_duplicate_checks_include_authoritative_linked_names_for_add_edit_and_change(): void
    {
        Queue::fake(); Mail::fake();
        [$event, $region, $team, $manager] = $this->roster(true);
        $linked = Player::factory()->create(['name' => 'LinkedVisible', 'surname' => 'Sandbox']);
        $other = Player::factory()->create();
        $target = NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'ImportedOld', 'surname' => 'Different', 'rank' => 2, 'player_profile' => $linked->id, 'pay_status' => 0]);
        $raw = NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Editable', 'surname' => 'Raw', 'rank' => 3, 'pay_status' => 0]);
        $change = NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Another', 'surname' => 'Imported', 'rank' => 4, 'player_profile' => $other->id, 'pay_status' => 0]);
        $payments = app(\App\Domain\Payments\Services\TeamPaymentService::class);
        $mirror = $payments->createUnpaidTeamPlayerSlot($team, $linked, 2);
        $otherMirror = $payments->createUnpaidTeamPlayerSlot($team, $other, 4);
        $auditCount = DB::table('activity_log')->count();
        $this->actingAs($manager);
        $base = ['event_id' => $event->id, 'team_id' => $team->id, 'name' => ' linkedvisible ', 'surname' => ' SANDBOX '];
        $this->postJson(route('backend.team.addPlayers'), $base)->assertUnprocessable();
        $this->postJson(route('backend.team.editPlayerName'), $base + ['slot_id' => $raw->id, 'expected_rank' => 3, 'expected_player_id' => 0, 'expected_name' => 'Editable', 'expected_surname' => 'Raw'])->assertUnprocessable();
        $this->postJson(route('backend.team.changePlayer'), $base + ['slot_id' => $change->id, 'expected_rank' => 4, 'expected_player_id' => $other->id])->assertUnprocessable();
        $this->postJson(route('backend.team.addPlayers'), array_replace($base, ['name' => 'ImportedOld', 'surname' => 'Different']))->assertUnprocessable();
        $this->assertSame(3, NoProfileTeamPlayer::where('team_id', $team->id)->count());
        $this->assertSame('Editable', $raw->fresh()->name);
        $this->assertSame($other->id, (int) $change->fresh()->player_profile);
        $this->assertSame($linked->id, (int) $mirror->fresh()->player_id);
        $this->assertSame($other->id, (int) $otherMirror->fresh()->player_id);
        $this->assertSame($auditCount, DB::table('activity_log')->count());
        $this->assertSame(0, TeamPaymentOrder::count());
        Queue::assertNothingPushed(); Mail::assertNothingSent();
    }

    public function test_regional_capacity_is_enforced_while_event_admin_retains_append_override(): void
    {
        [$event, $region, $team, $manager] = $this->roster(true);
        $team->update(['num_team_members' => 1]);
        NoProfileTeamPlayer::create(['team_id' => $team->id, 'rank' => 1, 'name' => 'Existing', 'surname' => 'Player', 'pay_status' => 0]);
        $payload = ['event_id' => $event->id, 'team_id' => $team->id, 'name' => 'Extra', 'surname' => 'Player'];
        $this->actingAs($manager)->postJson(route('backend.team.addPlayers'), $payload)->assertUnprocessable();
        $this->assertSame(1, NoProfileTeamPlayer::where('team_id', $team->id)->count());
        EventAdmin::create(['event_id' => $event->id, 'user_id' => $manager->id]);
        $this->postJson(route('backend.team.addPlayers'), $payload)->assertOk();
        $this->assertSame(2, NoProfileTeamPlayer::where('team_id', $team->id)->count());
        $this->assertSame(2, (int) NoProfileTeamPlayer::where('team_id', $team->id)->where('name', 'Extra')->sole()->rank);
        $this->assertSame(0, TeamPaymentOrder::count());
    }

    private function rosterChange(Team $team, array $payload)
    {
        $route = match ($payload['action']) { 'add' => 'backend.team.addPlayers', 'move' => 'team.order.player.list', default => 'backend.team.removePlayer' };
        if (isset($payload['player_id'])) $payload['player_ids'] = [$payload['player_id']];
        return $this->postJson(route($route), $payload + ['team_id' => $team->id, 'event_id' => $team->category->event_id]);
    }
    private function roster(bool $noProfile = false): array
    {
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Regional roster', 'type' => EventType::TEAM, 'code' => 'regional-roster-'.uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        $event = Event::factory()->create(['eventType' => $type, 'published' => true, 'start_date' => now()->addMonth()]);
        $region = TeamRegion::create(['region_name' => 'Eden test']);
        $eventRegion = new EventRegion();
        $eventRegion->forceFill(['event_id' => $event->id, 'region_id' => $region->id, 'ordering' => 1])->save();
        $manager = User::factory()->create();
        EventRegionManager::create(['event_id' => $event->id, 'event_region_id' => $eventRegion->id, 'region_id' => $region->id, 'user_id' => $manager->id, 'assigned_by' => $manager->id]);
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id, 'region_id' => $region->id, 'noProfile' => $noProfile, 'num_team_members' => 4]);

        return [$event, $eventRegion, $team, $manager];
    }
}
