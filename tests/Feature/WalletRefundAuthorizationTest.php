<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPaymentOrder;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WalletRefundAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_non_super_user_cannot_open_manual_wallet_transaction_form(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)
            ->get(route('transaction.create', $target->id))
            ->assertForbidden();

        $this->assertDatabaseMissing('wallets', [
            'payable_type' => User::class,
            'payable_id' => $target->id,
        ]);
    }

    public function test_admin_cannot_refund_team_payment_from_another_event(): void
    {
        [$admin, $teamPlayer, $order] = $this->refundScenario();
        $otherEvent = Event::factory()->create();
        DB::table('event_admins')->insert(['event_id' => $otherEvent->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)
            ->postJson(route('wallet.refund'), ['team_player_id' => $teamPlayer->id])
            ->assertForbidden();

        $this->assertSame('not_refunded', $order->fresh()->refund_status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_event_admin_refund_is_atomic_event_scoped_and_idempotent(): void
    {
        [$admin, $teamPlayer, $order, $event] = $this->refundScenario();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)
            ->postJson(route('wallet.refund'), ['team_player_id' => $teamPlayer->id])
            ->assertOk()
            ->assertJsonPath('amount', 256.5);

        $this->assertDatabaseHas('team_payment_orders', [
            'id' => $order->id,
            'refund_method' => 'wallet',
            'refund_status' => 'completed',
            'refund_gross' => 285,
            'refund_fee' => 28.5,
            'refund_net' => 256.5,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'source_type' => 'team_refund',
            'source_id' => $order->id,
            'amount' => 256.5,
            'type' => 'credit',
        ]);

        $this->actingAs($admin)
            ->postJson(route('wallet.refund'), ['team_player_id' => $teamPlayer->id])
            ->assertStatus(400);

        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_paid_team_slot_must_be_withdrawn_before_refund(): void
    {
        [$admin, $teamPlayer, $order, $event] = $this->refundScenario(payStatus: 1);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)
            ->postJson(route('wallet.refund'), ['team_player_id' => $teamPlayer->id])
            ->assertStatus(409);

        $this->assertSame('not_refunded', $order->fresh()->refund_status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_unpaid_mirror_does_not_prove_actual_withdrawal(): void
    {
        [$admin, $slot, $order, $event] = $this->refundScenario();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $order->update(['withdrawn_at' => null]);
        $this->actingAs($admin)->postJson(route('wallet.refund'), ['team_player_id' => $slot->id])->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertTrue($order->fresh()->pay_status);
        $this->assertSame('not_refunded', $order->fresh()->refund_status);
    }

    public function test_late_withdrawal_cannot_be_refunded_from_roster(): void
    {
        [$admin, $slot, $order, $event] = $this->refundScenario();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $order->update(['withdrawn_at' => $event->withdrawalCloseAt()->addMinute()]);
        $this->actingAs($admin)->postJson(route('wallet.refund'), ['team_player_id' => $slot->id])->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame('not_refunded', $order->fresh()->refund_status);
    }

    public function test_legacy_roster_save_preserves_unchanged_paid_slot_and_rejects_replacement(): void
    {
        [$admin, $slot, $order, $event] = $this->refundScenario(payStatus: 1);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin)->postJson(route('backend.team.roster.update'), [
            'team_id' => $slot->team_id, 'slots' => [$slot->id => $slot->player_id], 'preserve_payments' => false,
        ])->assertOk();
        $this->assertSame(1, (int) $slot->fresh()->pay_status);
        $replacement = Player::factory()->create();
        $this->postJson(route('backend.team.roster.update'), [
            'team_id' => $slot->team_id, 'slots' => [$slot->id => $replacement->id], 'preserve_payments' => true,
        ])->assertStatus(409)->assertJsonStructure(['url']);
        $this->assertSame((int) $order->player_id, (int) $slot->fresh()->player_id);
        $this->assertSame(1, (int) $slot->fresh()->pay_status);
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_legacy_roster_save_rejects_foreign_slots_and_unauthorized_actor(): void
    {
        [$admin, $slot, $order, $event] = $this->refundScenario(payStatus: 1);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $foreignTeam = Team::factory()->create();
        $foreignSlot = TeamPlayer::create([
            'team_id' => $foreignTeam->id, 'player_id' => Player::factory()->create()->id,
            'rank' => 1, 'pay_status' => 0,
        ]);
        $this->actingAs($admin)->postJson(route('backend.team.roster.update'), [
            'team_id' => $slot->team_id, 'slots' => [$foreignSlot->id => $slot->player_id],
        ])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->postJson(route('backend.team.roster.update'), [
            'team_id' => $slot->team_id, 'slots' => [$slot->id => $slot->player_id],
        ])->assertForbidden();
        $this->assertSame(1, (int) $slot->fresh()->pay_status);
        $this->assertSame(0, (int) $foreignSlot->fresh()->pay_status);
    }

    public function test_roster_payment_toggle_cannot_change_financial_state(): void
    {
        [$admin, $slot, $order, $event] = $this->refundScenario(payStatus: 1);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin)->postJson(route('team.change.pay.status'), ['pivot_id' => $slot->id])
            ->assertStatus(409)->assertJsonStructure(['url']);
        $this->assertSame(1, (int) $slot->fresh()->pay_status);
        $this->assertTrue($order->fresh()->pay_status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_pending_bank_refund_cannot_be_overwritten_by_roster_wallet_action(): void
    {
        [$admin, $slot, $order, $event] = $this->refundScenario();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $order->update(['refund_method' => 'bank', 'refund_status' => 'pending',
            'refund_gross' => 285, 'refund_fee' => 28.5, 'refund_net' => 256.5]);
        $this->actingAs($admin)->postJson(route('wallet.refund'), ['team_player_id' => $slot->id])->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseHas('team_payment_orders', ['id' => $order->id,
            'refund_method' => 'bank', 'refund_status' => 'pending',
            'refund_gross' => 285, 'refund_fee' => 28.5, 'refund_net' => 256.5]);
        $this->assertTrue($order->fresh()->pay_status);
    }

    public function test_refund_execution_rejects_gross_credit_and_pending_bank_under_lock(): void
    {
        [$admin, $slot, $order] = $this->refundScenario();
        $wallet = $order->user->wallet()->firstOrCreate();
        $service = app(\App\Domain\Refunds\Services\RefundExecutionService::class);
        foreach ([285, 256.5] as $amount) {
            if ($amount === 256.5) {
                $order->update(['refund_method' => 'bank', 'refund_status' => 'pending',
                    'refund_gross' => 285, 'refund_fee' => 28.5, 'refund_net' => 256.5]);
            }
            try {
                $service->executeWithdrawnTeamWalletRefund($order, $wallet, $amount, 'team_refund', $order->id);
                $this->fail('Unsafe refund execution must be rejected.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $this->assertArrayHasKey('refund', $exception->errors());
            }
        }
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame('bank', $order->fresh()->refund_method);
        $this->assertSame('pending', $order->fresh()->refund_status);
    }

    public function test_waived_refund_cannot_be_credited_by_roster_or_locked_execution(): void
    {
        [$admin, $slot, $order, $event] = $this->refundScenario();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $order->update(['refund_method' => 'wallet', 'refund_status' => 'waived',
            'refund_waived_at' => now(), 'refund_waived_by' => $admin->id,
            'refund_waiver_reason' => 'Payer opted to waive this refund.']);
        $before = $order->fresh()->getAttributes();
        $this->actingAs($admin)->postJson(route('wallet.refund'), ['team_player_id' => $slot->id])->assertUnprocessable();
        $wallet = $order->user->wallet()->firstOrCreate();
        try {
            app(\App\Domain\Refunds\Services\RefundExecutionService::class)
                ->executeWithdrawnTeamWalletRefund($order, $wallet, 256.5, 'team_refund', $order->id);
            $this->fail('Waived refunds must remain closed.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    private function refundScenario(int $payStatus = 0): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $payer = User::factory()->create();
        $event = Event::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['category_event_id' => $categoryEvent->id]);
        $player = Player::factory()->create(['userId' => $payer->id]);
        $player->users()->attach($payer->id);
        $teamPlayer = TeamPlayer::create([
            'team_id' => $team->id,
            'player_id' => $player->id,
            'rank' => 1,
            'pay_status' => $payStatus,
        ]);
        $order = TeamPaymentOrder::create([
            'user_id' => $payer->id,
            'event_id' => $event->id,
            'player_id' => $player->id,
            'team_id' => $team->id,
            'total_amount' => 285,
            'pay_status' => 1,
            'withdrawn_at' => now(),
        ]);

        return [$admin, $teamPlayer, $order, $event];
    }
}
