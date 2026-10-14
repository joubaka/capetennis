<?php

namespace Tests\Feature;

use App\Domain\Teams\Services\ExternalTeamRosterService;
use App\Models\{CategoryEvent, Event, EventSelectionFinalization, EventSelectionRosterImport, NoProfileTeamPlayer, Player, Team, TeamPlayer, TeamRegion, User};
use App\Services\{EventPageDataService, SelectedTeamRosterStatus};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SelectedTeamReserveRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_region_cannot_identify_the_event_of_an_uncategorized_team(): void
    {
        [$event, $team, $slots, $actor] = $this->scenario();
        $other = Event::factory()->create(); $other->regions()->attach($team->region_id);
        $team->update(['category_event_id' => null]);
        $this->assertSame(['imported' => [], 'profile' => []], app(SelectedTeamRosterStatus::class)->classify($event, collect([$team->fresh()])));
        $this->assertFalse(app(ExternalTeamRosterService::class)->teamBelongsToEvent($team->fresh(), $event));
        app(EventPageDataService::class)->prepareRegions($event);
        $this->assertCount(0, $event->regions->first()->teams);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(SelectedTeamRosterStatus::class)->assertMainPlayer($event, $team, $slots[0]->player_profile);
    }

    public function test_profile_only_public_roster_hides_reserves_and_keeps_paid_reserve_history(): void
    {
        [$event, $team, $slots, $actor] = $this->scenario();
        \Illuminate\Support\Facades\DB::table('no_profile_team_players')->delete();
        $team->update(['noProfile' => false]);
        TeamPlayer::where('team_id', $team->id)->where('player_id', $slots[10]->player_profile)->update(['pay_status' => 1]);
        $order = \App\Models\TeamPaymentOrder::create(['team_id' => $team->id, 'event_id' => $event->id, 'player_id' => $slots[10]->player_profile,
            'user_id' => $actor->id, 'total_amount' => 0, 'wallet_reserved' => 0, 'payfast_amount_due' => 0, 'pay_status' => true]);
        app(EventPageDataService::class)->prepareRegions($event);
        $region = $event->regions->first(); $team = $region->teams->first();
        $html = view('frontend.event.partials.profile-team', ['event' => $event, 'team' => $team, 'region' => $region, 'canWithdraw' => true,
            'myPaidTeamOrdersByPlayer' => collect([$team->id.'-'.$slots[10]->player_profile => $order])])->render();
        $this->assertSame(10, preg_match_all('/>Register\s*</', $html));
        $this->assertStringNotContainsString('Reserves (2)', $html);
        $this->assertStringNotContainsString(route('team.player.withdraw', [$team->id, $slots[10]->player_profile, $event->id]), $html);
        $this->assertDatabaseHas('team_payment_orders', ['id' => $order->id, 'pay_status' => true]);
        if (getenv('CT_SELECTED_RESERVE_BROWSER_FIXTURE')) file_put_contents(storage_path('app/testing/selected-reserve-profile-only.html'), $html);
        TeamPlayer::where('team_id', $team->id)->where('player_id', $slots[0]->player_profile)->update(['rank' => 20]);
        TeamPlayer::where('team_id', $team->id)->where('player_id', $slots[10]->player_profile)->update(['rank' => 1]);
        $team->update(['num_team_members' => 12]);
        $this->expectException(ValidationException::class);
        app(SelectedTeamRosterStatus::class)->assertMainPlayer($event, $team, $slots[10]->player_profile);
    }

    public function test_trials_keep_their_dedicated_selection_and_registration_policy(): void
    {
        [$event, $team] = $this->scenario();
        $type = \Illuminate\Support\Facades\DB::table('eventtypes')->insertGetId(['name' => 'Interprovincial Trials', 'code' => 'interprovincial-trials', 'type' => \App\Models\EventType::TEAM]);
        $event->update(['eventType' => $type]); $event->unsetRelation('eventTypeModel');
        $this->assertTrue($event->isTeam()); $this->assertTrue($event->isInterprovincialTrials());
        $this->assertSame(['imported' => [], 'profile' => []], app(SelectedTeamRosterStatus::class)->classify($event, collect([$team->fresh()])));
    }

    public function test_ordinary_team_capacity_applies_to_both_roster_modes_but_zero_capacity_and_nonteam_events_keep_legacy_behavior(): void
    {
        [$event, $team, $slots] = $this->scenario();
        \Illuminate\Support\Facades\DB::table('event_selection_roster_imports')->delete();
        foreach ([true, false] as $noProfile) {
            $team->update(['noProfile' => $noProfile]);
            $status = app(SelectedTeamRosterStatus::class)->classify($event, collect([$team->fresh()]));
            $this->assertCount(2, $status['profile'][$team->id]);
            $this->assertCount(2, $status['imported'][$team->id]);
        }
        $team->update(['num_team_members' => 0]);
        $this->assertSame(['imported' => [], 'profile' => []], app(SelectedTeamRosterStatus::class)->classify($event, collect([$team->fresh()])));
        $team->update(['num_team_members' => 10]);
        $type = \Illuminate\Support\Facades\DB::table('eventtypes')->insertGetId(['name' => 'Individual test', 'code' => 'individual-reserve-test', 'type' => \App\Models\EventType::INDIVIDUAL]);
        $event->update(['eventType' => $type]); $event->unsetRelation('eventTypeModel');
        $this->assertSame(['imported' => [], 'profile' => []], app(SelectedTeamRosterStatus::class)->classify($event, collect([$team->fresh()])));
    }

    public function test_only_audited_current_invitation_promotion_overrides_frozen_reserve(): void
    {
        [$event, $team, $slots, $actor] = $this->scenario();
        $slots[0]->update(['rank' => 20]);
        TeamPlayer::where('team_id', $team->id)->where('player_id', $slots[0]->player_profile)->update(['rank' => 20]);
        $slots[10]->update(['rank' => 1]);
        TeamPlayer::where('team_id', $team->id)->where('player_id', $slots[10]->player_profile)->update(['rank' => 1]);
        $managed = \App\Models\TeamSelectionImport::create(['source_id' => 1, 'event_id' => $event->id, 'region_id' => $team->region_id, 'series_id' => 1, 'ranking_run_id' => 'reserve-promotion-test', 'status' => 'draft']);
        $invitation = \App\Models\TeamSelectionInvitation::create(['import_id' => $managed->id, 'event_id' => $event->id, 'region_id' => $team->region_id, 'team_id' => $team->id, 'player_id' => $slots[10]->player_profile, 'ranking_list_id' => 1, 'ranking_position' => 11, 'queue_position' => 11, 'roster_rank' => 1, 'status' => 'invited']);
        $service = app(SelectedTeamRosterStatus::class);
        $this->assertArrayHasKey($slots[10]->id, $service->reserveSlots($event, collect([$team->fresh()]))[$team->id]);
        $invitation->update(['snapshot_json' => ['activation' => ['activated_at' => now()->toIso8601String(), 'activated_by_user_id' => $actor->id]]]);
        $this->assertArrayNotHasKey($slots[10]->id, $service->reserveSlots($event, collect([$team->fresh()]))[$team->id]);
        $this->assertSame((int) $slots[10]->player_profile, (int) app(ExternalTeamRosterService::class)->assertCanRegister($actor, $event, $team, Player::findOrFail($slots[10]->player_profile))->player_id);
        $invitation->update(['status' => 'reserve', 'roster_rank' => null]);
        $this->assertArrayHasKey($slots[10]->id, $service->reserveSlots($event, collect([$team->fresh()]))[$team->id]);
        $invitation->update(['status' => 'invited', 'roster_rank' => 1]);
        \App\Models\TeamSelectionImport::create(['source_id' => 1, 'event_id' => $event->id, 'region_id' => $team->region_id, 'series_id' => 1, 'ranking_run_id' => 'new-current-selection', 'status' => 'draft']);
        $this->assertArrayHasKey($slots[10]->id, $service->reserveSlots($event, collect([$team->fresh()]))[$team->id]);
    }

    public function test_combined_create_rechecks_after_event_lock_before_identity_creation(): void
    {
        [$event, $team, $slots] = $this->scenario();
        \Illuminate\Support\Facades\DB::table('event_selection_roster_imports')->delete();
        $team->update(['num_team_members' => 12]); $slots[11]->update(['player_profile' => null]);
        $this->partialMock(\App\Services\TeamDrawAdaptationService::class, function ($mock) use ($event, $team) {
            $mock->shouldReceive('lockEvent')->once()->andReturnUsing(function () use ($event, $team) {
                $team->update(['num_team_members' => 10]); return $event;
            });
        });
        $this->actingAs(User::factory()->create())->post(route('player.store'), ['type' => 'noProfile', 'noProfile' => $slots[11]->id,
            'team' => $team->id, 'event' => $event->id, 'player_name' => 'New', 'player_surname' => 'Reserve', 'dob' => '2013-01-01', 'gender' => 1])
            ->assertRedirect()->assertSessionHasErrors('player');
        $this->assertDatabaseCount('players', 12);
        $this->assertDatabaseCount('user_players', 0);
        $this->assertDatabaseCount('team_payment_orders', 0);
    }

    public function test_public_roster_shows_only_main_players_for_both_presentations(): void
    {
        [$event, $team, $slots, $actor] = $this->scenario();
        app(EventPageDataService::class)->prepareRegions($event);
        $region = $event->regions->first(); $team = $region->teams->first();
        foreach (['no-profile-team', 'profile-team'] as $partial) {
            $team->noProfile = $partial === 'no-profile-team';
            $html = view('frontend.event.partials.'.$partial, compact('event', 'team', 'region'))->render();
            $this->assertStringNotContainsString('Reserves (2)', $html);
            $this->assertSame(10, preg_match_all('/>Register\s*</', $html));
            $this->assertStringNotContainsString('Reserve11', $html);
            $this->assertStringNotContainsString('Reserve12', $html);
            $this->assertStringNotContainsString(route('team.payment.payfast', [$team->id, $slots[10]->player_profile, $event->id]), $html);
            if (getenv('CT_SELECTED_RESERVE_BROWSER_FIXTURE')) file_put_contents(storage_path('app/testing/selected-reserve-'.$partial.'.html'), $html);
        }
        $this->assertDatabaseCount('no_profile_team_players', 12);
        $this->assertDatabaseCount('team_players', 12);
    }

    public function test_reserve_direct_checkout_is_rejected_without_orders_or_identity_changes_and_main_sponsorship_remains_allowed(): void
    {
        [$event, $team, $slots] = $this->scenario();
        $sponsor = User::factory()->create();
        $reserve = Player::findOrFail($slots[10]->player_profile);
        $this->actingAs($sponsor)->get(route('team.payment.payfast', [$team, $reserve, $event]))->assertRedirect()->assertSessionHasErrors('player');
        $this->assertDatabaseCount('team_payment_orders', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('user_players', 0);
        $this->assertDatabaseCount('team_players', 12);
        $main = Player::findOrFail($slots[0]->player_profile);
        $selected = app(ExternalTeamRosterService::class)->assertCanRegister($sponsor, $event, $team, $main);
        $this->assertSame((int) $main->id, (int) $selected->player_id);
        $this->assertDatabaseCount('user_players', 0);
        $team->update(['num_team_members' => 12]);
        $slots[0]->update(['rank' => 20]);
        $slots[10]->update(['rank' => 1]);
        try {
            app(ExternalTeamRosterService::class)->assertCanRegister($sponsor, $event, $team, $reserve);
            $this->fail('Capacity/rank edits cannot promote a reserve.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('reserve', $exception->getMessage());
        }
    }

    public function test_reserve_combined_claim_checkout_rejects_before_profile_or_ownership_mutation(): void
    {
        [$event, $team, $slots] = $this->scenario();
        $slot = $slots[11]; $slot->update(['player_profile' => null]);
        $sponsor = User::factory()->create();
        $candidate = Player::factory()->create();
        $this->actingAs($sponsor)->post(route('player.attach'), ['player_id' => $candidate->id, 'team' => $team->id,
            'event' => $event->id, 'noProfile' => $slot->id, 'confirmed_profile' => 1])->assertRedirect()->assertSessionHasErrors('player');
        $this->assertNull($slot->fresh()->player_profile);
        $this->assertDatabaseCount('user_players', 0);
        $this->assertDatabaseCount('team_payment_orders', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_snapshot_status_is_event_and_team_scoped_and_latest_team_import_wins(): void
    {
        [$event, $team, $slots, $actor, $import] = $this->scenario();
        $foreign = Event::factory()->create();
        $foreignImport = EventSelectionRosterImport::create(['event_id' => $foreign->id, 'finalization_id' => $import->finalization_id,
            'region_id' => $team->region_id, 'imported_by' => $actor->id, 'imported_at' => now(),
            'groups' => ['10-boys' => ['team_id' => $team->id, 'players' => [['slot_id' => $slots[0]->id, 'status' => 'reserve']]]]]);
        $status = app(SelectedTeamRosterStatus::class)->reserveSlots($event, collect([$team]));
        $this->assertCount(2, $status[$team->id]);
        $this->assertArrayNotHasKey($slots[0]->id, $status[$team->id]);
        $newFinal = EventSelectionFinalization::create(['event_id' => $foreign->id, 'finalized_by' => $actor->id, 'draft_versions' => [], 'fingerprint' => str_repeat('b', 64), 'team_size' => 10, 'reserve_count' => 2, 'finalized_at' => now(), 'groups' => []]);
        EventSelectionRosterImport::create(['event_id' => $event->id, 'finalization_id' => $newFinal->id,
            'region_id' => $team->region_id, 'imported_by' => $actor->id, 'imported_at' => now(),
            'groups' => ['10-boys' => ['team_id' => $team->id, 'players' => [['slot_id' => $slots[0]->id, 'status' => 'reserve'], ['slot_id' => $slots[10]->id, 'status' => 'main']]]]]);
        $status = app(SelectedTeamRosterStatus::class)->reserveSlots($event, collect([$team]));
        $this->assertSame([$slots[0]->id, $slots[11]->id], array_keys($status[$team->id]));
    }

    private function scenario(): array
    {
        $type = \Illuminate\Support\Facades\DB::table('eventtypes')->insertGetId(['name' => 'Selected team test', 'code' => 'selected-team-test', 'type' => \App\Models\EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => $type, 'published' => true, 'signUp' => true, 'status' => 'active', 'start_date' => now()->addMonth(), 'deadline' => 2]);
        $region = TeamRegion::create(['region_name' => 'Selected region']); $event->regions()->attach($region);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $region->id, 'num_team_members' => 10, 'published' => true, 'noProfile' => true]);
        $actor = User::factory()->create(); $slots = collect(); $frozen = [];
        foreach (range(1, 12) as $rank) {
            $player = Player::factory()->create(['name' => $rank > 10 ? 'Reserve'.$rank : 'Main'.$rank, 'surname' => 'Selected']);
            $slot = NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => $player->name, 'surname' => $player->surname, 'rank' => $rank, 'player_profile' => $player->id, 'pay_status' => 0]);
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => $rank, 'pay_status' => 0]);
            $slots->push($slot); $frozen[] = ['slot_id' => $slot->id, 'profile_id' => $player->id, 'status' => $rank > 10 ? 'reserve' : 'main'];
        }
        $final = EventSelectionFinalization::create(['event_id' => Event::factory()->create()->id, 'finalized_by' => $actor->id, 'draft_versions' => [], 'fingerprint' => str_repeat('a', 64), 'team_size' => 10, 'reserve_count' => 2, 'finalized_at' => now(), 'groups' => []]);
        $import = EventSelectionRosterImport::create(['event_id' => $event->id, 'finalization_id' => $final->id, 'region_id' => $region->id, 'imported_by' => $actor->id, 'imported_at' => now(), 'groups' => ['10-boys' => ['team_id' => $team->id, 'players' => $frozen]]]);
        return [$event, $team, $slots, $actor, $import];
    }
}
