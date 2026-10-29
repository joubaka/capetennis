<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, EventSelectionFinalization, EventSelectionRosterImport, EventType, NoProfileTeamPlayer, Player, Team, TeamPlayer, TeamRegion, TeamRosterPromotion, User};
use App\Services\SelectedTeamRosterStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamRosterPlaceTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private Team $team;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actor = User::factory()->create()->assignRole('super-user');
        $this->actingAs($this->actor);
        DB::table('eventtypes')->insertOrIgnore(['id' => 3, 'name' => 'Team', 'type' => EventType::TEAM]);
        $this->event = Event::factory()->create(['eventType' => 3]);
        $region = TeamRegion::create(['region_name' => 'Roster place region']);
        $this->event->regions()->attach($region);
        $this->team = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => CategoryEvent::factory()->create(['event_id' => $this->event->id])->id, 'noProfile' => true, 'num_team_members' => 3]);
    }

    private function fill(array $source, int $rank = 2)
    {
        return $this->postJson(route('backend.team.addPlayers'), $source + ['event_id' => $this->event->id, 'team_id' => $this->team->id, 'target_rank' => $rank]);
    }

    private function freezeReserve(NoProfileTeamPlayer $reserve): void
    {
        $final = EventSelectionFinalization::create(['event_id' => $this->event->id, 'finalized_by' => $this->actor->id, 'draft_versions' => [], 'groups' => [], 'fingerprint' => str_repeat('c', 64), 'team_size' => 3, 'reserve_count' => 1, 'finalized_at' => now()]);
        EventSelectionRosterImport::create(['event_id' => $this->event->id, 'finalization_id' => $final->id, 'region_id' => $this->team->region_id, 'imported_by' => $this->actor->id, 'groups' => ['group' => ['team_id' => $this->team->id, 'players' => [['slot_id' => $reserve->id, 'status' => 'reserve', 'profile_id' => (int) $reserve->player_profile]]]], 'imported_at' => now()]);
    }

    public function test_promoted_reserve_can_reorder_with_hidden_overflow_empty_mirror_and_preserve_main_status(): void
    {
        $main = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'name' => 'Main', 'surname' => 'Player', 'pay_status' => 0]);
        $reserve = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 4, 'name' => 'Promoted', 'surname' => 'Reserve', 'pay_status' => 0]);
        $queued = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 5, 'name' => 'Queued', 'surname' => 'Reserve', 'pay_status' => 0]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'player_id' => 0, 'pay_status' => 0]);
        $empty = TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 2, 'player_id' => 0, 'pay_status' => 0]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 4, 'player_id' => 0, 'pay_status' => 0]);
        $this->freezeReserve($reserve);
        $this->fill(['reserve_slot_id' => $reserve->id, 'expected_reserve_rank' => 4])->assertOk();
        $original = TeamRosterPromotion::firstOrFail()->getAttributes();
        $order = collect([$reserve, $main, $queued])->map(fn ($slot, $index) => ['id' => $slot->id, 'no_profile_id' => $slot->id, 'position' => [1, 2, 5][$index]])->all();
        $this->postJson(route('team.order.player.list'), ['event_id' => $this->event->id, 'team_id' => $this->team->id, 'order' => $order])->assertOk();
        $this->assertSame(1, (int) $reserve->fresh()->rank);
        $this->assertSame(4, (int) $empty->fresh()->rank);
        $this->assertSame($original, TeamRosterPromotion::findOrFail($original['id'])->getAttributes());
        app(SelectedTeamRosterStatus::class)->assertMainSlot($this->event, $this->team, $reserve->fresh());
        $this->assertDatabaseCount('team_roster_promotions', 2);
        $this->postJson(route('team.order.player.list'), ['event_id' => $this->event->id, 'team_id' => $this->team->id, 'direction' => 'down', 'slot_id' => $reserve->id, 'expected_rank' => 1, 'expected_player_id' => 0])->assertOk();
        app(SelectedTeamRosterStatus::class)->assertMainSlot($this->event, $this->team, $reserve->fresh());
        $this->assertSame(2, (int) $reserve->fresh()->rank);
        $this->assertDatabaseCount('team_roster_promotions', 3);
        foreach (['players', 'order'] as $panel) {
            $this->get(route('admin.events.teams', $this->event).'?roster_region='.$this->team->region_id.'&panel='.$panel)->assertOk()->assertDontSee('data-teamplayerid="'.$empty->id.'"', false);
        }
        $this->assertDatabaseHas('team_players', ['id' => $empty->id, 'rank' => 4, 'player_id' => 0]);
    }

    public function test_stale_frozen_reserve_inside_main_places_requires_explicit_confirmation_and_is_idempotent(): void
    {
        $reserve = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 2, 'name' => 'Already', 'surname' => 'Reordered', 'pay_status' => 0]);
        $this->freezeReserve($reserve);
        $this->get(route('admin.events.teams', $this->event).'?roster_region='.$this->team->region_id)->assertOk()->assertSee('Confirm main place');
        $this->assertDatabaseCount('team_roster_promotions', 0);
        $payload = ['event_id' => $this->event->id, 'team_id' => $this->team->id, 'confirm_main_slot_id' => $reserve->id, 'expected_rank' => 2];
        $this->postJson(route('backend.team.addPlayers'), $payload)->assertOk();
        $this->postJson(route('backend.team.addPlayers'), $payload)->assertOk();
        app(SelectedTeamRosterStatus::class)->assertMainSlot($this->event, $this->team, $reserve->fresh());
        $this->assertDatabaseCount('team_roster_promotions', 1);
        $this->get(route('admin.events.teams', $this->event).'?roster_region='.$this->team->region_id)->assertOk()->assertDontSee('Confirm main place');
    }

    public function test_confirmation_rejects_ambiguous_imported_rank_without_creating_promotion(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') $this->markTestSkipped('Legacy duplicate-rank corruption is exercised only in isolated SQLite.');
        $reserve = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 2, 'name' => 'Ambiguous', 'surname' => 'Reserve', 'pay_status' => 0]);
        $this->freezeReserve($reserve);
        // Simulate a legacy database predating the roster rank uniqueness repair.
        DB::statement('DROP INDEX no_profile_team_players_team_rank_unique');
        NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 2, 'name' => 'Conflicting', 'surname' => 'Player', 'pay_status' => 0]);
        $this->postJson(route('backend.team.addPlayers'), ['event_id' => $this->event->id, 'team_id' => $this->team->id, 'confirm_main_slot_id' => $reserve->id, 'expected_rank' => 2])->assertUnprocessable();
        $this->assertDatabaseCount('team_roster_promotions', 0);
    }

    public function test_order_can_promote_frozen_reserve_but_cannot_displace_a_paid_main_player(): void
    {
        $main = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 3, 'name' => 'Paid', 'surname' => 'Main', 'pay_status' => 1]);
        $reserve = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 4, 'name' => 'Incoming', 'surname' => 'Reserve', 'pay_status' => 0]);
        $this->freezeReserve($reserve);
        $payload = ['event_id' => $this->event->id, 'team_id' => $this->team->id, 'order' => [['id' => $reserve->id, 'no_profile_id' => $reserve->id, 'position' => 3], ['id' => $main->id, 'no_profile_id' => $main->id, 'position' => 4]]];
        $this->postJson(route('team.order.player.list'), $payload)->assertUnprocessable();
        $this->assertSame(3, (int) $main->fresh()->rank);
        $this->assertSame(4, (int) $reserve->fresh()->rank);
        $this->assertDatabaseCount('team_roster_promotions', 0);
        $main->update(['pay_status' => 0]);
        $this->postJson(route('team.order.player.list'), $payload)->assertOk();
        app(SelectedTeamRosterStatus::class)->assertMainSlot($this->event, $this->team, $reserve->fresh());
        $this->assertDatabaseCount('team_roster_promotions', 1);
    }

    public function test_empty_numbered_places_show_team_reserves_before_database_search_and_change_name_for_unlinked_player(): void
    {
        NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'name' => 'Current', 'surname' => 'Player', 'pay_status' => 0]);
        NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 4, 'name' => 'Queued', 'surname' => 'Reserve', 'pay_status' => 0]);
        $panel = $this->get(route('admin.events.teams', $this->event).'?roster_region='.$this->team->region_id)->assertOk()
            ->assertSee('Add player to Rank 2')->assertSee('Add player to Rank 3')->assertSee('Change name')
            ->assertSeeInOrder(['Choose a team reserve', 'Add Queued Reserve', 'Existing player profile', 'Player without a profile'])
            ->assertSee('name="target_rank" value="2"', false);
        if (getenv('CT_ROSTER_PLACE_QA') === '1') {
            file_put_contents(storage_path('framework/testing/qa-roster-panel.html'), $panel->getContent());
            file_put_contents(storage_path('framework/testing/qa-roster.html'), $this->get(route('admin.events.teams', $this->event).'?selected_region='.$this->team->region_id)->getContent());
        }
        $this->assertDatabaseCount('team_players', 0);
    }

    public function test_frozen_imported_reserve_moves_existing_identity_to_exact_gap_with_durable_promotion_and_original_snapshot(): void
    {
        $reserve = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 4, 'name' => 'Frozen', 'surname' => 'Reserve', 'pay_status' => 0]);
        $final = EventSelectionFinalization::create(['event_id' => $this->event->id, 'finalized_by' => $this->actor->id, 'draft_versions' => [], 'groups' => [], 'fingerprint' => str_repeat('b', 64), 'team_size' => 3, 'reserve_count' => 1, 'finalized_at' => now()]);
        $snapshot = ['group' => ['team_id' => $this->team->id, 'players' => [['slot_id' => $reserve->id, 'status' => 'reserve', 'profile_id' => 0]]]];
        $import = EventSelectionRosterImport::create(['event_id' => $this->event->id, 'finalization_id' => $final->id, 'region_id' => $this->team->region_id, 'imported_by' => $this->actor->id, 'groups' => $snapshot, 'imported_at' => now()]);
        $this->fill(['reserve_slot_id' => $reserve->id, 'expected_reserve_rank' => 4])->assertOk();
        $this->assertSame(2, (int) $reserve->fresh()->rank);
        $this->assertEquals($snapshot, $import->fresh()->groups);
        app(SelectedTeamRosterStatus::class)->assertMainSlot($this->event, $this->team, $reserve->fresh());
        $this->assertDatabaseHas('team_roster_promotions', ['event_id' => $this->event->id, 'team_id' => $this->team->id, 'no_profile_slot_id' => $reserve->id, 'source_rank' => 4, 'target_rank' => 2, 'actor_id' => $this->actor->id]);
        $this->assertDatabaseCount('no_profile_team_players', 1);
        $this->fill(['reserve_slot_id' => $reserve->id, 'expected_reserve_rank' => 4])->assertUnprocessable();
        $this->assertDatabaseCount('team_roster_promotions', 1);
        $this->assertDatabaseCount('team_payment_orders', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_profile_reserve_moves_mirror_into_exact_gap_without_changing_other_places(): void
    {
        $this->team->update(['noProfile' => false]);
        $player = Player::factory()->create();
        $source = TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 4, 'player_id' => $player->id, 'pay_status' => 0]);
        $empty = TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 2, 'player_id' => 0, 'pay_status' => 0]);
        $this->fill(['reserve_slot_id' => $source->id, 'expected_reserve_rank' => 4])->assertOk();
        $this->assertSame(2, (int) $source->fresh()->rank);
        $this->assertSame(4, (int) $empty->fresh()->rank);
        app(SelectedTeamRosterStatus::class)->assertMainPlayer($this->event, $this->team, $player->id);
        $this->assertSame([], app(SelectedTeamRosterStatus::class)->classify($this->event, collect([$this->team->fresh()]))['profile']);
        $this->assertDatabaseCount('team_players', 2);
        $this->assertDatabaseCount('team_roster_promotions', 1);
    }

    public function test_database_profile_fills_exact_no_profile_gap_with_unpaid_canonical_mirror(): void
    {
        $player = Player::factory()->create();
        $this->fill(['player_id' => $player->id])->assertOk();
        $this->assertDatabaseHas('no_profile_team_players', ['team_id' => $this->team->id, 'rank' => 2, 'player_profile' => $player->id, 'pay_status' => 0]);
        $this->assertDatabaseHas('team_players', ['team_id' => $this->team->id, 'rank' => 2, 'player_id' => $player->id, 'pay_status' => 0]);
        $this->assertDatabaseCount('team_roster_promotions', 0);
        $this->assertDatabaseCount('team_payment_orders', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->fill(['player_id' => $player->id], 3)->assertUnprocessable();
        $this->assertDatabaseCount('no_profile_team_players', 1);
    }

    public function test_fill_rejects_stale_paid_foreign_and_occupied_sources_and_targets(): void
    {
        $source = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 4, 'name' => 'Protected', 'surname' => 'Reserve', 'pay_status' => 1]);
        $payload = ['reserve_slot_id' => $source->id, 'expected_reserve_rank' => 4];
        $this->fill($payload)->assertUnprocessable();
        $source->update(['pay_status' => 0]);
        $this->fill(array_replace($payload, ['expected_reserve_rank' => 5]))->assertUnprocessable();
        $this->fill($payload, 4)->assertUnprocessable();
        NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 2, 'name' => 'Occupied', 'surname' => 'Place', 'pay_status' => 0]);
        $this->fill($payload)->assertUnprocessable();
        $this->postJson(route('backend.team.addPlayers'), $payload + ['event_id' => Event::factory()->create()->id, 'team_id' => $this->team->id, 'target_rank' => 3])->assertNotFound();
        $this->actingAs(User::factory()->create())->fill($payload, 3)->assertForbidden();
        $this->assertSame(4, (int) $source->fresh()->rank);
        $this->assertDatabaseCount('team_roster_promotions', 0);
    }

    public function test_linked_reserve_moves_both_identities_and_rejects_checkout_history_before_promotion(): void
    {
        $player = Player::factory()->create();
        $source = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 4, 'name' => $player->name, 'surname' => $player->surname, 'player_profile' => $player->id, 'pay_status' => 0]);
        $mirror = TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 4, 'player_id' => $player->id, 'pay_status' => 0]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 2, 'player_id' => 0, 'pay_status' => 0]);
        $order = \App\Models\TeamPaymentOrder::create(['event_id' => $this->event->id, 'team_id' => $this->team->id, 'player_id' => $player->id, 'user_id' => $this->actor->id, 'total_amount' => 100, 'wallet_reserved' => 0, 'payfast_amount_due' => 100, 'payfast_handed_off_at' => now(), 'pay_status' => false]);
        $payload = ['reserve_slot_id' => $source->id, 'expected_reserve_rank' => 4];
        $this->fill($payload)->assertUnprocessable();
        $this->assertSame(4, (int) $source->fresh()->rank);
        $this->assertDatabaseCount('team_roster_promotions', 0);
        // Remove only this synthetic checkout to exercise the clean reserve separately.
        DB::table('team_payment_orders')->where('id', $order->id)->delete();
        $this->fill($payload)->assertOk();
        $this->assertSame(2, (int) $source->fresh()->rank);
        $this->assertSame(2, (int) $mirror->fresh()->rank);
        app(SelectedTeamRosterStatus::class)->assertMainSlot($this->event, $this->team, $source->fresh());
        app(SelectedTeamRosterStatus::class)->assertMainPlayer($this->event, $this->team, $player->id);
    }

    public function test_failed_database_profile_link_rolls_back_raw_slot_and_preserves_checkout(): void
    {
        $player = Player::factory()->create();
        $order = \App\Models\TeamPaymentOrder::create(['event_id' => $this->event->id, 'team_id' => $this->team->id, 'player_id' => $player->id, 'user_id' => $this->actor->id, 'total_amount' => 100, 'wallet_reserved' => 0, 'payfast_amount_due' => 100, 'pay_status' => false]);
        $this->fill(['player_id' => $player->id])->assertUnprocessable();
        $this->assertDatabaseCount('no_profile_team_players', 0);
        $this->assertDatabaseCount('team_players', 0);
        $this->assertDatabaseHas('team_payment_orders', ['id' => $order->id, 'total_amount' => 100, 'pay_status' => false]);
        $this->assertDatabaseCount('team_roster_promotions', 0);
    }

    public function test_fill_requires_team_scope_and_preserves_selection_and_competition_guards(): void
    {
        $otherTeam = Team::factory()->create(['region_id' => $this->team->region_id, 'category_event_id' => $this->team->category_event_id, 'noProfile' => true, 'num_team_members' => 3]);
        $foreign = NoProfileTeamPlayer::create(['team_id' => $otherTeam->id, 'rank' => 4, 'name' => 'Other', 'surname' => 'Reserve', 'pay_status' => 0]);
        $this->fill(['reserve_slot_id' => $foreign->id, 'expected_reserve_rank' => 4])->assertUnprocessable();
        $player = Player::factory()->create();
        $this->actingAs(User::factory()->create())->fill(['player_id' => $player->id])->assertForbidden();
        $manager = User::factory()->create();
        $eventRegion = \App\Models\EventRegion::where('event_id', $this->event->id)->where('region_id', $this->team->region_id)->firstOrFail();
        \App\Models\EventRegionManager::create(['event_id' => $this->event->id, 'event_region_id' => $eventRegion->id, 'region_id' => $this->team->region_id, 'user_id' => $manager->id]);
        $this->actingAs($manager)->fill(['player_id' => $player->id], 1)->assertOk();
        $this->assertDatabaseHas('no_profile_team_players', ['team_id' => $this->team->id, 'rank' => 1, 'player_profile' => $player->id]);
        $this->actingAs($this->actor);
        $probePlayer = Player::factory()->create();
        $import = \App\Models\TeamSelectionImport::create(['event_id' => $this->event->id, 'region_id' => $this->team->region_id, 'source_id' => 1, 'series_id' => 1, 'ranking_run_id' => 'place-restriction', 'status' => 'draft']);
        \App\Models\TeamSelectionInvitation::create(['import_id' => $import->id, 'event_id' => $this->event->id, 'region_id' => $this->team->region_id, 'team_id' => $this->team->id, 'player_id' => $player->id, 'ranking_list_id' => 1, 'ranking_position' => 1, 'queue_position' => 1]);
        $this->fill(['player_id' => $probePlayer->id])->assertUnprocessable()
            ->assertJsonPath('errors.roster.0', 'Use the selection workflow for this team.');
        $import->update(['status' => 'cancelled']);
        $draw = \App\Models\Draw::factory()->create(['event_id' => $this->event->id]);
        $fixture = \App\Models\TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'match_nr' => 1, 'round_nr' => 1, 'numSets' => 3]);
        \App\Models\TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'team1_id' => 0, 'team2_id' => 0]);
        $this->fill(['player_id' => $probePlayer->id])->assertUnprocessable()
            ->assertJsonPath('errors.roster.0', 'The competition has fixture history. Ask the event administrator to change this roster.');
        $this->assertDatabaseCount('team_roster_promotions', 0);
        $this->assertDatabaseCount('no_profile_team_players', 2);
    }

    public function test_change_name_preserves_unlinked_identity_and_rejects_stale_retry(): void
    {
        $slot = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'name' => 'Before', 'surname' => 'Player', 'pay_status' => 0]);
        $payload = ['event_id' => $this->event->id, 'team_id' => $this->team->id, 'slot_id' => $slot->id, 'expected_rank' => 1, 'expected_player_id' => 0, 'expected_name' => 'Before', 'expected_surname' => 'Player', 'name' => 'After', 'surname' => 'Player'];
        $this->postJson(route('backend.team.editPlayerName'), $payload)->assertOk();
        $this->assertDatabaseHas('no_profile_team_players', ['id' => $slot->id, 'rank' => 1, 'name' => 'After', 'player_profile' => null, 'pay_status' => 0]);
        $this->postJson(route('backend.team.editPlayerName'), $payload)->assertUnprocessable();
        $this->assertDatabaseHas('activity_log', ['subject_id' => $this->team->id, 'causer_id' => $this->actor->id, 'description' => 'regional manager corrected imported roster name']);
        $this->assertDatabaseCount('no_profile_team_players', 1);
    }
}
