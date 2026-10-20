<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\{ExpiredPayfastHandoffService, TeamLatePaymentReconciliationService, TeamPaymentService};
use App\Models\{CategoryEvent, Event, Player, Team, TeamPaymentOrder, TeamPlayer, Transaction, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamLatePaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_preview_and_apply(): void
    {
        [$order, $actor] = $this->scenario();
        Mail::fake();
        $service = app(TeamLatePaymentReconciliationService::class);
        $before = $order->fresh()->getAttributes();
        $audits = Activity::count();
        $this->assertFalse($service->reconcile($order->id, 'LATE-EXACT', $actor, 'Reviewed original payment')['applied']);
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertSame($audits, Activity::count());
        $this->assertTrue($service->reconcile($order->id, 'LATE-EXACT', $actor, 'Reviewed original payment', true)['applied']);
        $this->assertTrue($order->fresh()->pay_status);
        $this->assertTrue($order->fresh()->payfast_paid);
        $this->assertNull($order->fresh()->withdrawn_at);
        $this->assertSame('paid', $order->fresh()->collection_status);
        $this->assertSame('LATE-EXACT', $order->fresh()->payfast_pf_payment_id);
        $this->assertSame(490.0, $order->fresh()->payfast_amount_due);
        $this->assertDatabaseHas('team_players', ['team_id' => $order->team_id, 'player_id' => $order->player_id, 'pay_status' => 1]);
        $this->assertDatabaseHas('transactions_pf', ['pf_payment_id' => 'LATE-EXACT', 'custom_int4' => $order->user_id, 'amount_gross' => 490, 'cape_tennis_fee' => 10]);
        $this->assertTrue($service->reconcile($order->id, 'LATE-EXACT', $actor, 'Replay', true)['already_reconciled']);
        $this->assertSame(1, Activity::where('description', TeamLatePaymentReconciliationService::AUDIT)->count());
        $audit = Activity::where('description', TeamLatePaymentReconciliationService::AUDIT)->sole();
        $this->assertSame((int) $actor->id, (int) $audit->causer_id);
        $this->assertSame(1, Activity::where('description', 'Expired unresolved PayFast handoff retained for reconciliation')->count());
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertDatabaseCount('team_players', 1);
        $this->assertDatabaseCount('user_players', 0);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_imported_roster_mirror_and_active_invitation_are_confirmed_without_replacing_identity(): void
    {
        [$order, $actor, $team, $player] = $this->scenario();
        $team->update(['noProfile' => true, 'region_id' => 1]);
        $imported = \App\Models\NoProfileTeamPlayer::create(['team_id' => $team->id, 'player_profile' => $player->id, 'name' => 'Test', 'surname' => 'Player', 'rank' => 1, 'pay_status' => 0]);
        $import = \App\Models\TeamSelectionImport::create(['source_id' => 1, 'event_id' => $order->event_id, 'region_id' => $team->region_id,
            'series_id' => 1, 'ranking_run_id' => 'late-recovery-test', 'status' => 'sent']);
        $invitation = \App\Models\TeamSelectionInvitation::create(['import_id' => $import->id, 'event_id' => $order->event_id,
            'region_id' => $team->region_id, 'team_id' => $team->id, 'player_id' => $player->id, 'ranking_list_id' => 1,
            'ranking_position' => 1, 'queue_position' => 1, 'roster_rank' => 1, 'status' => 'accepted_pending_payment',
            'snapshot_json' => ['expired_checkout_order_ids' => [$order->id]]]);
        app(TeamLatePaymentReconciliationService::class)->reconcile($order->id, 'LATE-EXACT', $actor, 'Review imported player', true);
        $this->assertSame(1, (int) $imported->fresh()->pay_status);
        $this->assertSame('paid_confirmed', $invitation->fresh()->status);
        $this->assertSame((int) $order->id, (int) $invitation->fresh()->order_id);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_repeated_verified_callback_after_recovery_is_acknowledged_without_new_receipt(): void
    {
        [$order, $actor] = $this->scenario();
        app(TeamLatePaymentReconciliationService::class)->reconcile($order->id, 'LATE-EXACT', $actor, 'Review', true);
        config()->set('services.payfast.sandbox', false);
        config()->set('services.payfast.merchant_id', 'test-merchant');
        config()->set('services.payfast.passphrase_live', 'test-passphrase');
        $payload = ['merchant_id' => 'test-merchant'] + $this->receipt($order);
        $payload['signature'] = md5(http_build_query($payload).'&passphrase='.urlencode('test-passphrase'));
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->call('POST', route('notify.team'), $payload, [], [],
                ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($payload))->assertOk();
        }
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(1, Activity::where('description', TeamLatePaymentReconciliationService::AUDIT)->count());
    }

    public function test_unpaid_successor_is_closed_and_other_events_are_untouched(): void
    {
        [$order, $actor] = $this->scenario();
        $successor = TeamPaymentOrder::create(['user_id' => User::factory()->create()->id, 'team_id' => $order->team_id,
            'event_id' => $order->event_id, 'player_id' => $order->player_id, 'total_amount' => 490, 'wallet_reserved' => 0, 'payfast_amount_due' => 490]);
        $other = TeamPaymentOrder::create(['user_id' => $order->user_id, 'team_id' => $order->team_id,
            'event_id' => Event::factory()->create()->id, 'player_id' => $order->player_id, 'total_amount' => 490, 'pay_status' => true]);
        $before = $other->fresh()->getAttributes();
        app(TeamLatePaymentReconciliationService::class)->reconcile($order->id, 'LATE-EXACT', $actor, 'Review', true);
        $this->assertNotNull($successor->fresh()->withdrawn_at);
        $this->assertSame(0.0, $successor->fresh()->payfast_amount_due);
        $this->assertFalse($successor->fresh()->pay_status);
        $this->assertSame($before, $other->fresh()->getAttributes());
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    #[DataProvider('invalidEvidence')]
    public function test_unsafe_recovery_is_rejected_atomically(string $invalid): void
    {
        [$order, $actor] = $this->scenario();
        if ($invalid === 'amount') Transaction::where('pf_payment_id', 'LATE-EXACT')->update(['amount_gross' => 489.99]);
        if ($invalid === 'payer') Transaction::where('pf_payment_id', 'LATE-EXACT')->update(['custom_int4' => User::factory()->create()->id]);
        if ($invalid === 'event') Transaction::where('pf_payment_id', 'LATE-EXACT')->update(['event_id' => Event::factory()->create()->id]);
        if ($invalid === 'player') Transaction::where('pf_payment_id', 'LATE-EXACT')->update(['custom_int2' => Player::factory()->create()->id]);
        if ($invalid === 'test') Transaction::where('pf_payment_id', 'LATE-EXACT')->update(['is_test' => true]);
        if ($invalid === 'refund') $order->update(['refund_status' => 'pending']);
        if ($invalid === 'wallet') $order->update(['wallet_reserved' => 1]);
        if ($invalid === 'original_wallet') {
            $audit = Activity::where('description', 'Expired unresolved PayFast handoff retained for reconciliation')->sole();
            $audit->update(['properties' => array_replace($audit->properties->all(), ['reserved_wallet_amount' => '1'])]);
        }
        if ($invalid === 'withdrawal') $order->update(['withdrawn_by' => $actor->id]);
        if ($invalid === 'transfer') $order->forceFill(['beneficiary_player_id' => $order->player_id])->save();
        if ($invalid === 'missing_audit') Activity::where('description', 'Expired PayFast charge requires reconciliation')->delete();
        if ($invalid === 'second_receipt') app(ExpiredPayfastHandoffService::class)->recordReceipt($order, $this->receipt($order, 'LATE-SECOND'));
        if (in_array($invalid, ['paid_successor', 'handoff_successor', 'refund_successor'], true)) {
            TeamPaymentOrder::create(['user_id' => $order->user_id, 'team_id' => $order->team_id, 'event_id' => $order->event_id,
                'player_id' => $order->player_id, 'total_amount' => 490, 'payfast_amount_due' => 490,
                'pay_status' => $invalid === 'paid_successor', 'payfast_handed_off_at' => $invalid === 'handoff_successor' ? now() : null,
                'refund_status' => $invalid === 'refund_successor' ? 'pending' : 'not_refunded']);
        }
        $before = $order->fresh()->getAttributes();
        try {
            app(TeamLatePaymentReconciliationService::class)->reconcile($order->id, 'LATE-EXACT', $actor, 'Review', true);
            $this->fail('Unsafe recovery succeeded: '.$invalid);
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertDatabaseHas('team_players', ['team_id' => $order->team_id, 'player_id' => $order->player_id, 'pay_status' => 0]);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(0, Activity::where('description', TeamLatePaymentReconciliationService::AUDIT)->count());
    }

    public static function invalidEvidence(): array
    {
        return array_map(fn ($invalid) => [$invalid], ['amount', 'payer', 'event', 'player', 'test', 'refund', 'wallet', 'original_wallet', 'withdrawal', 'transfer', 'missing_audit', 'second_receipt', 'paid_successor', 'handoff_successor', 'refund_successor']);
    }

    public function test_non_super_user_cannot_preview_or_apply(): void
    {
        [$order] = $this->scenario();
        foreach ([false, true] as $apply) {
            try {
                app(TeamLatePaymentReconciliationService::class)->reconcile($order->id, 'LATE-EXACT', $order->user, 'Review', $apply);
                $this->fail('Unauthorized recovery succeeded.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertFalse($order->fresh()->pay_status);
    }

    public function test_explicit_reconciliation_context_suppresses_registration_notification_listener(): void
    {
        [$order, $actor] = $this->scenario();
        app(TeamLatePaymentReconciliationService::class)->reconcile($order->id, 'LATE-EXACT', $actor, 'Review', true);
        Mail::fake();
        app(\App\Listeners\SendTeamRegistrationConfirmation::class)->handle(
            new \App\Events\PaymentCompleted($order->fresh(), ['suppress_registration_confirmation' => true]));
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_received_payment_blocks_all_payers_and_stale_successor_handoff(): void
    {
        [$order, $actor, $team, $player] = $this->scenario();
        $payments = app(TeamPaymentService::class);
        foreach ([$order->user, $actor, User::factory()->create()] as $payer) {
            try { $payments->ensureOrder($payer, $team, $player, $order->event, 490); $this->fail('Duplicate checkout was created.'); }
            catch (ValidationException $exception) { $this->assertSame('Payment received and awaiting review. Do not pay again.', $exception->errors()['payment'][0]); }
        }
        $successor = TeamPaymentOrder::create(['user_id' => $order->user_id, 'event_id' => $order->event_id, 'team_id' => $team->id,
            'player_id' => $player->id, 'total_amount' => 490, 'wallet_reserved' => 0, 'payfast_amount_due' => 490]);
        try { $payments->recordPayfastHandoff($successor, $order->user, 490); $this->fail('A stale checkout charged again.'); }
        catch (ValidationException $exception) { $this->assertSame('Payment received and awaiting review. Do not pay again.', $exception->errors()['payment'][0]); }
        $this->assertNull($successor->fresh()->payfast_handed_off_at);
        foreach ([fn () => $payments->reservePayment($successor, 490, 0), fn () => $payments->finalizeWalletPayment($successor)] as $walletAction) {
            try { $walletAction(); $this->fail('A stale checkout used wallet funds again.'); }
            catch (ValidationException $exception) { $this->assertSame('Payment received and awaiting review. Do not pay again.', $exception->errors()['payment'][0]); }
        }
        $this->assertSame(0.0, $successor->fresh()->wallet_reserved);
        $this->assertDatabaseCount('transactions_pf', 1);
    }

    public function test_command_defaults_to_preview_then_explicit_apply(): void
    {
        [$order, $actor] = $this->scenario();
        $options = ['order' => $order->id, 'provider' => 'LATE-EXACT', '--actor' => $actor->id, '--reason' => 'Operator reviewed'];
        $this->artisan('payments:reconcile-late-team', $options)->assertSuccessful();
        $this->assertFalse($order->fresh()->pay_status);
        $this->artisan('payments:reconcile-late-team', $options + ['--apply' => true])->assertSuccessful();
        $this->assertTrue($order->fresh()->pay_status);
    }

    private function scenario(): array
    {
        $this->freezeTime();
        $event = Event::factory()->create(['cape_tennis_fee' => 10]);
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id, 'noProfile' => false]);
        $player = Player::factory()->create();
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);
        $order = TeamPaymentOrder::create(['user_id' => User::factory()->create()->id, 'team_id' => $team->id, 'event_id' => $event->id,
            'player_id' => $player->id, 'total_amount' => 490, 'wallet_reserved' => 0, 'payfast_amount_due' => 490,
            'pay_status' => false, 'payfast_paid' => false, 'wallet_debited' => false, 'payfast_handed_off_at' => now()->subMinutes(6)]);
        $this->assertTrue(app(TeamPaymentService::class)->expireUnpaidCheckout($order));
        $this->assertTrue(app(ExpiredPayfastHandoffService::class)->recordReceipt($order, $this->receipt($order)));
        $actor = User::factory()->create();
        $actor->assignRole(Role::findOrCreate('super-user', 'web'));
        return [$order->fresh(), $actor, $team, $player];
    }

    private function receipt(TeamPaymentOrder $order, string $provider = 'LATE-EXACT'): array
    {
        return ['payment_status' => 'COMPLETE', 'pf_payment_id' => $provider, 'amount_gross' => '490.00', 'custom_str5' => 'TeamOrder',
            'custom_int5' => $order->id, 'custom_int4' => $order->user_id, 'custom_int3' => $order->event_id, 'custom_int2' => $order->player_id];
    }
}
