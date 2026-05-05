<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Player;
use App\Models\SiteSetting;
use App\Models\Team;
use App\Models\TeamPaymentOrder;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature tests for TeamPlayerWithdrawController.
 *
 * Covers:
 *   POST /team/{team}/player/{player}/withdraw/{event}        → team.player.withdraw
 *   GET  /team/{team}/player/{player}/{event}/refund/choose   → team.player.refund.choose
 *   POST /team/{team}/player/{player}/{event}/refund/request  → team.player.refund.request
 */
class TeamPlayerWithdrawTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Create a user, player and link them via user_players pivot.
     * Returns ['user' => User, 'player' => Player].
     */
    private function ownerWithPlayer(): array
    {
        $user   = User::factory()->create();
        $player = Player::factory()->create();
        // Link the player to the user via the user_players pivot
        \DB::table('user_players')->insert(['user_id' => $user->id, 'player_id' => $player->id]);

        return compact('user', 'player');
    }

    /**
     * Create a TeamPlayer slot for the given team + player.
     */
    private function makeTeamPlayer(Team $team, Player $player, int $payStatus = 0): TeamPlayer
    {
        return TeamPlayer::create([
            'team_id'    => $team->id,
            'player_id'  => $player->id,
            'rank'       => 1,
            'pay_status' => $payStatus,
        ]);
    }

    /**
     * Create an Event with withdrawal_deadline in the future (refund allowed).
     */
    private function eventWithOpenDeadline(): Event
    {
        return Event::factory()->create([
            'withdrawal_deadline' => now()->addDays(30),
            'start_date'          => now()->addDays(60)->format('Y-m-d'),
            'deadline'            => 14,
        ]);
    }

    /**
     * Create an Event with withdrawal_deadline in the past (refund blocked).
     */
    private function eventWithClosedDeadline(): Event
    {
        return Event::factory()->create([
            'withdrawal_deadline' => now()->subDays(1),
            'start_date'          => now()->addDays(10)->format('Y-m-d'),
            'deadline'            => 14,
        ]);
    }

    // -----------------------------------------------------------------------
    // withdraw — auth guard
    // -----------------------------------------------------------------------

    public function test_guest_cannot_withdraw_team_player(): void
    {
        $team   = Team::factory()->create();
        $player = Player::factory()->create();
        $event  = $this->eventWithOpenDeadline();

        $response = $this->post(route('team.player.withdraw', [$team, $player, $event->id]));

        $response->assertRedirect(route('login'));
    }

    // -----------------------------------------------------------------------
    // withdraw — global withdrawal switch
    // -----------------------------------------------------------------------

    public function test_withdraw_blocked_when_global_withdrawal_disabled(): void
    {
        SiteSetting::set('withdrawal_allowed', '0');
        Cache::forget('site_setting.withdrawal_allowed');

        ['user' => $user, 'player' => $player] = $this->ownerWithPlayer();
        $team  = Team::factory()->create();
        $event = $this->eventWithOpenDeadline();
        $this->makeTeamPlayer($team, $player);

        $this->actingAs($user);

        $response = $this->post(route('team.player.withdraw', [$team, $player, $event->id]));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    // -----------------------------------------------------------------------
    // withdraw — ownership check
    // -----------------------------------------------------------------------

    public function test_non_owner_cannot_withdraw_team_player(): void
    {
        $other  = User::factory()->create();
        $player = Player::factory()->create();
        $team   = Team::factory()->create();
        $event  = $this->eventWithOpenDeadline();
        $this->makeTeamPlayer($team, $player);

        $this->actingAs($other);

        $response = $this->post(route('team.player.withdraw', [$team, $player, $event->id]));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    // -----------------------------------------------------------------------
    // withdraw — team player not found
    // -----------------------------------------------------------------------

    public function test_withdraw_fails_when_team_player_record_missing(): void
    {
        ['user' => $user, 'player' => $player] = $this->ownerWithPlayer();
        $team  = Team::factory()->create();
        $event = $this->eventWithOpenDeadline();
        // No TeamPlayer record created

        $this->actingAs($user);

        $response = $this->post(route('team.player.withdraw', [$team, $player, $event->id]));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    // -----------------------------------------------------------------------
    // withdraw — unpaid slot: clears player_id
    // -----------------------------------------------------------------------

    public function test_unpaid_player_slot_is_cleared_on_withdraw(): void
    {
        ['user' => $user, 'player' => $player] = $this->ownerWithPlayer();
        $team       = Team::factory()->create();
        $event      = $this->eventWithOpenDeadline();
        $teamPlayer = $this->makeTeamPlayer($team, $player, 0); // unpaid

        $this->actingAs($user);

        $response = $this->post(route('team.player.withdraw', [$team, $player, $event->id]));

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('team_players', [
            'id'        => $teamPlayer->id,
            'player_id' => 0,
        ]);
    }

    // -----------------------------------------------------------------------
    // withdraw — paid slot, deadline open: redirects to choose-refund
    // -----------------------------------------------------------------------

    public function test_paid_player_within_deadline_redirects_to_choose_refund(): void
    {
        ['user' => $user, 'player' => $player] = $this->ownerWithPlayer();
        $team       = Team::factory()->create();
        $event      = $this->eventWithOpenDeadline();
        $teamPlayer = $this->makeTeamPlayer($team, $player, 1); // paid

        $this->actingAs($user);

        $response = $this->post(route('team.player.withdraw', [$team, $player, $event->id]));

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        // pay_status set to 0 (unpaid) before refund is processed
        $this->assertDatabaseHas('team_players', [
            'id'         => $teamPlayer->id,
            'pay_status' => 0,
        ]);
    }

    // -----------------------------------------------------------------------
    // withdraw — paid slot, deadline passed: no-refund message
    // -----------------------------------------------------------------------

    public function test_paid_player_past_deadline_gets_no_refund_message(): void
    {
        ['user' => $user, 'player' => $player] = $this->ownerWithPlayer();
        $team       = Team::factory()->create();
        $event      = $this->eventWithClosedDeadline();
        $teamPlayer = $this->makeTeamPlayer($team, $player, 1); // paid

        $this->actingAs($user);

        $response = $this->post(route('team.player.withdraw', [$team, $player, $event->id]));

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('team_players', [
            'id'         => $teamPlayer->id,
            'pay_status' => 0,
        ]);
    }

    // -----------------------------------------------------------------------
    // chooseRefund — auth guard
    // -----------------------------------------------------------------------

    public function test_guest_cannot_access_choose_refund_page(): void
    {
        $team   = Team::factory()->create();
        $player = Player::factory()->create();
        $event  = $this->eventWithOpenDeadline();

        $response = $this->get(route('team.player.refund.choose', [$team, $player, $event->id]));

        $response->assertRedirect(route('login'));
    }

    // -----------------------------------------------------------------------
    // chooseRefund — ownership check
    // -----------------------------------------------------------------------

    public function test_non_owner_cannot_access_choose_refund_page(): void
    {
        $other  = User::factory()->create();
        $player = Player::factory()->create();
        $team   = Team::factory()->create();
        $event  = $this->eventWithOpenDeadline();

        $this->actingAs($other);

        $response = $this->get(route('team.player.refund.choose', [$team, $player, $event->id]));

        $response->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // chooseRefund — no payment order → redirect with success
    // -----------------------------------------------------------------------

    public function test_choose_refund_redirects_when_no_payment_order(): void
    {
        ['user' => $user, 'player' => $player] = $this->ownerWithPlayer();
        $team  = Team::factory()->create();
        $event = $this->eventWithOpenDeadline();
        // No TeamPaymentOrder created

        $this->actingAs($user);

        $response = $this->get(route('team.player.refund.choose', [$team, $player, $event->id]));

        $response->assertStatus(302);
        $response->assertSessionHas('success');
    }

    // -----------------------------------------------------------------------
    // chooseRefund — unpaid order → redirect with success
    // -----------------------------------------------------------------------

    public function test_choose_refund_redirects_when_order_has_no_paid_amount(): void
    {
        ['user' => $user, 'player' => $player] = $this->ownerWithPlayer();
        $team  = Team::factory()->create();
        $event = $this->eventWithOpenDeadline();

        // Create a TeamPaymentOrder with no payment made
        TeamPaymentOrder::create([
            'user_id'       => $user->id,
            'team_id'       => $team->id,
            'player_id'     => $player->id,
            'event_id'      => $event->id,
            'total_amount'  => 100.00,
            'pay_status'    => 0,
            'payfast_paid'  => false,
            'wallet_debited' => false,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('team.player.refund.choose', [$team, $player, $event->id]));

        $response->assertStatus(302);
        $response->assertSessionHas('success');
    }

    // -----------------------------------------------------------------------
    // storeRefund — auth guard
    // -----------------------------------------------------------------------

    public function test_guest_cannot_submit_team_refund(): void
    {
        $team   = Team::factory()->create();
        $player = Player::factory()->create();
        $event  = $this->eventWithOpenDeadline();

        $response = $this->post(
            route('team.player.refund.request', [$team, $player, $event->id]),
            ['method' => 'wallet']
        );

        $response->assertRedirect(route('login'));
    }

    // -----------------------------------------------------------------------
    // storeRefund — ownership check
    // -----------------------------------------------------------------------

    public function test_non_owner_cannot_submit_team_refund(): void
    {
        $other  = User::factory()->create();
        $player = Player::factory()->create();
        $team   = Team::factory()->create();
        $event  = $this->eventWithOpenDeadline();

        $this->actingAs($other);

        $response = $this->post(
            route('team.player.refund.request', [$team, $player, $event->id]),
            ['method' => 'wallet']
        );

        $response->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // storeRefund — validation: invalid method
    // -----------------------------------------------------------------------

    public function test_store_refund_rejects_invalid_method(): void
    {
        ['user' => $user, 'player' => $player] = $this->ownerWithPlayer();
        $team  = Team::factory()->create();
        $event = $this->eventWithOpenDeadline();
        $this->makeTeamPlayer($team, $player, 1);

        TeamPaymentOrder::create([
            'user_id'        => $user->id,
            'team_id'        => $team->id,
            'player_id'      => $player->id,
            'event_id'       => $event->id,
            'total_amount'   => 100.00,
            'pay_status'     => 1,
            'payfast_paid'   => true,
            'wallet_debited' => false,
        ]);

        $this->actingAs($user);

        $response = $this->post(
            route('team.player.refund.request', [$team, $player, $event->id]),
            ['method' => 'cash'] // invalid
        );

        $response->assertSessionHasErrors(['method']);
    }

    // -----------------------------------------------------------------------
    // storeRefund — validation: bank fields required
    // -----------------------------------------------------------------------

    public function test_store_refund_bank_requires_bank_fields(): void
    {
        ['user' => $user, 'player' => $player] = $this->ownerWithPlayer();
        $team  = Team::factory()->create();
        $event = $this->eventWithOpenDeadline();
        $this->makeTeamPlayer($team, $player, 1);

        TeamPaymentOrder::create([
            'user_id'        => $user->id,
            'team_id'        => $team->id,
            'player_id'      => $player->id,
            'event_id'       => $event->id,
            'total_amount'   => 100.00,
            'pay_status'     => 1,
            'payfast_paid'   => true,
            'wallet_debited' => false,
        ]);

        $this->actingAs($user);

        $response = $this->post(
            route('team.player.refund.request', [$team, $player, $event->id]),
            ['method' => 'bank'] // all bank fields missing
        );

        $response->assertSessionHasErrors(['account_name', 'bank_name', 'account_number', 'branch_code', 'account_type']);
    }

    // -----------------------------------------------------------------------
    // storeRefund — no payment order found
    // -----------------------------------------------------------------------

    public function test_store_refund_fails_when_no_payment_order(): void
    {
        ['user' => $user, 'player' => $player] = $this->ownerWithPlayer();
        $team  = Team::factory()->create();
        $event = $this->eventWithOpenDeadline();
        $this->makeTeamPlayer($team, $player, 1);
        // No TeamPaymentOrder

        $this->actingAs($user);

        $response = $this->post(
            route('team.player.refund.request', [$team, $player, $event->id]),
            ['method' => 'wallet']
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    // -----------------------------------------------------------------------
    // storeRefund — no paid amount guard
    // -----------------------------------------------------------------------

    public function test_store_refund_fails_when_order_has_no_paid_amount(): void
    {
        ['user' => $user, 'player' => $player] = $this->ownerWithPlayer();
        $team  = Team::factory()->create();
        $event = $this->eventWithOpenDeadline();
        $this->makeTeamPlayer($team, $player, 1);

        TeamPaymentOrder::create([
            'user_id'        => $user->id,
            'team_id'        => $team->id,
            'player_id'      => $player->id,
            'event_id'       => $event->id,
            'total_amount'   => 100.00,
            'pay_status'     => 0,
            'payfast_paid'   => false,
            'wallet_debited' => false,
        ]);

        $this->actingAs($user);

        $response = $this->post(
            route('team.player.refund.request', [$team, $player, $event->id]),
            ['method' => 'wallet']
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    // -----------------------------------------------------------------------
    // storeRefund — wallet: credits wallet and clears slot
    // -----------------------------------------------------------------------

    public function test_store_refund_wallet_credits_wallet_and_clears_slot(): void
    {
        ['user' => $user, 'player' => $player] = $this->ownerWithPlayer();
        $team       = Team::factory()->create();
        $event      = $this->eventWithOpenDeadline();
        $teamPlayer = $this->makeTeamPlayer($team, $player, 1);

        // Give the user a wallet
        $wallet = Wallet::factory()->forUser($user)->create();

        $order = TeamPaymentOrder::create([
            'user_id'        => $user->id,
            'team_id'        => $team->id,
            'player_id'      => $player->id,
            'event_id'       => $event->id,
            'total_amount'   => 100.00,
            'pay_status'     => 1,
            'payfast_paid'   => true,
            'wallet_debited' => false,
        ]);

        $this->actingAs($user);

        $response = $this->post(
            route('team.player.refund.request', [$team, $player, $event->id]),
            ['method' => 'wallet']
        );

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        // Slot should be cleared
        $this->assertDatabaseHas('team_players', [
            'id'         => $teamPlayer->id,
            'player_id'  => 0,
            'pay_status' => 0,
        ]);

        // Order should be marked unpaid
        $this->assertDatabaseHas('team_payment_orders', [
            'id'         => $order->id,
            'pay_status' => 0,
        ]);

        // Wallet should have a credit transaction
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id'   => $wallet->id,
            'source_type' => 'team_player_refund',
            'source_id'   => $order->id,
            'type'        => 'credit',
        ]);
    }

    // -----------------------------------------------------------------------
    // storeRefund — bank: persists bank details and marks refund pending
    // -----------------------------------------------------------------------

    public function test_store_refund_bank_persists_details_and_marks_pending(): void
    {
        ['user' => $user, 'player' => $player] = $this->ownerWithPlayer();
        $team       = Team::factory()->create();
        $event      = $this->eventWithOpenDeadline();
        $teamPlayer = $this->makeTeamPlayer($team, $player, 1);

        $order = TeamPaymentOrder::create([
            'user_id'        => $user->id,
            'team_id'        => $team->id,
            'player_id'      => $player->id,
            'event_id'       => $event->id,
            'total_amount'   => 200.00,
            'pay_status'     => 1,
            'payfast_paid'   => true,
            'wallet_debited' => false,
        ]);

        $this->actingAs($user);

        $response = $this->post(
            route('team.player.refund.request', [$team, $player, $event->id]),
            [
                'method'         => 'bank',
                'account_name'   => 'John Doe',
                'bank_name'      => 'FNB',
                'account_number' => '62012345678',
                'branch_code'    => '250655',
                'account_type'   => 'cheque',
            ]
        );

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        // Slot should be cleared
        $this->assertDatabaseHas('team_players', [
            'id'         => $teamPlayer->id,
            'player_id'  => 0,
            'pay_status' => 0,
        ]);

        // Order should have refund details
        $this->assertDatabaseHas('team_payment_orders', [
            'id'            => $order->id,
            'refund_method' => 'bank',
            'refund_status' => 'pending',
            'refund_bank_name' => 'FNB',
            'pay_status'    => 0,
        ]);
    }
}
