<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\PaymentTransactionService;
use App\Events\PaymentCompleted;
use App\Models\{CategoryEvent, Event, Player, Team, TeamPaymentOrder, TeamPlayer, Transaction, User};
use Illuminate\Database\DeadlockException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\{DB, Event as Events};
use Tests\TestCase;

class TeamPayfastDeadlockTest extends TestCase
{
    // Retries must own the outermost transaction, as in an actual HTTP request.
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        try {
            // Leave no committed fixtures for tests using RefreshDatabase next.
            $this->truncateTablesForAllConnections();
        } finally {
            parent::tearDown();
        }
    }

    public function test_deadlock_retries_entire_finalization_and_replay_remains_idempotent(): void
    {
        [$order, $payload] = $this->scenario();
        $recorder = $this->recorder(1);

        $this->sendCallback($payload)->assertOk();
        $this->assertSame(2, $recorder->attempts);
        $this->sendCallback($payload)->assertOk();
        $this->assertSame(2, $recorder->attempts);
        $this->assertTrue($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseHas('transactions_pf', ['pf_payment_id' => 'PF-DEADLOCK', 'amount_gross' => 160, 'custom_int5' => $order->id]);
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseHas('wallet_transactions', ['type' => 'debit', 'amount' => 40, 'source_id' => $order->id]);
        $this->assertDatabaseHas('team_players', ['team_id' => $order->team_id, 'player_id' => $order->player_id, 'pay_status' => 1]);
        Events::assertDispatchedTimes(PaymentCompleted::class, 1);
    }

    public function test_exhausted_deadlocks_return_503_and_roll_back_all_payment_state(): void
    {
        [$order, $payload] = $this->scenario();
        $recorder = $this->recorder(3);

        $this->sendCallback($payload)->assertStatus(503);
        $this->assertSame(3, $recorder->attempts);
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertFalse($order->fresh()->wallet_debited);
        $this->assertSame(40.0, $order->fresh()->wallet_reserved);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('team_players', ['team_id' => $order->team_id, 'player_id' => $order->player_id, 'pay_status' => 0]);
        Events::assertNotDispatched(PaymentCompleted::class);
    }

    public function test_mismatched_payer_event_and_amount_still_return_400_without_retry(): void
    {
        [$order, $payload] = $this->scenario();
        $recorder = $this->recorder(0);
        foreach (['custom_int4' => $order->user_id + 1, 'custom_int3' => $order->event_id + 1, 'amount_gross' => '159.99'] as $key => $value) {
            $this->sendCallback(array_replace($payload, [$key => $value]))->assertStatus(400);
        }
        $this->assertSame(0, $recorder->attempts);
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    private function recorder(int $failures): PaymentTransactionService
    {
        $recorder = new class($failures) extends PaymentTransactionService {
            public int $attempts = 0;

            public function __construct(private int $failures) {}

            protected function recordPayfastTransaction(array $data): Transaction
            {
                // Fail after the real receipt and all preceding payment writes.
                $receipt = parent::recordPayfastTransaction($data);
                if (++$this->attempts <= $this->failures) {
                    throw new DeadlockException('Deadlock found when trying to get lock; try restarting transaction');
                }

                return $receipt;
            }
        };
        $this->app->instance(PaymentTransactionService::class, $recorder);

        return $recorder;
    }

    private function sendCallback(array $payload): \Illuminate\Testing\TestResponse
    {
        $payload['signature'] = md5(http_build_query($payload).'&passphrase=test-passphrase');

        return $this->call('POST', route('notify.team'), $payload, [], [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($payload));
    }

    private function scenario(): array
    {
        $this->assertSame('ct_testing', DB::connection()->getDatabaseName());
        Events::fake([PaymentCompleted::class]);
        config(['services.payfast.sandbox' => false, 'services.payfast.merchant_id' => 'test-merchant', 'services.payfast.passphrase_live' => 'test-passphrase']);
        $event = Event::factory()->create();
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id, 'noProfile' => false]);
        $player = Player::factory()->create();
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);
        $payer = User::factory()->create();
        $wallet = $payer->wallet()->create();
        $wallet->transactions()->create(['type' => 'credit', 'amount' => 40, 'source_type' => 'test']);
        $order = TeamPaymentOrder::create(['user_id' => $payer->id, 'team_id' => $team->id, 'player_id' => $player->id,
            'event_id' => $event->id, 'total_amount' => 200, 'wallet_reserved' => 40, 'payfast_amount_due' => 160,
            'pay_status' => false, 'payfast_paid' => false, 'wallet_debited' => false]);

        return [$order, ['merchant_id' => 'test-merchant', 'payment_status' => 'COMPLETE', 'pf_payment_id' => 'PF-DEADLOCK',
            'amount_gross' => '160.00', 'custom_str5' => 'TeamOrder', 'custom_int5' => $order->id,
            'custom_int4' => $payer->id, 'custom_int3' => $event->id, 'custom_int2' => $player->id]];
    }
}
