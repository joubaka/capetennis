<?php

namespace Tests\Feature;

use App\Domain\Teams\Services\ExternalTeamRosterService;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventRegion;
use App\Models\EventType;
use App\Models\NoProfileTeamPlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamRegion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExternalTeamRosterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private Team $team;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create([
            'published' => true,
            'signUp' => true,
            'status' => 'active',
            'start_date' => now()->addMonth(),
            'deadline' => 2,
        ]);
        $categoryEvent = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->team = Team::factory()->create([
            'category_event_id' => $categoryEvent->id,
            'num_team_members' => 2,
            'published' => true,
            'noProfile' => true,
        ]);
        $this->admin = User::factory()->create();
        EventAdmin::create(['user_id' => $this->admin->id, 'event_id' => $this->event->id]);
    }

    public function test_import_requires_preview_then_confirmation_and_never_accepts_payment_state(): void
    {
        $file = $this->roster("Rank,Name,Surname,DateOfBirth,PayStatus\n1,Ana,One,2013-01-02,1\n2,Ben,Two,,1\n");

        $preview = $this->actingAs($this->admin)->postJson(
            route('backend.team.import.no.profile', [$this->event, $this->team]),
            ['file' => $file]
        );

        $preview->assertOk()
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonCount(2, 'preview');
        $this->assertDatabaseCount('no_profile_team_players', 0);

        $confirmed = $this->actingAs($this->admin)->postJson(
            route('backend.team.import.no.profile', [$this->event, $this->team]),
            ['file' => $this->roster("Rank,Name,Surname,DateOfBirth,PayStatus\n1,Ana,One,2013-01-02,1\n2,Ben,Two,,1\n"), 'confirmed' => 1]
        );

        $confirmed->assertOk()->assertJsonPath('imported_count', 2);
        $this->assertDatabaseHas('no_profile_team_players', [
            'team_id' => $this->team->id,
            'rank' => 1,
            'name' => 'Ana',
            'pay_status' => 0,
        ]);
        $this->assertDatabaseCount('no_profile_team_players', 2);
        $this->assertDatabaseCount('team_players', 2);
    }

    public function test_import_rejects_cross_event_team_and_duplicate_ranks_without_writes(): void
    {
        $otherEvent = Event::factory()->create();
        EventAdmin::create(['user_id' => $this->admin->id, 'event_id' => $otherEvent->id]);

        $this->actingAs($this->admin)->postJson(
            route('backend.team.import.no.profile', [$otherEvent, $this->team]),
            ['file' => $this->roster("Rank,Name,Surname\n1,Ana,One\n2,Ben,Two\n")]
        )->assertNotFound();

        $this->actingAs($this->admin)->postJson(
            route('backend.team.import.no.profile', [$this->event, $this->team]),
            ['file' => $this->roster("Rank,Name,Surname\n1,Ana,One\n1,Ben,Two\n"), 'confirmed' => 1]
        )->assertStatus(422)->assertJsonPath('success', false);

        $this->assertDatabaseCount('no_profile_team_players', 0);
    }

    public function test_user_can_claim_an_owned_profile_and_continue_to_payment(): void
    {
        $user = User::factory()->create();
        $owned = Player::factory()->create(['userId' => $user->id]);
        $slot = NoProfileTeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'name' => $owned->name,
            'surname' => $owned->surname,
            'pay_status' => 0,
        ]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'player_id' => 0, 'pay_status' => 0]);

        $this->actingAs($user)->post(route('player.attach'), [
            'player_id' => $owned->id,
            'team' => $this->team->id,
            'event' => $this->event->id,
            'noProfile' => $slot->id,
        ])->assertRedirect(route('team.payment.payfast', [$this->team, $owned, $this->event]));

        $this->assertDatabaseHas('no_profile_team_players', [
            'id' => $slot->id,
            'player_profile' => $owned->id,
            'claimed_by_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('team_players', [
            'team_id' => $this->team->id,
            'rank' => 1,
            'player_id' => $owned->id,
            'pay_status' => 0,
        ]);
    }

    public function test_user_can_link_a_system_profile_after_private_identity_verification(): void
    {
        $user = User::factory()->create();
        $existingOwner = User::factory()->create();
        $player = Player::factory()->create([
            'name' => 'Ana',
            'surname' => 'One',
            'dateOfBirth' => '2013-01-02',
            'email' => 'ana@example.test',
            'userId' => $existingOwner->id,
        ]);
        $slot = NoProfileTeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'name' => 'Ana',
            'surname' => 'One',
            'date_of_birth' => '2013-01-02',
            'pay_status' => 0,
        ]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'player_id' => 0, 'pay_status' => 0]);

        $this->actingAs($user)->post(route('player.attach'), [
            'player_id' => $player->id,
            'team' => $this->team->id,
            'event' => $this->event->id,
            'noProfile' => $slot->id,
        ])->assertSessionHasErrors('player_id');
        $this->assertNull($slot->fresh()->player_profile);

        $this->actingAs($user)->post(route('player.attach'), [
            'player_id' => $player->id,
            'team' => $this->team->id,
            'event' => $this->event->id,
            'noProfile' => $slot->id,
            'date_of_birth' => '2013-01-02',
            'contact' => 'ANA@example.test',
        ])->assertRedirect(route('team.payment.payfast', [$this->team, $player, $this->event]));

        $this->assertDatabaseHas('user_players', ['user_id' => $user->id, 'player_id' => $player->id]);
        $this->assertSame($player->id, (int) $slot->fresh()->player_profile);
    }

    public function test_claim_rejects_a_profile_that_does_not_match_the_roster_identity(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['name' => 'Wrong', 'surname' => 'Person', 'userId' => $user->id]);
        $slot = NoProfileTeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'name' => 'Correct',
            'surname' => 'Player',
            'pay_status' => 0,
        ]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'player_id' => 0, 'pay_status' => 0]);

        $this->actingAs($user)->post(route('player.attach'), [
            'player_id' => $player->id,
            'team' => $this->team->id,
            'event' => $this->event->id,
            'noProfile' => $slot->id,
        ])->assertSessionHasErrors('player_id');

        $this->assertNull($slot->fresh()->player_profile);
    }

    public function test_closed_event_blocks_claiming_and_self_service_registration(): void
    {
        $this->event->update(['signUp' => false]);
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        $slot = NoProfileTeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'name' => $player->name,
            'surname' => $player->surname,
            'pay_status' => 0,
        ]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'player_id' => $player->id, 'pay_status' => 0]);

        $this->actingAs($user)->post(route('player.attach'), [
            'player_id' => $player->id,
            'team' => $this->team->id,
            'event' => $this->event->id,
            'noProfile' => $slot->id,
        ])->assertSessionHasErrors('event');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ExternalTeamRosterService::class)->assertCanRegister($user, $this->event->fresh(), $this->team, $player);
    }

    public function test_profile_owner_can_open_team_payment_without_team_management_permission(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        TeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'player_id' => $player->id,
            'pay_status' => 0,
        ]);

        $this->actingAs($user)->get(route('team.payment.payfast', [
            $this->team,
            $player,
            $this->event,
        ]))->assertOk()->assertViewIs('frontend.payfast.team_payment');

        $this->assertDatabaseHas('team_payment_orders', [
            'user_id' => $user->id,
            'team_id' => $this->team->id,
            'player_id' => $player->id,
            'event_id' => $this->event->id,
        ]);
    }

    public function test_import_preserves_an_existing_link_when_the_same_roster_is_reimported(): void
    {
        $player = Player::factory()->create();
        NoProfileTeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'name' => 'Ana',
            'surname' => 'One',
            'player_profile' => $player->id,
            'pay_status' => 0,
        ]);

        $this->actingAs($this->admin)->postJson(
            route('backend.team.import.no.profile', [$this->event, $this->team]),
            ['file' => $this->roster("Rank,Name,Surname\n1,Ana,One\n2,Ben,Two\n"), 'confirmed' => 1]
        )->assertOk();

        $this->assertSame($player->id, NoProfileTeamPlayer::where('team_id', $this->team->id)->where('rank', 1)->value('player_profile'));
    }

    public function test_creating_a_profile_claims_the_slot_and_links_the_profile_to_the_account(): void
    {
        $user = User::factory()->create();
        $slot = NoProfileTeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'name' => 'New',
            'surname' => 'Player',
            'pay_status' => 0,
        ]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'player_id' => 0, 'pay_status' => 0]);

        $response = $this->actingAs($user)->post(route('player.store'), [
            'type' => 'noProfile',
            'noProfile' => $slot->id,
            'team' => $this->team->id,
            'event' => $this->event->id,
            'player_name' => 'New',
            'player_surname' => 'Player',
            'dob' => '2013-04-05',
            'gender' => 1,
        ]);

        $player = Player::where('name', 'New')->where('surname', 'Player')->firstOrFail();
        $response->assertRedirect(route('team.payment.payfast', [$this->team, $player, $this->event]));
        $this->assertSame($user->id, (int) $player->userId);
        $this->assertDatabaseHas('user_players', ['user_id' => $user->id, 'player_id' => $player->id]);
        $this->assertSame($player->id, (int) $slot->fresh()->player_profile);
    }

    public function test_create_path_reuses_an_existing_profile_and_then_continues_to_payment(): void
    {
        $user = User::factory()->create();
        $existing = Player::factory()->create([
            'name' => 'Existing',
            'surname' => 'Player',
            'dateOfBirth' => '2013-04-05',
            'email' => 'existing@example.test',
        ]);
        $slot = NoProfileTeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'name' => 'Existing',
            'surname' => 'Player',
            'date_of_birth' => '2013-04-05',
            'pay_status' => 0,
        ]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'player_id' => 0, 'pay_status' => 0]);
        $before = Player::count();

        $this->actingAs($user)->post(route('player.store'), [
            'type' => 'noProfile',
            'noProfile' => $slot->id,
            'team' => $this->team->id,
            'event' => $this->event->id,
            'player_name' => ' existing ',
            'player_surname' => 'PLAYER',
            'dob' => '2013-04-05',
            'gender' => 1,
            'email' => 'existing@example.test',
        ])->assertRedirect(route('team.payment.payfast', [$this->team, $existing, $this->event]));

        $this->assertSame($before, Player::count());
        $this->assertSame($existing->id, (int) $slot->fresh()->player_profile);
        $this->assertDatabaseHas('user_players', ['user_id' => $user->id, 'player_id' => $existing->id]);
    }

    public function test_no_profile_search_is_system_wide_paginated_and_does_not_disclose_private_fields(): void
    {
        $user = User::factory()->create();
        $otherOwner = User::factory()->create();
        Player::factory()->count(21)->sequence(fn ($sequence) => [
            'name' => 'Searchable',
            'surname' => 'Player '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT),
            'dateOfBirth' => '2012-02-03',
            'email' => 'private'.$sequence->index.'@example.test',
            'userId' => $otherOwner->id,
        ])->create();

        $firstPage = $this->actingAs($user)->getJson(route('player.search', [
            'q' => 'Searchable',
            'format' => 'select2',
            'page' => 1,
        ]))->assertOk()->assertJsonCount(20, 'results')->assertJsonPath('pagination.more', true);
        $secondPage = $this->actingAs($user)->getJson(route('player.search', [
            'q' => 'Searchable',
            'format' => 'select2',
            'page' => 2,
        ]))->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('pagination.more', false);

        $payload = json_encode([$firstPage->json(), $secondPage->json()]);
        $this->assertStringNotContainsString('dateOfBirth', $payload);
        $this->assertStringNotContainsString('@example.test', $payload);

        $this->actingAs($user)->getJson(route('player.search', [
            'q' => 'private0@example.test',
            'format' => 'select2',
        ]))->assertOk()->assertJsonCount(0, 'results');
    }

    public function test_rejected_create_profile_context_does_not_create_an_orphan_player(): void
    {
        $this->event->update(['signUp' => false]);
        $user = User::factory()->create();
        $slot = NoProfileTeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'name' => 'Blocked',
            'surname' => 'Player',
            'pay_status' => 0,
        ]);
        $before = Player::count();

        $this->actingAs($user)->post(route('player.store'), [
            'type' => 'noProfile',
            'noProfile' => $slot->id,
            'team' => $this->team->id,
            'event' => $this->event->id,
            'player_name' => 'Blocked',
            'player_surname' => 'Player',
            'dob' => '2013-04-05',
            'gender' => 1,
        ])->assertSessionHasErrors('event');

        $this->assertSame($before, Player::count());
        $this->assertNull($slot->fresh()->player_profile);
    }

    public function test_regional_workspace_shows_and_manages_imported_roster_names_and_order(): void
    {
        $teamEventType = DB::table('eventtypes')->insertGetId([
            'name' => 'Imported regional team event',
            'type' => EventType::TEAM,
            'code' => 'imported-regional-team-event',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $region = TeamRegion::create(['region_name' => 'Imported Test Region']);
        $this->event->update(['eventType' => $teamEventType]);
        $this->team->update(['region_id' => $region->id, 'noProfile' => true]);
        $eventRegion = new EventRegion();
        $eventRegion->event_id = $this->event->id;
        $eventRegion->region_id = $region->id;
        $eventRegion->ordering = 1;
        $eventRegion->save();

        $linkedPlayer = Player::factory()->create(['name' => 'Linked', 'surname' => 'Player']);
        $linkedSlot = NoProfileTeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'name' => 'Linked',
            'surname' => 'Player',
            'player_profile' => $linkedPlayer->id,
            'pay_status' => 0,
        ]);
        $unlinkedSlot = NoProfileTeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 2,
            'name' => 'Imported',
            'surname' => 'Name',
            'pay_status' => 0,
        ]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'player_id' => $linkedPlayer->id, 'pay_status' => 0]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 2, 'player_id' => 0, 'pay_status' => 0]);

        $this->actingAs($this->admin)->get(route('backend.team-selection.index', $this->event))
            ->assertOk()
            ->assertSee('2 roster players · 1 linked · 1 unlinked')
            ->assertSee('Imported · Linked')
            ->assertSee('Imported · Unlinked')
            ->assertSee('value="Imported"', false)
            ->assertSee('Player order');

        $this->actingAs($this->admin)->patch(route('backend.team-selection.imported-players.update', [
            $this->event, $eventRegion, $this->team, $unlinkedSlot,
        ]), [
            'name' => 'Corrected',
            'surname' => 'Surname',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('no_profile_team_players', [
            'id' => $unlinkedSlot->id,
            'name' => 'Corrected',
            'surname' => 'Surname',
            'player_profile' => null,
        ]);

        $this->actingAs($this->admin)->post(route('backend.team-selection.imported-players.move', [
            $this->event, $eventRegion, $this->team, $linkedSlot,
        ]), ['direction' => 'down'])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(2, (int) $linkedSlot->fresh()->rank);
        $this->assertSame(1, (int) $unlinkedSlot->fresh()->rank);
        $this->assertDatabaseHas('team_players', [
            'team_id' => $this->team->id,
            'player_id' => $linkedPlayer->id,
            'rank' => 2,
        ]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Team::class,
            'subject_id' => $this->team->id,
            'description' => 'regional manager corrected imported roster name',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Team::class,
            'subject_id' => $this->team->id,
            'description' => 'regional manager reordered imported roster',
        ]);

        $otherTeam = Team::factory()->create([
            'category_event_id' => $this->team->category_event_id,
            'region_id' => $region->id,
            'noProfile' => true,
        ]);
        $otherSlot = NoProfileTeamPlayer::create([
            'team_id' => $otherTeam->id,
            'rank' => 1,
            'name' => 'Other',
            'surname' => 'Team',
            'pay_status' => 0,
        ]);
        $this->actingAs($this->admin)->patch(route('backend.team-selection.imported-players.update', [
            $this->event, $eventRegion, $this->team, $otherSlot,
        ]), ['name' => 'Cross-team', 'surname' => 'Change'])->assertNotFound();
        $this->assertSame('Other', $otherSlot->fresh()->name);

        $this->actingAs(User::factory()->create())->patch(route('backend.team-selection.imported-players.update', [
            $this->event, $eventRegion, $this->team, $unlinkedSlot,
        ]), ['name' => 'Unauthorized', 'surname' => 'Change'])->assertForbidden();
    }

    private function roster(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('external-roster.csv', $contents);
    }
}
