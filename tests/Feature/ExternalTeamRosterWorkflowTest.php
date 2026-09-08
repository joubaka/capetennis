<?php

namespace Tests\Feature;

use App\Domain\Teams\Services\ExternalTeamRosterService;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\NoProfileTeamPlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_user_can_claim_owned_profile_but_cannot_claim_someone_elses_profile(): void
    {
        $user = User::factory()->create();
        $owned = Player::factory()->create(['userId' => $user->id]);
        $unowned = Player::factory()->create(['userId' => User::factory()->create()->id]);
        $slot = NoProfileTeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'name' => $owned->name,
            'surname' => $owned->surname,
            'pay_status' => 0,
        ]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'player_id' => 0, 'pay_status' => 0]);

        $this->from(route('events.show', $this->event))->actingAs($user)->post(route('player.attach'), [
            'player_id' => $unowned->id,
            'team' => $this->team->id,
            'event' => $this->event->id,
            'noProfile' => $slot->id,
        ])->assertRedirect(route('events.show', $this->event))->assertSessionHasErrors('player_id');

        $this->assertNull($slot->fresh()->player_profile);

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

        $this->actingAs($user)->post(route('player.store'), [
            'type' => 'noProfile',
            'noProfile' => $slot->id,
            'team' => $this->team->id,
            'event' => $this->event->id,
            'player_name' => 'New',
            'player_surname' => 'Player',
            'dob' => '2013-04-05',
            'gender' => 1,
        ])->assertRedirect();

        $player = Player::where('name', 'New')->where('surname', 'Player')->firstOrFail();
        $this->assertSame($user->id, (int) $player->userId);
        $this->assertDatabaseHas('user_players', ['user_id' => $user->id, 'player_id' => $player->id]);
        $this->assertSame($player->id, (int) $slot->fresh()->player_profile);
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

    private function roster(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('external-roster.csv', $contents);
    }
}
