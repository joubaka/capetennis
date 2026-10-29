<?php

namespace Tests\Feature;

use App\Domain\Refunds\Services\RefundExecutionService;
use App\Models\{CategoryEvent, Event, Player, Team, TeamPaymentOrder, TeamPlayer, TeamProviderRefundAttempt, User, WalletTransaction};
use App\Services\{AdminTeamWithdrawalService, Payfast};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{DB, Event as Events, Http};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminTeamWithdrawalProviderRefundTest extends TestCase
{
    use DatabaseMigrations;

    // Provider dispatch must cross real commits. Isolated test DBs are rebuilt
    // before each case; rollback deliberately refuses durable provider claims.
    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Events::fake([\App\Events\RefundCompleted::class]);
    }

    private function scenario(bool $hybrid = true): array
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $payer = User::factory()->create();
        $event = Event::factory()->create(['withdrawal_deadline' => now()->addDays(3)]);
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id, 'noProfile' => false]);
        $player = Player::factory()->create();
        $slot = TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 1]);
        $walletPart = $hybrid ? 85 : 0;
        $order = TeamPaymentOrder::create(['event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $player->id,
            'user_id' => $payer->id, 'total_amount' => 285, 'wallet_reserved' => $walletPart, 'payfast_amount_due' => 285 - $walletPart,
            'wallet_debited' => $hybrid, 'payfast_paid' => true, 'payfast_pf_payment_id' => 'PF-refund-'. $slot->id, 'pay_status' => true]);
        $wallet = $payer->wallet()->firstOrCreate();
        if ($hybrid) WalletTransaction::create(['wallet_id' => $wallet->id, 'type' => 'debit', 'amount' => 85, 'source_type' => 'team_registration_wallet_payment', 'source_id' => $order->id]);
        DB::table('transactions_pf')->insert(['custom_str5' => 'TeamOrder', 'custom_int5' => $order->id, 'custom_int2' => $player->id,
            'custom_int3' => $event->id, 'custom_int4' => $payer->id, 'pf_payment_id' => $order->payfast_pf_payment_id, 'amount_gross' => 285 - $walletPart, 'payment_status' => 'COMPLETE']);
        $this->actingAs($actor);
        return compact('actor', 'payer', 'event', 'team', 'player', 'slot', 'order', 'wallet');
    }

    private function payload(array $s, string $destination = 'payfast', string $feeMode = 'current'): array
    {
        $review = app(AdminTeamWithdrawalService::class)->review($s['event'], $s['team'], $s['slot'], $s['actor'], $destination, $feeMode);
        return ['order_id' => $s['order']->id, 'fingerprint' => $review['fingerprint'], 'destination' => $destination, 'fee_mode' => $feeMode, 'reason' => 'Requested original source refund', 'confirm' => 1];
    }

    private function url(array $s): string
    {
        return route('backend.team.withdrawal.store', [$s['event'], $s['team'], $s['slot']]);
    }

    private function recovery(array $s): string
    {
        return route('backend.team.withdrawal.refund.recover', [$s['event'], $s['order']]);
    }

    private function success(): array
    {
        return ['success' => true, 'data' => ['status' => 'success', 'code' => 200, 'data' => ['response' => true]]];
    }

    public function test_full_and_current_fee_payfast_and_hybrid_refund_exact_original_funding_once_after_commit(): void
    {
        foreach ([false, true] as $hybrid) foreach (['full', 'current'] as $mode) {
            $s = $this->scenario($hybrid);
            $providerAmount = ($hybrid ? 200 : 285) - ($mode === 'full' ? 0 : 28.5);
            $mock = $this->mock(Payfast::class);
            $mock->shouldReceive('refundToOriginalSource')->once()->with($s['order']->payfast_pf_payment_id, (float) $providerAmount, 'Team withdrawal refund')
                ->andReturnUsing(function () use ($s): array {
                    $this->assertSame(0, DB::transactionLevel());
                    $this->assertNotNull($s['order']->fresh()->withdrawn_at);
                    $this->assertSame(0, (int) $s['slot']->fresh()->player_id);
                    $this->assertDatabaseHas('team_provider_refund_attempts', ['order_id' => $s['order']->id, 'status' => 'dispatching']);
                    return $this->success();
                });
            $payload = $this->payload($s, 'payfast', $mode);
            $this->post($this->url($s), $payload)->assertRedirect();
            $this->post($this->url($s), $payload)->assertRedirect();
            $this->assertDatabaseHas('team_payment_orders', ['id' => $s['order']->id, 'pay_status' => 1, 'payfast_paid' => 1,
                'refund_status' => 'completed', 'refund_method' => 'payfast', 'refund_gross' => 285, 'refund_fee' => $mode === 'full' ? 0 : 28.5, 'refund_net' => $mode === 'full' ? 285 : 256.5]);
            $credits = WalletTransaction::where('source_type', 'admin_team_withdrawal_refund')->where('source_id', $s['order']->id)->get();
            $this->assertCount($hybrid ? 1 : 0, $credits);
            if ($hybrid) $this->assertEquals(85, $credits->first()->amount);
            $this->assertDatabaseHas('team_provider_refund_attempts', ['order_id' => $s['order']->id, 'status' => 'confirmed', 'amount' => $providerAmount, 'wallet_amount' => $hybrid ? 85 : 0]);
        }
    }

    public function test_full_wallet_refund_waives_fee_without_provider_dispatch(): void
    {
        $s = $this->scenario();
        $this->mock(Payfast::class)->shouldNotReceive('refundToOriginalSource');
        $this->post($this->url($s), $this->payload($s, 'wallet', 'full'))->assertRedirect();
        $this->assertDatabaseHas('wallet_transactions', ['wallet_id' => $s['wallet']->id, 'type' => 'credit', 'amount' => 285]);
        $this->assertDatabaseHas('team_payment_orders', ['id' => $s['order']->id, 'refund_fee' => 0, 'refund_net' => 285, 'refund_method' => 'wallet']);
        $this->assertDatabaseCount('team_provider_refund_attempts', 0);
    }

    public function test_late_provider_refund_preserves_deadline_and_uses_audited_authorization_for_recovery(): void
    {
        $s = $this->scenario();
        $s['event']->update(['withdrawal_deadline' => now()->subDay()]);
        $deadline = $s['event']->withdrawalCloseAt()->format('Y-m-d H:i:s');
        $review = app(AdminTeamWithdrawalService::class)->review($s['event'], $s['team'], $s['slot'], $s['actor'], 'payfast', 'full', 'refund', true);
        $payload = ['order_id' => $s['order']->id, 'fingerprint' => $review['fingerprint'], 'destination' => 'payfast',
            'fee_mode' => 'full', 'deadline_override' => 1, 'reason' => 'Exceptional late withdrawal approved', 'confirm' => 1];
        $this->mock(Payfast::class)->shouldReceive('refundToOriginalSource')->once()->with($s['order']->payfast_pf_payment_id, 200.0, 'Team withdrawal refund')->andThrow(new \RuntimeException('timeout'));
        $this->post($this->url($s), $payload)->assertRedirect();
        $attempt = TeamProviderRefundAttempt::where('order_id', $s['order']->id)->sole();
        $this->assertSame($deadline, $attempt->authorized_deadline->format('Y-m-d H:i:s'));
        $this->assertTrue($attempt->authorized_withdrawn_at->gt($attempt->authorized_deadline));
        $this->assertSame('uncertain', $attempt->status);
        $this->post($this->url($s), $payload)->assertRedirect();
        $evidence = ['action' => 'reconcile', 'confirm' => 1, 'externally_confirmed' => 1,
            'pf_payment_id' => $attempt->pf_payment_id, 'amount' => '200.00', 'reference' => 'LATE-REFUND-123',
            'reason' => 'Verified independently with provider', 'confirmed_at' => now()->toDateTimeString()];
        $audit = \Spatie\Activitylog\Models\Activity::where('description', 'super user authorized team withdrawal refund after deadline')->sole();
        $properties = $audit->properties;
        $audit->update(['properties' => $properties->merge(['fingerprint' => str_repeat('0', 64)])]);
        $this->postJson($this->recovery($s), $evidence)->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame('uncertain', $attempt->fresh()->status);
        $audit->delete();
        $this->postJson($this->recovery($s), $evidence)->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 1);
        activity('refund')->performedOn($s['order'])->causedBy($s['actor'])->withProperties($properties)->log('super user authorized team withdrawal refund after deadline');
        $this->post($this->recovery($s), $evidence)->assertRedirect();
        $this->assertDatabaseHas('team_payment_orders', ['id' => $s['order']->id, 'refund_status' => 'completed', 'refund_net' => 285, 'refund_fee' => 0]);
        $this->assertDatabaseHas('wallet_transactions', ['wallet_id' => $s['wallet']->id, 'type' => 'credit', 'amount' => 85]);
        $this->assertDatabaseCount('team_provider_refund_attempts', 1);
        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_canonical_admin_refund_request_rejects_late_withdrawal_without_override(): void
    {
        $s = $this->scenario();
        $s['event']->update(['withdrawal_deadline' => now()->subDay()]);
        $withdrawn = app(\App\Domain\Payments\Services\TeamPaymentService::class)->recordWithdrawal($s['order'], $s['actor']);
        try {
            app(RefundExecutionService::class)->requestAdminTeamWithdrawalRefund($withdrawn, $s['actor'], 'wallet', 'current', str_repeat('a', 64));
            $this->fail('Late canonical refund must require explicit override.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }
        $this->assertDatabaseCount('team_provider_refund_attempts', 0);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame('not_refunded', $s['order']->fresh()->refund_status);
    }

    public function test_choices_are_bound_to_preview_and_review_is_read_only(): void
    {
        $s = $this->scenario();
        $page = $this->get(route('backend.team.withdrawal.show', [$s['event'], $s['team'], $s['slot'], 'destination' => 'payfast', 'fee_mode' => 'full']))
            ->assertOk()->assertSee('R285.00')->assertSee('R200.00')->assertSee('R85.00');
        if (getenv('CT_WITHDRAWAL_QA') === '1') {
            \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('app/team-withdrawal-qa'));
            file_put_contents(storage_path('app/team-withdrawal-qa/payfast-full-review.html'), $page->getContent());
        }
        $this->assertDatabaseCount('team_provider_refund_attempts', 0);
        $this->assertNull($s['order']->fresh()->withdrawn_at);
        $this->mock(Payfast::class)->shouldNotReceive('refundToOriginalSource');
        $payload = $this->payload($s); $payload['fee_mode'] = 'full';
        $this->postJson($this->url($s), $payload)->assertUnprocessable();
        $payload = $this->payload($s); $payload['destination'] = 'wallet';
        $this->postJson($this->url($s), $payload)->assertUnprocessable();
        $this->assertNull($s['order']->fresh()->withdrawn_at);
    }

    public function test_uncertain_provider_outcome_commits_withdrawal_blocks_retry_and_alternate_wallet_or_bank_refund(): void
    {
        $s = $this->scenario();
        $this->mock(Payfast::class)->shouldReceive('refundToOriginalSource')->once()->andThrow(new \RuntimeException('timeout'));
        $payload = $this->payload($s);
        $pending = route('backend.team.withdrawal.refund.show', [$s['event'], $s['order']]);
        $this->post($this->url($s), $payload)->assertRedirect($pending);
        $this->post($this->url($s), $payload)->assertRedirect($pending);
        $this->get($pending)->assertOk()->assertSee('Do not issue another refund');
        $this->assertDatabaseHas('team_provider_refund_attempts', ['order_id' => $s['order']->id, 'status' => 'uncertain']);
        $migration = require database_path('migrations/2026_10_17_000001_create_team_provider_refund_attempts.php');
        try {
            $migration->down();
            $this->fail('Durable refund claims must prevent rollback.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Retain team provider refund', $exception->getMessage());
        }
        foreach (['wallet', 'bank'] as $method) {
            $this->postJson(route('superadmin.finances.full-refund.team', [$s['event'], $s['order']]), ['method' => $method, 'percentage' => 0])->assertUnprocessable();
        }
        $this->postJson($this->recovery($s), ['action' => 'resume', 'confirm' => 1])->assertUnprocessable();
        $this->assertDatabaseHas('team_payment_orders', ['id' => $s['order']->id, 'refund_method' => 'payfast', 'refund_status' => 'pending']);
        $this->assertDatabaseHas('team_players', ['id' => $s['slot']->id, 'player_id' => 0]);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_only_strict_payfast_confirmation_completes_a_refund(): void
    {
        foreach ([['success' => true], ['success' => true, 'data' => ['status' => 'success', 'code' => 200, 'data' => ['response' => 'true']]], ['success' => false, 'data' => $this->success()['data']]] as $response) {
            $s = $this->scenario(false);
            $this->mock(Payfast::class)->shouldReceive('refundToOriginalSource')->once()->andReturn($response);
            $this->post($this->url($s), $this->payload($s))->assertRedirect(route('backend.team.withdrawal.refund.show', [$s['event'], $s['order']]));
            $this->assertDatabaseHas('team_provider_refund_attempts', ['order_id' => $s['order']->id, 'status' => 'uncertain']);
            $this->assertDatabaseHas('team_payment_orders', ['id' => $s['order']->id, 'refund_status' => 'pending']);
        }
    }

    public function test_reconciliation_requires_exact_independent_evidence_and_finishes_once_without_redispatch(): void
    {
        $s = $this->scenario();
        $this->mock(Payfast::class)->shouldReceive('refundToOriginalSource')->once()->andThrow(new \RuntimeException('timeout'));
        $this->post($this->url($s), $this->payload($s, 'payfast', 'full'))->assertRedirect();
        $s['event']->update(['withdrawal_deadline' => now()->subDay()]);
        $evidence = ['action' => 'reconcile', 'confirm' => 1, 'pf_payment_id' => $s['order']->payfast_pf_payment_id, 'amount' => 200,
            'reference' => 'PF-refund-confirmed-123', 'reason' => 'Independently confirmed original source refund in provider history', 'confirmed_at' => now()->subMinute()->toDateTimeString(), 'externally_confirmed' => 1];
        $invalid = $evidence; $invalid['amount'] = 201;
        $this->postJson($this->recovery($s), $invalid)->assertUnprocessable();
        $invalid = $evidence; $invalid['pf_payment_id'] = 'different';
        $this->postJson($this->recovery($s), $invalid)->assertUnprocessable();
        $invalid = $evidence; unset($invalid['externally_confirmed']);
        $this->postJson($this->recovery($s), $invalid)->assertUnprocessable();
        $this->post($this->recovery($s), $evidence)->assertRedirect();
        $this->post($this->recovery($s), ['action' => 'resume', 'confirm' => 1])->assertRedirect();
        $this->assertDatabaseHas('team_payment_orders', ['id' => $s['order']->id, 'pay_status' => 1, 'refund_status' => 'completed', 'refund_fee' => 0]);
        $this->assertDatabaseHas('wallet_transactions', ['wallet_id' => $s['wallet']->id, 'type' => 'credit', 'amount' => 85]);
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseHas('team_provider_refund_attempts', ['order_id' => $s['order']->id, 'confirmed_by' => $s['actor']->id, 'provider_reference' => 'PF-refund-confirmed-123']);
    }

    public function test_recovery_rejects_wrong_event_and_non_super_user_without_provider_or_ledger_changes(): void
    {
        $s = $this->scenario();
        $this->mock(Payfast::class)->shouldReceive('refundToOriginalSource')->once()->andThrow(new \RuntimeException('timeout'));
        $this->post($this->url($s), $this->payload($s))->assertRedirect();
        $this->postJson(route('backend.team.withdrawal.refund.recover', [Event::factory()->create(), $s['order']]), ['action' => 'resume', 'confirm' => 1])->assertNotFound();
        $this->actingAs($s['payer'])->get(route('backend.team.withdrawal.refund.show', [$s['event'], $s['order']]))->assertForbidden();
        $this->postJson($this->recovery($s), ['action' => 'resume', 'confirm' => 1])->assertForbidden();
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_original_source_helper_never_sends_bank_payout_and_rejects_excess_amount(): void
    {
        $payfast = \Mockery::mock(Payfast::class)->makePartial();
        $payfast->shouldReceive('refundQuery')->twice()->andReturn(['success' => true, 'data' => ['data' => ['response' => [
            'status' => 'REFUNDABLE', 'amount_available_for_refund' => 20000, 'refund_partial' => ['method' => 'BANK_PAYOUT'], 'refund_full' => ['method' => 'PAYMENT_SOURCE'],
        ]]]]);
        $payfast->shouldNotReceive('refund');
        $this->assertFalse($payfast->refundToOriginalSource('PF-123', 171.5, 'refund')['success']);
        $this->assertFalse($payfast->refundToOriginalSource('PF-123', 201, 'refund')['success']);
    }

    public function test_confirmed_provider_refund_can_finish_local_ledger_failure_without_redispatch(): void
    {
        $s = $this->scenario();
        $executor = new class(app(\App\Domain\Payments\Services\LedgerService::class)) extends RefundExecutionService {
            public bool $failLocalWrite = true;
            public function executeSplitRefund(\Illuminate\Database\Eloquent\Model $entity, ?\App\Models\Wallet $wallet, float $walletAmount, string $sourceType, int $sourceId, array $meta = [], array $statusOverrides = []): \Illuminate\Database\Eloquent\Model
            {
                if ($this->failLocalWrite) throw new \RuntimeException('Simulated local ledger failure after provider confirmation');
                return parent::executeSplitRefund($entity, $wallet, $walletAmount, $sourceType, $sourceId, $meta, $statusOverrides);
            }
        };
        $this->app->instance(RefundExecutionService::class, $executor);
        $this->mock(Payfast::class)->shouldReceive('refundToOriginalSource')->once()->andReturn($this->success());
        $this->post($this->url($s), $this->payload($s))->assertRedirect(route('backend.team.withdrawal.refund.show', [$s['event'], $s['order']]));
        $this->assertDatabaseHas('team_provider_refund_attempts', ['order_id' => $s['order']->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('team_payment_orders', ['id' => $s['order']->id, 'refund_status' => 'pending']);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $executor->failLocalWrite = false;
        $s['event']->update(['withdrawal_deadline' => now()->subDay()]);
        $this->post($this->recovery($s), ['action' => 'resume', 'confirm' => 1])->assertRedirect();
        $this->assertDatabaseHas('team_payment_orders', ['id' => $s['order']->id, 'refund_status' => 'completed', 'refund_net' => 256.5]);
        $this->assertDatabaseCount('wallet_transactions', 2);
    }
}
