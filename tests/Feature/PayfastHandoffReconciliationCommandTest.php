<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\TeamPaymentService;
use App\Domain\Payments\Services\RegistrationPaymentService;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\TeamPaymentOrder;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayfastHandoffReconciliationCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->operator = User::factory()->create()->assignRole('super-user');
    }

    public function test_preview_is_read_only_apply_requires_evidence_and_release_preserves_order_before_normal_resubmission(): void
    {
        $payer = User::factory()->create();
        $order = $this->unresolvedTeamOrder($payer);
        $originalHandoff = $order->payfast_handed_off_at->toISOString();

        $this->artisan('payments:release-payfast-handoff', [
            'type' => 'team',
            'id' => $order->id,
        ])->expectsOutput("PREVIEW: team order {$order->id} is eligible. No data was changed.")
            ->assertSuccessful();

        $this->assertSame($originalHandoff, $order->fresh()->payfast_handed_off_at->toISOString());

        $this->artisan('payments:release-payfast-handoff', [
            'type' => 'team',
            'id' => $order->id,
            '--apply' => true,
            '--evidence' => 'PF-QUERY:CASE-1042',
        ])->assertFailed();

        $this->assertSame($originalHandoff, $order->fresh()->payfast_handed_off_at->toISOString());

        $ordinaryUser = User::factory()->create();
        foreach ([999999, $ordinaryUser->id] as $invalidOperator) {
            $this->artisan('payments:release-payfast-handoff', [
                'type' => 'team',
                'id' => $order->id,
                '--apply' => true,
                '--evidence' => 'PF-QUERY:CASE-1042',
                '--operator' => $invalidOperator,
            ])->assertFailed();
            $this->assertSame($originalHandoff, $order->fresh()->payfast_handed_off_at->toISOString());
        }

        $orderCount = TeamPaymentOrder::count();
        $activityCount = DB::table('activity_log')->count();
        $this->artisan('payments:release-payfast-handoff', [
            'type' => 'team',
            'id' => $order->id,
            '--apply' => true,
            '--evidence' => 'PF-QUERY:CASE-1042',
            '--operator' => $this->operator->id,
        ])->expectsOutput("Released team order {$order->id}. The order may now be re-submitted through the normal checkout.")
            ->assertSuccessful();

        $released = $order->fresh();
        $this->assertNull($released->payfast_handed_off_at);
        $this->assertSame(25.0, (float) $released->wallet_reserved);
        $this->assertSame(125.0, (float) $released->payfast_amount_due);
        $this->assertSame($orderCount, TeamPaymentOrder::count());
        $this->assertSame($activityCount + 1, DB::table('activity_log')->count());
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TeamPaymentOrder::class,
            'subject_id' => $order->id,
            'causer_type' => User::class,
            'causer_id' => $this->operator->id,
            'description' => 'supervised unresolved PayFast handoff released',
        ]);
        $auditProperties = json_decode((string) DB::table('activity_log')
            ->where('subject_type', TeamPaymentOrder::class)
            ->where('subject_id', $order->id)
            ->where('description', 'supervised unresolved PayFast handoff released')
            ->value('properties'), true);
        $this->assertSame($this->operator->id, $auditProperties['operator_id']);
        $this->assertSame('PF-QUERY:CASE-1042', $auditProperties['evidence_reference']);

        app(TeamPaymentService::class)->recordPayfastHandoff($released, $payer, 125);
        $this->assertNotNull($order->fresh()->payfast_handed_off_at);
        $this->assertSame(25.0, (float) $order->fresh()->wallet_reserved);
        $this->assertSame(125.0, (float) $order->fresh()->payfast_amount_due);
        $this->assertSame($orderCount, TeamPaymentOrder::count());
    }

    public function test_apply_rejects_provider_or_settlement_evidence_without_mutation(): void
    {
        $order = $this->unresolvedTeamOrder(User::factory()->create());
        $order->forceFill(['payfast_pf_payment_id' => 'PF-EXISTS'])->save();
        $originalHandoff = $order->fresh()->payfast_handed_off_at->toISOString();

        $this->artisan('payments:release-payfast-handoff', [
            'type' => 'team',
            'id' => $order->id,
            '--apply' => true,
            '--evidence' => 'PF-QUERY:CASE-UNSAFE',
            '--operator' => $this->operator->id,
        ])->assertFailed();

        $this->assertSame($originalHandoff, $order->fresh()->payfast_handed_off_at->toISOString());
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertDatabaseMissing('activity_log', [
            'subject_type' => TeamPaymentOrder::class,
            'subject_id' => $order->id,
            'description' => 'supervised unresolved PayFast handoff released',
        ]);

        $withdrawn = $this->unresolvedTeamOrder(User::factory()->create());
        $withdrawn->forceFill(['withdrawn_by' => $this->operator->id])->save();
        $withdrawnHandoff = $withdrawn->fresh()->payfast_handed_off_at->toISOString();
        $this->artisan('payments:release-payfast-handoff', [
            'type' => 'team',
            'id' => $withdrawn->id,
            '--apply' => true,
            '--evidence' => 'PF-QUERY:WITHDRAWN',
            '--operator' => $this->operator->id,
        ])->assertFailed();
        $this->assertSame($withdrawnHandoff, $withdrawn->fresh()->payfast_handed_off_at->toISOString());
    }

    public function test_registration_preview_apply_and_unsafe_evidence_boundaries(): void
    {
        $payer = User::factory()->create();
        $order = $this->unresolvedRegistrationOrder($payer);
        $originalHandoff = $order->payfast_handed_off_at->toISOString();

        $this->artisan('payments:release-payfast-handoff', [
            'type' => 'registration',
            'id' => $order->id,
        ])->assertSuccessful();
        $this->assertSame($originalHandoff, $order->fresh()->payfast_handed_off_at->toISOString());

        $this->artisan('payments:release-payfast-handoff', [
            'type' => 'registration',
            'id' => $order->id,
            '--apply' => true,
        ])->assertFailed();
        $this->assertSame($originalHandoff, $order->fresh()->payfast_handed_off_at->toISOString());

        try {
            app(RegistrationPaymentService::class)
                ->releaseUnresolvedPayfastHandoff($order, $this->operator, 'unsafe evidence with spaces');
            $this->fail('Service callers must not bypass safe evidence validation.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }
        $this->assertSame($originalHandoff, $order->fresh()->payfast_handed_off_at->toISOString());

        try {
            app(RegistrationPaymentService::class)
                ->releaseUnresolvedPayfastHandoff($order, User::factory()->create(), 'PF-QUERY:DIRECT-NON-SUPER');
            $this->fail('Service callers must provide a current super-user operator.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('operator', $exception->errors());
        }
        $this->assertSame($originalHandoff, $order->fresh()->payfast_handed_off_at->toISOString());

        $orderCount = RegistrationOrder::count();
        $itemCount = RegistrationOrderItems::count();
        $this->artisan('payments:release-payfast-handoff', [
            'type' => 'registration',
            'id' => $order->id,
            '--apply' => true,
            '--evidence' => 'PF-QUERY:REG-2048',
            '--operator' => $this->operator->id,
        ])->assertSuccessful();

        $released = $order->fresh('items');
        $this->assertNull($released->payfast_handed_off_at);
        $this->assertSame(40.0, (float) $released->wallet_reserved);
        $this->assertSame(160.0, (float) $released->payfast_amount_due);
        $this->assertSame($orderCount, RegistrationOrder::count());
        $this->assertSame($itemCount, RegistrationOrderItems::count());

        app(RegistrationPaymentService::class)->preparePayfastHandoff($released, $payer, 40, 160);
        $this->assertNotNull($order->fresh()->payfast_handed_off_at);

        $unsafe = $this->unresolvedRegistrationOrder($payer);
        $unsafe->forceFill(['payfast_pf_payment_id' => 'PF-ALREADY-RECORDED'])->save();
        $unsafeHandoff = $unsafe->fresh()->payfast_handed_off_at->toISOString();
        $this->artisan('payments:release-payfast-handoff', [
            'type' => 'registration',
            'id' => $unsafe->id,
            '--apply' => true,
            '--evidence' => 'PF-QUERY:REG-UNSAFE',
            '--operator' => $this->operator->id,
        ])->assertFailed();
        $this->assertSame($unsafeHandoff, $unsafe->fresh()->payfast_handed_off_at->toISOString());
    }

    public function test_registration_release_blocks_every_canonical_transaction_evidence_source(): void
    {
        $payer = User::factory()->create();

        $linkedOrder = $this->unresolvedRegistrationOrder($payer);
        $linkedOrder->forceFill(['wallet_transaction_id' => 98765])->save();
        $this->assertRegistrationReleaseBlocked($linkedOrder, 'PF-QUERY:ORDER-WALLET');

        $walletOrder = $this->unresolvedRegistrationOrder($payer);
        $wallet = Wallet::factory()->forUser($payer)->create();
        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => 40,
            'source_type' => 'event_registration_wallet_payment',
            'source_id' => $walletOrder->id,
        ]);
        $this->assertRegistrationReleaseBlocked($walletOrder, 'PF-QUERY:LEDGER-WALLET');

        $payfastOrder = $this->unresolvedRegistrationOrder($payer);
        (new Transaction())->forceFill([
            'custom_int5' => $payfastOrder->id,
            'pf_payment_id' => 'PF-CANONICAL-EVIDENCE',
            'payment_status' => 'COMPLETE',
        ])->save();
        $this->assertRegistrationReleaseBlocked($payfastOrder, 'PF-QUERY:TRANSACTION');
    }

    public function test_same_numeric_team_evidence_does_not_block_clean_registration_release(): void
    {
        $payer = User::factory()->create();
        $order = $this->unresolvedRegistrationOrder($payer);
        $wallet = Wallet::factory()->forUser($payer)->create();
        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => 40,
            'source_type' => 'team_registration_wallet_payment',
            'source_id' => $order->id,
        ]);
        (new Transaction())->forceFill([
            'custom_int5' => $order->id,
            'custom_str5' => 'TeamOrder',
            'pf_payment_id' => 'PF-TEAM-SAME-ID',
            'payment_status' => 'COMPLETE',
        ])->save();

        $this->artisan('payments:release-payfast-handoff', [
            'type' => 'registration',
            'id' => $order->id,
            '--apply' => true,
            '--evidence' => 'PF-QUERY:COLLISION-CLEARED',
            '--operator' => $this->operator->id,
        ])->assertSuccessful();

        $this->assertNull($order->fresh()->payfast_handed_off_at);
        $this->assertSame(40.0, (float) $order->fresh()->wallet_reserved);
        $this->assertSame(160.0, (float) $order->fresh()->payfast_amount_due);
    }

    private function unresolvedTeamOrder(User $payer): TeamPaymentOrder
    {
        $sequence = TeamPaymentOrder::count() + 1;

        return TeamPaymentOrder::create([
            'user_id' => $payer->id,
            'team_id' => 10 + $sequence,
            'player_id' => 20 + $sequence,
            'event_id' => 30 + $sequence,
            'total_amount' => 150,
            'wallet_reserved' => 25,
            'payfast_amount_due' => 125,
            'wallet_debited' => false,
            'payfast_paid' => false,
            'pay_status' => false,
            'payfast_handed_off_at' => now()->subHour(),
        ]);
    }

    private function unresolvedRegistrationOrder(User $payer): RegistrationOrder
    {
        $order = RegistrationOrder::create([
            'user_id' => $payer->id,
            'wallet_reserved' => 40,
            'payfast_amount_due' => 160,
            'wallet_debited' => false,
            'payfast_paid' => false,
            'pay_status' => false,
            'total_fee' => 200,
            'status' => 'pending',
            'payfast_handed_off_at' => now()->subHour(),
        ]);
        (new RegistrationOrderItems())->forceFill([
            'order_id' => $order->id,
            'registration_id' => 1000 + $order->id,
            'category_event_id' => 2000 + $order->id,
            'player_id' => 3000 + $order->id,
            'user_id' => $payer->id,
            'item_price' => 200,
        ])->save();

        return $order->fresh('items');
    }

    private function assertRegistrationReleaseBlocked(RegistrationOrder $order, string $evidence): void
    {
        $handoff = $order->fresh()->payfast_handed_off_at->toISOString();
        $this->artisan('payments:release-payfast-handoff', [
            'type' => 'registration',
            'id' => $order->id,
            '--apply' => true,
            '--evidence' => $evidence,
            '--operator' => $this->operator->id,
        ])->assertFailed();
        $this->assertSame($handoff, $order->fresh()->payfast_handed_off_at->toISOString());
    }
}
