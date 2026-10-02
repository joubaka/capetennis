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
use App\Models\TeamPaymentOrder;
use App\Models\TeamPlayer;
use App\Models\TeamRegion;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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

    public function test_confirming_a_profile_does_not_link_it_until_the_profile_review_is_saved(): void
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
            'confirmed_profile' => 1,
        ])->assertRedirect(route('player.claim.review'));

        $this->assertNull($slot->fresh()->player_profile);
        $this->assertDatabaseMissing('user_players', [
            'user_id' => $user->id,
            'player_id' => $owned->id,
        ]);

        $this->actingAs($user)->get(route('player.claim.review'))
            ->assertOk()
            ->assertSee($owned->name)
            ->assertSee('Save Profile and Continue to Payment');

        $this->actingAs($user)->put(route('player.claim.complete'), [
            'dateOfBirth' => '2012-05-17',
            'gender' => 'Male',
            'cellNr' => '',
            'email' => 'updated@example.test',
            'confirmed_details' => 1,
        ])->assertSessionHasErrors('cellNr');

        $this->assertNull($slot->fresh()->player_profile);
        $this->assertDatabaseMissing('user_players', [
            'user_id' => $user->id,
            'player_id' => $owned->id,
        ]);

        $this->actingAs($user)->put(route('player.claim.complete'), [
            'dateOfBirth' => '2012-05-17',
            'gender' => 'Male',
            'cellNr' => '0821234567',
            'email' => 'updated@example.test',
            'confirmed_details' => 1,
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

    public function test_user_can_correct_stale_identity_details_then_link_the_confirmed_profile(): void
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
        ])->assertSessionHasErrors('confirmed_profile');
        $this->assertNull($slot->fresh()->player_profile);

        $this->actingAs($user)->post(route('player.attach'), [
            'player_id' => $player->id,
            'team' => $this->team->id,
            'event' => $this->event->id,
            'noProfile' => $slot->id,
            'confirmed_profile' => 1,
        ])->assertRedirect(route('player.claim.review'));

        $this->assertNull($slot->fresh()->player_profile);

        $this->actingAs($user)->put(route('player.claim.complete'), [
            'dateOfBirth' => '2013-02-03',
            'gender' => 'Female',
            'cellNr' => '083 555 0101',
            'email' => 'corrected@example.test',
            'confirmed_details' => 1,
        ])->assertRedirect(route('team.payment.payfast', [$this->team, $player, $this->event]));

        $this->assertDatabaseHas('user_players', ['user_id' => $user->id, 'player_id' => $player->id]);
        $this->assertSame($player->id, (int) $slot->fresh()->player_profile);
        $this->assertSame('2013-02-03', substr((string) $player->fresh()->dateOfBirth, 0, 10));
        $this->assertSame('corrected@example.test', $player->fresh()->email);
    }

    public function test_any_user_can_link_an_eligible_profile_that_does_not_match_the_roster_identity(): void
    {
        $user = User::factory()->create();
        $existingOwner = User::factory()->create();
        $player = Player::factory()->create([
            'name' => 'Different',
            'surname' => 'Person',
            'userId' => $existingOwner->id,
        ]);
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
            'confirmed_profile' => 1,
        ])->assertRedirect(route('player.claim.review'));

        $this->assertNull($slot->fresh()->player_profile);

        $this->actingAs($user)->put(route('player.claim.complete'), [
            'dateOfBirth' => '2012-05-17',
            'gender' => 'Male',
            'cellNr' => '0821234567',
            'email' => 'different.person@example.test',
            'confirmed_details' => 1,
        ])->assertRedirect(route('team.payment.payfast', [$this->team, $player, $this->event]));

        $this->assertDatabaseHas('no_profile_team_players', [
            'id' => $slot->id,
            'player_profile' => $player->id,
            'claimed_by_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('team_players', [
            'team_id' => $this->team->id,
            'rank' => 1,
            'player_id' => $player->id,
            'pay_status' => 0,
        ]);
        $this->assertDatabaseHas('user_players', [
            'user_id' => $user->id,
            'player_id' => $player->id,
        ]);
    }

    public function test_claim_accepts_equivalent_roster_and_profile_names_with_diacritics(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create([
            'name' => 'Zione',
            'surname' => 'Van Zyl',
            'userId' => $user->id,
        ]);
        $slot = NoProfileTeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'name' => 'Zioné',
            'surname' => 'Van Zyl',
            'pay_status' => 0,
        ]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'player_id' => 0, 'pay_status' => 0]);

        $this->actingAs($user)->post(route('player.attach'), [
            'player_id' => $player->id,
            'team' => $this->team->id,
            'event' => $this->event->id,
            'noProfile' => $slot->id,
            'confirmed_profile' => 1,
        ])->assertRedirect(route('player.claim.review'));

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
            'confirmed_profile' => 1,
        ])->assertSessionHasErrors('event');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ExternalTeamRosterService::class)->assertCanRegister($user, $this->event->fresh(), $this->team, $player);
    }

    public function test_open_team_event_accepts_registration_after_legacy_deadline(): void
    {
        $this->team->update(['noProfile' => false]);
        $this->event->update([
            'start_date' => now()->addDay(),
            'deadline' => 7,
            'signUp' => true,
        ]);
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        TeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'player_id' => $player->id,
            'pay_status' => 0,
        ]);

        $this->assertTrue(app(ExternalTeamRosterService::class)->registrationIsOpen($this->event->fresh()));
        $this->assertSame(
            $player->id,
            app(ExternalTeamRosterService::class)
                ->assertCanRegister($user, $this->event->fresh(), $this->team, $player)
                ->player_id
        );
    }

    public function test_any_user_can_open_team_payment_for_an_eligible_unlinked_player(): void
    {
        $this->team->update(['noProfile' => false]);
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => User::factory()->create()->id]);
        TeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'player_id' => $player->id,
            'pay_status' => 0,
        ]);

        $paymentUrl = route('team.payment.payfast', [
            $this->team,
            $player,
            $this->event,
        ]);
        $this->actingAs($user)->get($paymentUrl)->assertOk()
            ->assertViewIs('frontend.payfast.team_payment')
            ->assertSee('Registration details')
            ->assertSee($player->name.' '.$player->surname)
            ->assertSee('Profile #'.$player->id)
            ->assertSee($this->team->name);

        $this->assertDatabaseHas('team_payment_orders', [
            'user_id' => $user->id,
            'team_id' => $this->team->id,
            'player_id' => $player->id,
            'event_id' => $this->event->id,
        ]);
        $order = TeamPaymentOrder::query()->where('user_id', $user->id)
            ->where('team_id', $this->team->id)->where('player_id', $player->id)->firstOrFail();
        $this->assertNull($order->payfast_handed_off_at);

        $this->post(route('team.payment.payfast.handoff', [
            $this->team, $player, $this->event,
        ]))->assertOk()
            ->assertViewIs('frontend.payfast.team_payment')
            ->assertSee('Redirecting securely to PayFast')
            ->assertSee('name="amount" value="'.number_format((float) $order->payfast_amount_due, 2, '.', '').'"', false)
            ->assertSee('name="custom_int5" value="'.$order->id.'"', false);
        $this->assertNotNull($order->fresh()->payfast_handed_off_at);
        $handedOffAt = $order->fresh()->payfast_handed_off_at->toISOString();
        $this->post(route('team.payment.payfast.handoff', [
            $this->team, $player, $this->event,
        ]))->assertOk();
        $this->assertSame($handedOffAt, $order->fresh()->payfast_handed_off_at->toISOString());
        $this->assertSame(1, DB::table('activity_log')
            ->where('subject_type', TeamPaymentOrder::class)
            ->where('subject_id', $order->id)
            ->where('description', 'team checkout handed off to PayFast')
            ->count());

        $reserved = (float) $order->fresh()->wallet_reserved;
        $due = (float) $order->fresh()->payfast_amount_due;
        $this->travel(31)->minutes();
        $this->post(route('team.payment.payfast.handoff', [
            $this->team, $player, $this->event,
        ]))->assertOk();

        $stillPending = $order->fresh();
        $this->assertSame($handedOffAt, $stillPending->payfast_handed_off_at->toISOString());
        $this->assertSame($reserved, (float) $stillPending->wallet_reserved);
        $this->assertSame($due, (float) $stillPending->payfast_amount_due);
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertSame(0, DB::table('activity_log')
            ->where('subject_type', TeamPaymentOrder::class)
            ->where('subject_id', $order->id)
            ->where('description', 'stale team checkout re-submitted to PayFast')
            ->count());
    }

    public function test_linked_no_profile_player_can_open_payment_when_zero_mirror_is_repaired(): void
    {
        $payer = User::factory()->create();
        $player = Player::factory()->create();
        NoProfileTeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'name' => $player->name,
            'surname' => $player->surname,
            'player_profile' => $player->id,
            'pay_status' => 0,
        ]);
        $mirror = TeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'player_id' => 0,
            'pay_status' => 0,
        ]);

        $this->actingAs($payer)->get(route('team.payment.payfast', [$this->team, $player, $this->event]))
            ->assertOk()
            ->assertViewIs('frontend.payfast.team_payment');

        $this->assertSame($player->id, (int) $mirror->fresh()->player_id);
        $this->assertDatabaseHas('team_payment_orders', [
            'user_id' => $payer->id,
            'team_id' => $this->team->id,
            'player_id' => $player->id,
            'event_id' => $this->event->id,
        ]);
    }

    public function test_linked_no_profile_player_can_open_payment_when_mirror_is_missing(): void
    {
        $payer = User::factory()->create();
        $player = Player::factory()->create();
        NoProfileTeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 2,
            'name' => $player->name,
            'surname' => $player->surname,
            'player_profile' => $player->id,
            'pay_status' => 0,
        ]);

        $this->actingAs($payer)->get(route('team.payment.payfast', [$this->team, $player, $this->event]))
            ->assertOk();

        $this->assertDatabaseHas('team_players', [
            'team_id' => $this->team->id,
            'rank' => 2,
            'player_id' => $player->id,
            'pay_status' => 0,
        ]);
        $this->assertDatabaseCount('team_payment_orders', 1);
    }

    public function test_no_profile_payment_rejects_conflicting_or_duplicate_mirrors_without_order_or_repairs(): void
    {
        $payer = User::factory()->create();
        $player = Player::factory()->create();
        $otherPlayer = Player::factory()->create();
        NoProfileTeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'name' => $player->name,
            'surname' => $player->surname,
            'player_profile' => $player->id,
            'pay_status' => 0,
        ]);
        $conflict = TeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'player_id' => $otherPlayer->id,
            'pay_status' => 0,
        ]);

        $this->actingAs($payer)->from(route('events.show', $this->event))
            ->get(route('team.payment.payfast', [$this->team, $player, $this->event]))
            ->assertRedirect(route('events.show', $this->event))
            ->assertSessionHasErrors('player');
        $this->assertSame($otherPlayer->id, (int) $conflict->fresh()->player_id);
        $this->assertDatabaseCount('team_payment_orders', 0);

        $conflict->update(['player_id' => 0]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'player_id' => 0, 'pay_status' => 0]);

        $this->actingAs($payer)->from(route('events.show', $this->event))
            ->get(route('team.payment.payfast', [$this->team, $player, $this->event]))
            ->assertRedirect(route('events.show', $this->event))
            ->assertSessionHasErrors('player');
        $this->assertSame(2, TeamPlayer::where('team_id', $this->team->id)->where('rank', 1)->where('player_id', 0)->count());
        $this->assertDatabaseCount('team_payment_orders', 0);
    }

    public function test_no_profile_payment_rejects_arbitrary_and_cross_team_players_without_order(): void
    {
        $payer = User::factory()->create();
        $listedElsewhere = Player::factory()->create();
        $arbitrary = Player::factory()->create();
        $otherTeam = Team::factory()->create([
            'category_event_id' => $this->team->category_event_id,
            'published' => true,
            'noProfile' => true,
        ]);
        NoProfileTeamPlayer::create([
            'team_id' => $otherTeam->id,
            'rank' => 1,
            'name' => $listedElsewhere->name,
            'surname' => $listedElsewhere->surname,
            'player_profile' => $listedElsewhere->id,
            'pay_status' => 0,
        ]);

        foreach ([$listedElsewhere, $arbitrary] as $player) {
            $this->actingAs($payer)->from(route('events.show', $this->event))
                ->get(route('team.payment.payfast', [$this->team, $player, $this->event]))
                ->assertRedirect(route('events.show', $this->event))
                ->assertSessionHasErrors('player');
        }

        $this->assertDatabaseCount('team_payment_orders', 0);
        $this->assertDatabaseMissing('team_players', ['team_id' => $this->team->id, 'player_id' => $listedElsewhere->id]);
        $this->assertDatabaseMissing('team_players', ['team_id' => $this->team->id, 'player_id' => $arbitrary->id]);
    }

    public function test_team_payment_trims_legacy_payer_name_before_signing_and_posting(): void
    {
        $this->team->update(['noProfile' => false]);
        $user = User::factory()->create(['name' => 'Wiaan ']);
        $player = Player::factory()->create([
            'name' => 'Nina',
            'surname' => 'Bishop',
            'userId' => $user->id,
        ]);
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
        ]))->assertOk()
            ->assertSee('name="custom_str4" value="Wiaan"', false)
            ->assertDontSee('name="custom_str4" value="Wiaan "', false);
    }

    public function test_team_payment_can_reapply_an_increased_wallet_balance_to_cover_the_full_order(): void
    {
        $this->team->update(['noProfile' => false]);
        $this->event->update(['entryFee' => 490]);
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        TeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'player_id' => $player->id,
            'pay_status' => 0,
        ]);
        $wallet = Wallet::factory()->forUser($user)->create();
        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'amount' => 1259.38,
            'source_type' => 'test_seed',
            'source_id' => 1,
            'meta' => [],
        ]);
        $order = TeamPaymentOrder::create([
            'user_id' => $user->id,
            'team_id' => $this->team->id,
            'player_id' => $player->id,
            'event_id' => $this->event->id,
            'total_amount' => 490,
            'wallet_reserved' => 259.38,
            'payfast_amount_due' => 230.62,
            'wallet_debited' => false,
            'payfast_paid' => false,
            'pay_status' => false,
        ]);

        $paymentRoute = route('team.payment.payfast', [$this->team, $player, $this->event]);

        $this->actingAs($user)->get($paymentRoute)
            ->assertOk()
            ->assertSee('Wallet Applied:</strong> R259.38', false)
            ->assertSee('Use Wallet for Full Payment')
            ->assertSee('name="wallet_applied" value="490.00"', false);

        $this->post(route('team.hybrid.pay'), [
            'custom_int5' => $order->id,
        ])->assertRedirect($paymentRoute);

        $this->assertDatabaseHas('team_payment_orders', [
            'id' => $order->id,
            'wallet_reserved' => 490,
            'payfast_amount_due' => 0,
        ]);

        $this->get($paymentRoute)
            ->assertOk()
            ->assertSee('Wallet Balance After Payment:')
            ->assertSee('R769.38')
            ->assertSee('No additional payment required')
            ->assertSee('Confirm Wallet Payment')
            ->assertDontSee('Pay now with Payfast');
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
        TeamPlayer::create([
            'team_id' => $this->team->id,
            'rank' => 1,
            'player_id' => $player->id,
            'pay_status' => 1,
        ]);

        $this->actingAs($this->admin)->postJson(
            route('backend.team.import.no.profile', [$this->event, $this->team]),
            ['file' => $this->roster("Rank,Name,Surname,Email\n1,Ana,One,ana.parent@example.test\n2,Ben,Two,ben.parent@example.test\n"), 'confirmed' => 1]
        )->assertOk();

        $this->assertSame($player->id, NoProfileTeamPlayer::where('team_id', $this->team->id)->where('rank', 1)->value('player_profile'));
        $this->assertSame('ana.parent@example.test', NoProfileTeamPlayer::where('team_id', $this->team->id)->where('rank', 1)->value('email'));
        $this->assertDatabaseHas('team_players', [
            'team_id' => $this->team->id,
            'rank' => 1,
            'player_id' => $player->id,
            'pay_status' => 1,
        ]);
    }

    public function test_contact_reimport_only_fills_blank_email_and_requires_review_for_anomalies(): void
    {
        $teamEventType = DB::table('eventtypes')->insertGetId([
            'name' => 'Contact enrichment event', 'type' => EventType::TEAM, 'code' => 'contact-enrichment-event',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $region = TeamRegion::create(['region_name' => 'Contact Test Region']);
        $this->event->update(['eventType' => $teamEventType]);
        $this->team->update(['region_id' => $region->id, 'noProfile' => true]);
        $eventRegion = new EventRegion();
        $eventRegion->event_id = $this->event->id;
        $eventRegion->region_id = $region->id;
        $eventRegion->ordering = 1;
        $eventRegion->save();
        $safe = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'name' => 'Ana', 'surname' => 'One', 'email' => null, 'pay_status' => 0]);
        $conflict = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 2, 'name' => 'Ben', 'surname' => 'Two', 'email' => 'keep@example.test', 'pay_status' => 1]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'player_id' => 0, 'pay_status' => 0]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 2, 'player_id' => 0, 'pay_status' => 1]);
        $file = fn () => $this->roster("Category,Rank,Name,Surname,Email\nBoys U10,1,Ana,One,ana@example.test\nBoys U10,2,Ben,Two,different@example.test\n");

        $preview = $this->actingAs($this->admin)->postJson(route('backend.team-selection.imported-contacts.enrich', [$this->event, $eventRegion]), [
            'file' => $file(), 'expected_players' => 2,
        ])->assertOk()->assertJsonCount(1, 'updates')->assertJsonCount(1, 'issues');
        $this->assertNull($safe->fresh()->email);

        $payload = [
            'file' => $file(), 'expected_players' => 2, 'confirmed' => 1,
            'preview_fingerprint' => $preview->json('preview_fingerprint'),
        ];
        $this->actingAs($this->admin)->postJson(route('backend.team-selection.imported-contacts.enrich', [$this->event, $eventRegion]), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('confirm_anomalies');
        $this->actingAs($this->admin)->postJson(route('backend.team-selection.imported-contacts.enrich', [$this->event, $eventRegion]), $payload + ['confirm_anomalies' => 1])
            ->assertOk()->assertJsonPath('requires_confirmation', false);

        $this->assertSame('ana@example.test', $safe->fresh()->email);
        $this->assertSame('keep@example.test', $conflict->fresh()->email);
        $this->assertDatabaseHas('team_players', ['team_id' => $this->team->id, 'rank' => 2, 'pay_status' => 1]);
        $this->assertSame('Ben', $conflict->fresh()->name);
        $this->assertDatabaseCount('no_profile_team_players', 2);
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
        $this->assertStringNotContainsString('private0@example.test', $payload);
        $this->assertStringContainsString('p***@example.test', $payload);

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
        Queue::fake();
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

        $linkedPlayer = Player::factory()->create([
            'name' => 'Linked',
            'surname' => 'Player',
            'email' => 'linked.player@example.test',
            'cellNr' => '0821234567',
        ]);
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
            'email' => 'imported.player@example.test',
            'cell_nr' => '0837654321',
            'pay_status' => 0,
        ]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'player_id' => $linkedPlayer->id, 'pay_status' => 0]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 2, 'player_id' => 0, 'pay_status' => 0]);

        $this->actingAs($this->admin)->get(route('backend.team-selection.index', $this->event))
            ->assertOk()
            ->assertSee('2 roster players · 1 linked · 1 unlinked')
            ->assertSee('Imported · Linked')
            ->assertSee('Imported · Unlinked')
            ->assertSee('linked.player@example.test')
            ->assertSee('0821234567')
            ->assertSee('imported.player@example.test')
            ->assertSee('0837654321')
            ->assertSee('value="Imported"', false)
            ->assertSee('Player order')
            ->assertSee('Email unlinked / not registered')
            ->assertSee('Email linked, not registered / paid')
            ->assertSee('Email all linked players')
            ->assertSee('class="row g-1 align-items-center imported-name-form"', false)
            ->assertSee('class="d-flex align-items-center gap-1 mt-2 imported-email-form"', false)
            ->assertDontSee('id="imported-email-'.$linkedSlot->id.'"', false)
            ->assertSee('id="imported-email-'.$unlinkedSlot->id.'"', false)
            ->assertSee('Linked profile email')
            ->assertSee('No-profile email')
            ->assertSee('class="imported-roster-players"', false)
            ->assertSee('class="imported-roster-sortable"', false)
            ->assertSee('draggable="true"', false);

        $this->actingAs($this->admin)->post(route('backend.team-selection.roster-email.send', [$this->event, $eventRegion]), [
            'target_type' => 'unlinked_imported',
            'subject' => 'Complete your Cape Tennis registration',
            'message' => 'Please link your account, register and complete payment.',
            'confirm_recipients' => 1,
            'recipient_hash' => hash('sha256', collect(['imported.player@example.test'])->toJson()),
        ])->assertRedirect(route('backend.event-communications.index', $this->event))->assertSessionHas('success');
        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('bulk_email_logs', [
            'mail_type' => 'region_email',
            'related_type' => EventRegion::class,
            'related_id' => $eventRegion->id,
            'recipient_email' => 'imported.player@example.test',
            'status' => 'queued',
        ]);
        $this->assertDatabaseMissing('bulk_email_logs', [
            'related_id' => $eventRegion->id,
            'recipient_email' => 'linked.player@example.test',
        ]);

        $this->actingAs($this->admin)->patchJson(route('backend.team-selection.imported-players.email.update', [
            $this->event, $eventRegion, $this->team, $linkedSlot,
        ]), ['email' => 'linked.fallback@example.test'])
            ->assertOk()
            ->assertJsonPath('imported_email', 'linked.fallback@example.test')
            ->assertJsonPath('effective_email', 'linked.player@example.test')
            ->assertJsonPath('effective_email_source', 'Linked profile email');
        $this->assertSame('linked.fallback@example.test', $linkedSlot->fresh()->email);
        $this->actingAs($this->admin)->post(route('backend.team-selection.roster-email.send', [$this->event, $eventRegion]), [
            'target_type' => 'linked_unpaid',
            'subject' => 'Complete payment',
            'message' => 'Please complete registration and payment.',
            'confirm_recipients' => 1,
            'recipient_hash' => hash('sha256', collect(['linked.player@example.test'])->toJson()),
        ])->assertRedirect(route('backend.event-communications.index', $this->event))->assertSessionHas('success');
        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('bulk_email_logs', [
            'related_id' => $eventRegion->id,
            'recipient_email' => 'linked.player@example.test',
            'status' => 'queued',
        ]);

        $this->actingAs($this->admin)->patchJson(route('backend.team-selection.imported-players.email.update', [
            $this->event, $eventRegion, $this->team, $unlinkedSlot,
        ]), ['email' => 'UPDATED.ROSTER@example.test'])
            ->assertOk()
            ->assertJsonPath('imported_email', 'updated.roster@example.test')
            ->assertJsonPath('effective_email_source', 'No-profile email');

        $this->actingAs($this->admin)->patchJson(route('backend.team-selection.imported-players.update', [
            $this->event, $eventRegion, $this->team, $unlinkedSlot,
        ]), [
            'name' => 'Corrected',
            'surname' => 'Surname',
        ])->assertOk()->assertJsonPath('player.name', 'Corrected');
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

        $this->actingAs($this->admin)->putJson(route('backend.team-selection.imported-players.reorder', [
            $this->event, $eventRegion, $this->team,
        ]), ['slot_ids' => [$linkedSlot->id, $unlinkedSlot->id]])
            ->assertOk()->assertJsonPath('message', 'The imported roster order was updated.');
        $this->assertSame(1, (int) $linkedSlot->fresh()->rank);
        $this->assertSame(2, (int) $unlinkedSlot->fresh()->rank);
        $this->assertDatabaseHas('team_players', [
            'team_id' => $this->team->id,
            'player_id' => $linkedPlayer->id,
            'rank' => 1,
            'pay_status' => 0,
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

    public function test_admin_relinks_imported_profile_atomically_without_ownership_or_communications(): void
    {
        Queue::fake();
        [$region, $slot, $old, $replacement, $url] = $this->relinkScenario();
        $owners = DB::table('user_players')->count();
        $this->actingAs($this->admin)->getJson(route('backend.team-selection.imported-players.profiles.search', [$this->event, $region, $this->team, $slot]).'?q='.urlencode($replacement->name))
            ->assertOk()->assertJsonFragment(['id' => $replacement->id]);
        $payload = ['player_id' => $replacement->id, 'expected_player_id' => $old->id, 'expected_rank' => 1, 'confirm_replacement' => 1];
        $this->actingAs($this->admin)->patch($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('no_profile_team_players', ['id' => $slot->id, 'player_profile' => $replacement->id, 'name' => 'Imported', 'claimed_by_user_id' => null, 'claimed_at' => null]);
        $this->assertDatabaseHas('team_players', ['team_id' => $this->team->id, 'rank' => 1, 'player_id' => $replacement->id, 'pay_status' => 0]);
        $this->assertDatabaseCount('team_players', 1);
        $this->assertSame($owners, DB::table('user_players')->count());
        $this->assertDatabaseHas('activity_log', ['description' => 'administrator replaced imported roster profile link']);
        $this->actingAs($this->admin)->patchJson($url, $payload)->assertUnprocessable();
        try {
            app(\App\Domain\Payments\Services\TeamPaymentService::class)->ensureOrder($this->admin, $this->team, $old, $this->event, 100);
            $this->fail('Stale checkout must not create an order.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('player', $exception->errors());
        }
        $this->assertDatabaseCount('team_payment_orders', 0);
        Queue::assertNothingPushed();
    }

    public function test_relink_rejects_unauthorized_cross_team_stale_duplicate_and_checkout_state(): void
    {
        [$region, $slot, $old, $replacement, $url] = $this->relinkScenario();
        $payload = ['player_id' => $replacement->id, 'expected_player_id' => $old->id, 'expected_rank' => 1, 'confirm_replacement' => 1];
        $this->actingAs(User::factory()->create())->patchJson($url, $payload)->assertForbidden();
        $manager = User::factory()->create();
        \App\Models\EventRegionManager::create(['event_id' => $this->event->id, 'event_region_id' => $region->id, 'region_id' => $region->region_id, 'user_id' => $manager->id, 'assigned_by' => $this->admin->id]);
        $this->actingAs($manager)->patchJson($url, $payload)->assertForbidden();
        $this->actingAs($manager)->getJson(route('backend.team-selection.imported-players.profiles.search', [$this->event, $region, $this->team, $slot]).'?q=Player')->assertForbidden();
        $otherEvent = Event::factory()->create(['eventType' => $this->event->eventType]);
        $this->actingAs($this->admin)->patchJson(route('backend.team-selection.imported-players.profile.update', [$otherEvent, $region, $this->team, $slot]), $payload)->assertNotFound();
        $this->actingAs($this->admin)->patchJson($url, array_replace($payload, ['expected_rank' => 2]))->assertUnprocessable();
        $this->actingAs($this->admin)->patchJson($url, array_replace($payload, ['confirm_replacement' => 0]))->assertUnprocessable();
        $duplicate = TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 2, 'player_id' => $replacement->id, 'pay_status' => 0]);
        $this->actingAs($this->admin)->patchJson($url, $payload)->assertUnprocessable();
        $duplicate->delete();
        $otherTeam = Team::factory()->create(['category_event_id' => $this->team->category_event_id, 'region_id' => $region->region_id]);
        $otherLinked = TeamPlayer::create(['team_id' => $otherTeam->id, 'rank' => 1, 'player_id' => $replacement->id, 'pay_status' => 0]);
        $this->actingAs($this->admin)->patchJson($url, $payload)->assertUnprocessable();
        $otherLinked->delete();
        TeamPaymentOrder::create(['team_id' => $this->team->id, 'event_id' => $this->event->id, 'player_id' => $old->id, 'user_id' => $this->admin->id, 'total_amount' => 100, 'wallet_reserved' => 50, 'payfast_amount_due' => 50, 'pay_status' => false]);
        $this->actingAs($this->admin)->patchJson($url, $payload)->assertUnprocessable();
        $this->assertDatabaseHas('no_profile_team_players', ['id' => $slot->id, 'player_profile' => $old->id]);
        $this->assertDatabaseHas('team_payment_orders', ['player_id' => $old->id, 'total_amount' => 100, 'wallet_reserved' => 50]);
        $this->assertDatabaseCount('team_payment_orders', 1);
    }

    public function test_relink_preserves_paid_audit_and_blocks_imported_fixture_assignment(): void
    {
        [$region, $slot, $old, $replacement, $url] = $this->relinkScenario();
        $payload = ['player_id' => $replacement->id, 'expected_player_id' => $old->id, 'expected_rank' => 1, 'confirm_replacement' => 1];
        $slot->update(['pay_status' => 1]);
        $this->actingAs($this->admin)->patchJson($url, $payload)->assertUnprocessable();
        $slot->update(['pay_status' => 0]);
        $draw = \App\Models\Draw::factory()->create(['event_id' => $this->event->id, 'category_event_id' => $this->team->category_event_id]);
        $fixture = \App\Models\TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'match_nr' => 1, 'round_nr' => 1]);
        \App\Models\TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1, 'team1_no_profile_id' => $slot->id]);
        $this->actingAs($this->admin)->patchJson($url, $payload)->assertUnprocessable();
        $this->assertSame((int) $old->id, (int) $slot->fresh()->player_profile);
        $this->assertDatabaseCount('team_fixture_players', 1);
    }

    public function test_relink_rejects_ineligible_player_without_changing_either_roster_record(): void
    {
        [$region, $slot, $old, $replacement, $url] = $this->relinkScenario();
        $this->mock(\App\Services\PlayerEligibilityService::class)->shouldReceive('assertEligible')->once()->andThrow(new \RuntimeException('Player is not eligible for this event.'));
        $this->actingAs($this->admin)->patchJson($url, ['player_id' => $replacement->id, 'expected_player_id' => $old->id, 'expected_rank' => 1, 'confirm_replacement' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('player_id');
        $this->assertDatabaseHas('no_profile_team_players', ['id' => $slot->id, 'player_profile' => $old->id]);
        $this->assertDatabaseHas('team_players', ['team_id' => $this->team->id, 'player_id' => $old->id]);
    }

    public function test_clothing_checkout_rechecks_profile_after_relink_during_validation(): void
    {
        [$eventRegion, $slot, $old, $replacement] = $this->relinkScenario();
        $region = TeamRegion::findOrFail($eventRegion->region_id);
        $region->forceFill(['clothing_admin' => true, 'clothing_order' => true])->save();
        $relinked = false;
        DB::listen(function (\Illuminate\Database\Events\QueryExecuted $query) use (&$relinked, $slot, $old, $replacement): void {
            if (! $relinked && str_starts_with($query->sql, 'select exists') && str_contains($query->sql, '`team_players`')) {
                $relinked = true;
                app(\App\Services\TeamSelection\ImportedTeamRosterService::class)->relink($this->event, $slot, $replacement->id, $old->id, 1, $this->admin);
            }
        });
        try {
            app(\App\Services\Clothing\ClothingOrderService::class)->create($this->admin, $this->event, $region, $this->team, $old, [1 => ['size' => 1, 'qty' => 1]], (string) \Illuminate\Support\Str::uuid());
            $this->fail('Stale clothing checkout must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('player_id', $exception->errors());
        }
        $this->assertTrue($relinked);
        $this->assertSame((int) $replacement->id, (int) $slot->fresh()->player_profile);
        $this->assertDatabaseCount('clothing_orders', 0);
    }

    public function test_relink_safely_creates_a_missing_unpaid_roster_mirror(): void
    {
        [$region, $slot, $old, $replacement, $url] = $this->relinkScenario();
        TeamPlayer::query()->where('team_id', $this->team->id)->delete();
        $this->actingAs($this->admin)->patch($url, ['player_id' => $replacement->id, 'expected_player_id' => $old->id, 'expected_rank' => 1, 'confirm_replacement' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('team_players', ['team_id' => $this->team->id, 'rank' => 1, 'player_id' => $replacement->id, 'pay_status' => 0]);
        $this->assertDatabaseCount('team_players', 1);
        $this->assertSame((int) $replacement->id, (int) $slot->fresh()->player_profile);
        $audit = DB::table('activity_log')->where('description', 'administrator replaced imported roster profile link')->first();
        $this->assertSame('missing_mirror', json_decode($audit->properties, true)['mirror_repair']);
        $order = app(\App\Domain\Payments\Services\TeamPaymentService::class)->ensureOrder($this->admin, $this->team, $replacement, $this->event, 100);
        $this->assertSame(100.0, $order->total_amount);
        $this->assertDatabaseCount('team_payment_orders', 1);
    }

    public function test_relink_safely_reuses_an_empty_unpaid_roster_mirror(): void
    {
        [$region, $slot, $old, $replacement, $url] = $this->relinkScenario();
        $mirror = TeamPlayer::query()->where('team_id', $this->team->id)->firstOrFail();
        $mirror->update(['player_id' => 0, 'pay_status' => 1]);
        $this->actingAs($this->admin)->patchJson($url, ['player_id' => $replacement->id, 'expected_player_id' => $old->id, 'expected_rank' => 1, 'confirm_replacement' => 1])->assertUnprocessable();
        $this->assertDatabaseHas('team_players', ['id' => $mirror->id, 'player_id' => 0, 'pay_status' => 1]);
        $mirror->update(['pay_status' => 0]);
        $this->actingAs($this->admin)->patch($url, ['player_id' => $replacement->id, 'expected_player_id' => $old->id, 'expected_rank' => 1, 'confirm_replacement' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('team_players', ['id' => $mirror->id, 'rank' => 1, 'player_id' => $replacement->id, 'pay_status' => 0]);
        $this->assertDatabaseCount('team_players', 1);
        $this->assertSame((int) $replacement->id, (int) $slot->fresh()->player_profile);
        $audit = DB::table('activity_log')->where('description', 'administrator replaced imported roster profile link')->first();
        $this->assertSame('empty_mirror', json_decode($audit->properties, true)['mirror_repair']);
    }

    public function test_missing_mirror_is_not_created_when_payment_history_blocks_relink(): void
    {
        [$region, $slot, $old, $replacement, $url] = $this->relinkScenario();
        TeamPlayer::query()->where('team_id', $this->team->id)->delete();
        TeamPaymentOrder::create(['team_id' => $this->team->id, 'event_id' => $this->event->id, 'player_id' => $old->id, 'user_id' => $this->admin->id, 'total_amount' => 100, 'wallet_reserved' => 0, 'payfast_amount_due' => 0, 'pay_status' => true]);
        $this->actingAs($this->admin)->patchJson($url, ['player_id' => $replacement->id, 'expected_player_id' => $old->id, 'expected_rank' => 1, 'confirm_replacement' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('team_players', 0);
        $this->assertDatabaseHas('team_payment_orders', ['player_id' => $old->id, 'pay_status' => true, 'total_amount' => 100]);
        $this->assertSame((int) $old->id, (int) $slot->fresh()->player_profile);
    }

    public function test_relink_rejects_conflicting_positive_and_wrong_rank_mirrors(): void
    {
        [$region, $slot, $old, $replacement, $url] = $this->relinkScenario();
        $mirror = TeamPlayer::query()->where('team_id', $this->team->id)->firstOrFail();
        $payload = ['player_id' => $replacement->id, 'expected_player_id' => $old->id, 'expected_rank' => 1, 'confirm_replacement' => 1];
        $other = Player::factory()->create();
        $mirror->update(['player_id' => $other->id]);
        $this->actingAs($this->admin)->patchJson($url, $payload)->assertUnprocessable();
        $mirror->update(['player_id' => $old->id, 'rank' => 2]);
        $this->actingAs($this->admin)->patchJson($url, $payload)->assertUnprocessable();
        $this->assertSame((int) $old->id, (int) $slot->fresh()->player_profile);
        $this->assertDatabaseCount('team_players', 1);
        $this->assertDatabaseHas('team_players', ['id' => $mirror->id, 'rank' => 2, 'player_id' => $old->id]);
        $mirror->update(['rank' => 1]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'player_id' => 0, 'pay_status' => 0]);
        $this->actingAs($this->admin)->patchJson($url, $payload)->assertUnprocessable();
        $this->assertDatabaseCount('team_players', 2);
        $this->assertSame((int) $old->id, (int) $slot->fresh()->player_profile);
    }

    public function test_relink_aligns_imported_profile_after_entries_replaced_the_same_mirror(): void
    {
        Queue::fake();
        \Illuminate\Support\Facades\Mail::fake();
        [$region, $slot, $old, $replacement, $url] = $this->relinkScenario();
        $mirror = TeamPlayer::query()->where('team_id', $this->team->id)->firstOrFail();
        // Entries changes only this mirror. Its null unpaid flag is represented
        // as zero because the isolated test schema requires a non-null integer.
        $mirror->update(['player_id' => $replacement->id, 'pay_status' => 0]);
        $mirrorBefore = $mirror->fresh()->getAttributes();
        $owners = DB::table('user_players')->count();
        $this->assertSame((int) $old->id, (int) $slot->fresh()->player_profile);
        $payload = ['player_id' => $replacement->id, 'expected_player_id' => $old->id, 'expected_rank' => 1, 'confirm_replacement' => 1];
        $this->actingAs($this->admin)->patch($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame((int) $replacement->id, (int) $slot->fresh()->player_profile);
        $this->assertSame($mirrorBefore, $mirror->fresh()->getAttributes());
        $this->assertDatabaseCount('team_players', 1);
        $this->assertSame($owners, DB::table('user_players')->count());
        $audit = DB::table('activity_log')->where('description', 'administrator replaced imported roster profile link')->first();
        $this->assertSame('aligned_existing_replacement', json_decode($audit->properties, true)['mirror_repair']);
        $this->actingAs($this->admin)->patchJson($url, $payload)->assertUnprocessable();
        Queue::assertNothingPushed();
        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }

    public function test_existing_replacement_mirror_alignment_still_rejects_duplicates_and_paid_history(): void
    {
        [$region, $slot, $old, $replacement, $url] = $this->relinkScenario();
        $mirror = TeamPlayer::query()->where('team_id', $this->team->id)->firstOrFail();
        $mirror->update(['player_id' => $replacement->id]);
        $payload = ['player_id' => $replacement->id, 'expected_player_id' => $old->id, 'expected_rank' => 1, 'confirm_replacement' => 1];
        $otherTeam = Team::factory()->create(['category_event_id' => $this->team->category_event_id]);
        $duplicate = TeamPlayer::create(['team_id' => $otherTeam->id, 'rank' => 1, 'player_id' => $replacement->id, 'pay_status' => 0]);
        $this->actingAs($this->admin)->patchJson($url, $payload)->assertUnprocessable();
        $duplicate->delete();
        $importedDuplicate = NoProfileTeamPlayer::create(['team_id' => $otherTeam->id, 'rank' => 1, 'name' => 'Another', 'surname' => 'Roster', 'player_profile' => $replacement->id, 'pay_status' => 0]);
        $this->actingAs($this->admin)->patchJson($url, $payload)->assertUnprocessable();
        $importedDuplicate->delete();
        $mirror->update(['pay_status' => 1]);
        $this->actingAs($this->admin)->patchJson($url, $payload)->assertUnprocessable();
        $mirror->update(['pay_status' => 0]);
        foreach ([$old, $replacement] as $historyPlayer) {
            $order = TeamPaymentOrder::create(['team_id' => $this->team->id, 'event_id' => $this->event->id, 'player_id' => $historyPlayer->id, 'user_id' => $this->admin->id, 'total_amount' => 100, 'wallet_reserved' => 0, 'payfast_amount_due' => 0, 'pay_status' => true]);
            $this->actingAs($this->admin)->patchJson($url, $payload)->assertUnprocessable();
            $this->assertDatabaseHas('team_payment_orders', ['id' => $order->id, 'player_id' => $historyPlayer->id, 'pay_status' => true, 'total_amount' => 100]);
            $order->delete();
        }
        $this->assertSame((int) $old->id, (int) $slot->fresh()->player_profile);
        $this->assertDatabaseHas('team_players', ['id' => $mirror->id, 'rank' => 1, 'player_id' => $replacement->id, 'pay_status' => 0]);
    }

    public function test_alignment_closes_only_stale_unpaid_checkout_and_preserves_refunded_audit(): void
    {
        Queue::fake();
        \Illuminate\Support\Facades\Mail::fake();
        [$slot, $old, $replacement, $mirror, $retired, $active, $url] = $this->alignmentCheckoutScenario();
        $retiredBefore = $retired->fresh()->getAttributes();
        $mirrorBefore = $mirror->fresh()->getAttributes();
        $moneyBefore = $active->fresh()->only(['total_amount', 'wallet_reserved', 'payfast_amount_due', 'pay_status', 'payfast_paid', 'wallet_debited']);
        $owners = DB::table('user_players')->count();
        $walletRows = WalletTransaction::count();
        $payload = ['player_id' => $replacement->id, 'expected_player_id' => $old->id, 'expected_rank' => 1, 'confirm_replacement' => 1];
        $this->actingAs($this->admin)->patch($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame((int) $replacement->id, (int) $slot->fresh()->player_profile);
        $this->assertSame($retiredBefore, $retired->fresh()->getAttributes());
        $this->assertSame($mirrorBefore, $mirror->fresh()->getAttributes());
        $this->assertSame($moneyBefore, $active->fresh()->only(array_keys($moneyBefore)));
        $this->assertNotNull($active->fresh()->withdrawn_at);
        $this->assertSame((int) $this->admin->id, (int) $active->fresh()->withdrawn_by);
        $this->assertSame($walletRows, WalletTransaction::count());
        $this->assertSame($owners, DB::table('user_players')->count());
        $audit = DB::table('activity_log')->where('description', 'administrator replaced imported roster profile link')->first();
        $this->assertSame([$active->id], json_decode($audit->properties, true)['closed_stale_order_ids']);
        $payments = app(\App\Domain\Payments\Services\TeamPaymentService::class);
        try {
            $payments->recordPayfastHandoff($active, User::findOrFail($active->user_id), 100);
            $this->fail('Closed old checkout must not be handed off.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }
        try {
            $payments->finalizePayment($active, ['payment_method' => 'payfast', 'payfast_amount_received' => 100, 'pf_payment_id' => 'must-not-settle']);
            $this->fail('Closed old checkout must not finalize.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('cannot be paid', $exception->getMessage());
        }
        $this->assertDatabaseCount('team_payment_orders', 2);
        Queue::assertNothingPushed();
        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }

    public function test_alignment_closes_wholly_unpaid_checkout_with_full_server_balance_due(): void
    {
        [$slot, $old, $replacement, $mirror, $retired, $active, $url] = $this->alignmentCheckoutScenario();
        $active->update(['payfast_amount_due' => 100]);
        $retiredBefore = $retired->fresh()->getAttributes();
        $walletRows = WalletTransaction::count();
        $this->actingAs($this->admin)->patch($url, ['player_id' => $replacement->id, 'expected_player_id' => $old->id, 'expected_rank' => 1, 'confirm_replacement' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($active->fresh()->withdrawn_at);
        $this->assertSame(100.0, $active->fresh()->total_amount);
        $this->assertSame(0.0, $active->fresh()->payfast_amount_due);
        $this->assertSame(0.0, $active->fresh()->wallet_reserved);
        $this->assertFalse($active->fresh()->pay_status);
        $this->assertFalse($active->fresh()->wallet_debited);
        $this->assertSame($walletRows, WalletTransaction::count());
        $this->assertSame($retiredBefore, $retired->fresh()->getAttributes());
    }

    public function test_alignment_rejects_checkout_evidence_and_late_guards_before_closure(): void
    {
        [$slot, $old, $replacement, $mirror, $retired, $active, $url] = $this->alignmentCheckoutScenario();
        $payload = ['player_id' => $replacement->id, 'expected_player_id' => $old->id, 'expected_rank' => 1, 'confirm_replacement' => 1];
        foreach ([['wallet_reserved' => 10], ['payfast_handed_off_at' => now()], ['payfast_pf_payment_id' => 'provider-evidence'], ['payfast_amount_due' => 50]] as $evidence) {
            $active->update($evidence);
            $this->actingAs($this->admin)->patchJson($url, $payload)->assertUnprocessable();
            $this->assertNull($active->fresh()->withdrawn_at);
            $active->update(['wallet_reserved' => 0, 'payfast_handed_off_at' => null, 'payfast_pf_payment_id' => null, 'payfast_amount_due' => 0]);
        }
        $draw = \App\Models\Draw::factory()->create(['event_id' => $this->event->id, 'category_event_id' => $this->team->category_event_id]);
        $fixture = \App\Models\TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'match_nr' => 1, 'round_nr' => 1]);
        \App\Models\TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1, 'team1_no_profile_id' => $slot->id]);
        $this->actingAs($this->admin)->patchJson($url, $payload)->assertUnprocessable();
        $this->assertNull($active->fresh()->withdrawn_at);
        $this->assertSame((int) $old->id, (int) $slot->fresh()->player_profile);
    }

    public function test_failed_alignment_rolls_back_stale_checkout_closure(): void
    {
        [$slot, $old, $replacement, $mirror, $retired, $active] = $this->alignmentCheckoutScenario();
        NoProfileTeamPlayer::updating(function (NoProfileTeamPlayer $changing) use ($slot): void {
            if ((int) $changing->id === (int) $slot->id && $changing->isDirty('player_profile')) {
                throw new \RuntimeException('Simulated imported-link write failure.');
            }
        });
        try {
            app(\App\Services\TeamSelection\ImportedTeamRosterService::class)->relink($this->event, $slot, $replacement->id, $old->id, 1, $this->admin);
            $this->fail('The simulated failure must roll back the whole transaction.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated imported-link write failure.', $exception->getMessage());
        }
        $this->assertNull($active->fresh()->withdrawn_at);
        $this->assertNull($active->fresh()->withdrawn_by);
        $this->assertSame((int) $old->id, (int) $slot->fresh()->player_profile);
        $this->assertDatabaseMissing('activity_log', ['description' => 'administrator replaced imported roster profile link']);
    }

    private function alignmentCheckoutScenario(): array
    {
        [$region, $slot, $old, $replacement, $url] = $this->relinkScenario();
        $mirror = TeamPlayer::query()->where('team_id', $this->team->id)->firstOrFail();
        $mirror->update(['player_id' => $replacement->id]);
        $retired = TeamPaymentOrder::create(['team_id' => $this->team->id, 'event_id' => $this->event->id, 'player_id' => $old->id, 'user_id' => User::factory()->create()->id,
            'total_amount' => 100, 'wallet_reserved' => 0, 'payfast_amount_due' => 100, 'pay_status' => true, 'payfast_paid' => true,
            'withdrawn_at' => now(), 'refund_status' => 'completed', 'refunded_at' => now(), 'refund_gross' => 100, 'refund_fee' => 5, 'refund_net' => 95]);
        $active = TeamPaymentOrder::create(['team_id' => $this->team->id, 'event_id' => $this->event->id, 'player_id' => $old->id, 'user_id' => User::factory()->create()->id,
            'total_amount' => 100, 'wallet_reserved' => 0, 'payfast_amount_due' => 0, 'pay_status' => false, 'payfast_paid' => false, 'wallet_debited' => false]);

        return [$slot, $old, $replacement, $mirror, $retired, $active, $url];
    }

    private function relinkScenario(): array
    {
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Relink team event', 'type' => EventType::TEAM, 'code' => 'relink-team-event', 'created_at' => now(), 'updated_at' => now()]);
        $this->event->update(['eventType' => $type]);
        $region = TeamRegion::create(['region_name' => 'Relink region']);
        $this->team->update(['region_id' => $region->id]);
        $eventRegion = new EventRegion();
        $eventRegion->forceFill(['event_id' => $this->event->id, 'region_id' => $region->id, 'ordering' => 1])->save();
        $old = Player::factory()->create();
        $replacement = Player::factory()->create();
        $slot = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'name' => 'Imported', 'surname' => 'Name', 'player_profile' => $old->id, 'claimed_by_user_id' => $this->admin->id, 'claimed_at' => now(), 'pay_status' => 0]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'player_id' => $old->id, 'pay_status' => 0]);

        return [$eventRegion, $slot, $old, $replacement, route('backend.team-selection.imported-players.profile.update', [$this->event, $eventRegion, $this->team, $slot])];
    }

    private function roster(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('external-roster.csv', $contents);
    }
}
