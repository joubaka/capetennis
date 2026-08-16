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
            ->assertJsonPath('amount', 285);

        $this->assertDatabaseHas('team_payment_orders', [
            'id' => $order->id,
            'refund_method' => 'wallet',
            'refund_status' => 'completed',
            'refund_gross' => 285,
            'refund_net' => 285,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'source_type' => 'team_refund',
            'source_id' => $order->id,
            'amount' => 285,
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
        ]);

        return [$admin, $teamPlayer, $order, $event];
    }
}
