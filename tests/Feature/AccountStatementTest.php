<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\CategoryAdjustment;
use App\Models\CategoryEventRegistration;
use App\Models\Registration;
use App\Models\ClothingOrder;
use App\Models\ClothingRefund;
use App\Models\Event;
use App\Models\Player;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\TeamPaymentOrder;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\AccountStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountStatementTest extends TestCase
{
    use RefreshDatabase;

    private function receipt(User $user, array $attributes = []): Transaction
    {
        return Transaction::unguarded(fn () => Transaction::create($attributes + [
            'custom_int4' => $user->id, 'pf_payment_id' => 'PF'.fake()->unique()->numberBetween(100000, 999999),
            'amount_gross' => 300, 'payment_status' => 'COMPLETE', 'is_test' => false,
            'created_at' => '2025-06-10 10:00:00', 'updated_at' => '2025-06-10 10:00:00',
        ]));
    }

    private function statement(User $user, string $player = '', string $category = 'all'): array
    {
        return app(AccountStatementService::class)->build($user, '2025-01-01', '2026-12-31', $player, $category);
    }

    public function test_historical_schema_without_payfast_status_uses_verified_orders_and_discloses_unsupported_history(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $player = Player::factory()->create();
        $order = TeamPaymentOrder::create(['user_id' => $user->id, 'player_id' => $player->id, 'event_id' => $event->id,
            'total_amount' => 300, 'pay_status' => true, 'payfast_paid' => true,
            'payfast_amount_due' => 300, 'payfast_pf_payment_id' => 'HISTORICAL-PAID']);
        $this->receipt($user, ['pf_payment_id' => 'HISTORICAL-PAID', 'payment_status' => null,
            'custom_str5' => 'TeamOrder', 'custom_int5' => $order->id, 'event_id' => $event->id]);
        $this->receipt($user, ['pf_payment_id' => 'UNVERIFIED-STANDALONE', 'player_id' => $player->id,
            'category_event_id' => $category->id, 'event_id' => $event->id]);
        $this->receipt($user, ['pf_payment_id' => 'WRONG-REFERENCE', 'custom_str5' => 'TeamOrder',
            'custom_int5' => $order->id, 'event_id' => $event->id]);

        $schema = Schema::getFacadeRoot();
        $mock = \Mockery::mock($schema);
        $mock->shouldReceive('hasTable')->andReturnUsing(fn ($table) => $schema->hasTable($table))->byDefault();
        $mock->shouldReceive('hasColumn')->andReturnUsing(fn ($table, $column) => $schema->hasColumn($table, $column))->byDefault();
        $mock->shouldReceive('hasColumn')->with('transactions_pf', 'payment_status')->andReturn(false);
        $mock->shouldReceive('hasTable')->with('registration_manual_receipts')->andReturn(false);
        $mock->shouldReceive('hasTable')->with('trial_participation_receipts')->andReturn(false);
        Schema::swap($mock);
        $queries = [];
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        try {
            $statement = $this->statement($user);
            $this->assertCount(1, $statement['rows']);
            $this->assertSame(30000, $statement['paid']);
            $this->assertCount(2, $statement['warnings']);
            $this->assertCount(0, $this->statement($other)['rows']);
            $this->assertStringNotContainsString('`payment_status`', implode("\n", $queries));
            $params = ['from' => '2025-01-01', 'to' => '2026-12-31'];
            $this->actingAs($user)->get(route('account.statement', $params))
                ->assertOk()->assertSee('History notice')->assertSee('older PayFast receipts');
            $csv = $this->get(route('account.statement', $params + ['format' => 'csv']))->assertOk();
            $this->assertStringContainsString('History notice', $csv->streamedContent());
            $this->get(route('account.statement', $params + ['format' => 'pdf']))->assertOk()
                ->assertHeader('content-type', 'application/pdf');
            $this->assertStringContainsString($statement['warnings'][0], view('account.statement-pdf', $statement)->render());
            $this->assertDatabaseCount('transactions_pf', 3);
        } finally {
            Schema::swap($schema);
        }
    }

    public function test_guests_cannot_access_statement_and_invalid_ranges_are_rejected(): void
    {
        $this->get(route('account.statement'))->assertRedirect();
        $this->actingAs(User::factory()->create())->getJson(route('account.statement', ['from' => '2020-01-01', 'to' => '2026-01-01']))->assertUnprocessable();
    }

    public function test_hybrid_uses_actual_debit_and_canonical_null_status_receipt_once_with_original_team_player(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->create(['name' => 'Cape 2025']);
        $player = Player::factory()->create(['name' => 'Mia', 'surname' => 'Example']);
        $beneficiary = Player::factory()->create(['name' => 'Other', 'surname' => 'Player']);
        $order = TeamPaymentOrder::create(['user_id' => $user->id, 'player_id' => $player->id, 'event_id' => $event->id,
            'total_amount' => 490, 'wallet_reserved' => 190, 'wallet_debited' => true, 'payfast_paid' => true,
            'payfast_amount_due' => 300, 'payfast_pf_payment_id' => 'TEAM-PF', 'pay_status' => true]);
        $order->forceFill(['beneficiary_player_id' => $beneficiary->id])->save();
        $this->receipt($user, ['pf_payment_id' => 'TEAM-PF', 'payment_status' => null, 'custom_str5' => 'TeamOrder', 'custom_int5' => $order->id,
            'event_id' => $event->id, 'player_id' => $player->id]);
        $wallet = $user->wallet()->create();
        WalletTransaction::create(['wallet_id' => $wallet->id, 'type' => 'debit', 'amount' => 190,
            'source_type' => 'team_registration_wallet_payment', 'source_id' => $order->id])->forceFill(['created_at' => '2025-06-10'])->save();
        WalletTransaction::create(['wallet_id' => $wallet->id, 'type' => 'credit', 'amount' => 190,
            'source_type' => 'topup', 'source_id' => 1])->forceFill(['created_at' => '2025-06-10'])->save();
        $this->receipt($other, ['custom_str5' => 'TeamOrder', 'custom_int5' => $order->id, 'event_id' => $event->id]);
        $statement = $this->statement($user, 'Mia');
        $this->assertCount(2, $statement['rows']);
        $this->assertSame(49000, $statement['paid']);
        $this->assertSame('Mia Example', $statement['rows'][0]['details'][0]['player']);
        $this->assertSame(49000, $this->statement($user)['paid']);
        $this->assertCount(0, $this->statement($other)['rows']);
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseCount('transactions_pf', 2);
    }

    public function test_unequal_item_fees_remain_descriptive_and_legacy_receipts_are_supported(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $mia = Player::factory()->create(['name' => 'Mia']);
        $order = RegistrationOrder::create(['user_id' => $user->id, 'pay_status' => true, 'payfast_paid' => true,
            'payfast_amount_due' => 500.01, 'payfast_pf_payment_id' => 'INDIVIDUAL']);
        foreach ([100.01, 200, 200] as $price) {
            RegistrationOrderItems::unguarded(fn () => RegistrationOrderItems::create(['order_id' => $order->id,
                'player_id' => $mia->id, 'category_event_id' => $category->id, 'item_price' => $price]));
        }
        $this->receipt($user, ['pf_payment_id' => 'INDIVIDUAL', 'amount_gross' => 500.01, 'custom_int5' => $order->id, 'event_id' => $event->id]);
        $this->receipt($user, ['amount_gross' => 200, 'event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $mia->id]);
        $this->receipt($user, ['amount_gross' => 900, 'event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $mia->id, 'is_test' => true]);
        $statement = $this->statement($user);
        $this->assertSame(70001, $statement['paid']);
        $this->assertCount(2, $statement['rows']);
        $details = $statement['rows']->firstWhere('checkout', 'R'.$order->id)['details'];
        $this->assertSame([10001, 20000, 20000], array_column($details, 'fee'));
    }

    public function test_clothing_payments_refunds_and_category_filter_are_payer_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->create();
        $player = Player::factory()->create();
        $order = ClothingOrder::create(['user_id' => $user->id, 'event_id' => $event->id, 'player_id' => $player->id,
            'pay_status' => 1, 'payfast_paid' => true, 'paid_at' => '2025-03-01', 'amount_paid' => 350.25,
            'total' => 350.25, 'payfast_pf_payment_id' => 'CLOTHES']);
        ClothingRefund::create(['clothing_order_id' => $order->id, 'event_id' => $event->id, 'payer_id' => $user->id,
            'requested_by' => $user->id, 'request_fingerprint' => hash('sha256', 'test-statement'),
            'refund_gross' => 120.15, 'reason' => 'Statement fixture refund',
            'request_token' => 'test-statement-refund', 'refund_status' => 'completed', 'refund_method' => 'wallet', 'refund_net' => 120.15, 'refunded_at' => '2026-01-02']);
        $statement = $this->statement($user);
        $this->assertSame(35025, $statement['paid']);
        $this->assertSame(12015, $statement['refunded']);
        $this->assertSame(23010, $statement['net']);
        $this->assertCount(2, $this->statement($user, '', 'clothing')['rows']);
        $this->assertCount(0, $this->statement($user, '', 'entry')['rows']);
        $this->assertCount(0, $this->statement($other)['rows']);
    }

    public function test_page_pdf_and_csv_are_downloadable_and_read_only(): void
    {
        $user = User::factory()->create(['name' => '=HYPERLINK("bad")']);
        $params = ['from' => '2025-01-01', 'to' => '2026-12-31'];
        $response = $this->actingAs($user)->get(route('account.statement', $params));
        $response->assertOk()->assertSee('Download PDF')->assertSee('Clothing');
        $csv = $this->get(route('account.statement', $params + ['format' => 'csv']))->assertOk();
        $this->assertStringContainsString("'=HYPERLINK", $csv->streamedContent());
        $this->get(route('account.statement', $params + ['format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_category_change_receipts_and_actual_wallet_credits_exclude_unapplied_and_other_payers(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $player = Player::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $base = ['entry_id' => 1, 'event_id' => $event->id, 'player_id' => $player->id, 'user_id' => $user->id,
            'actor_id' => $user->id, 'source_category_id' => $category->id, 'target_category_id' => $category->id,
            'payment_before' => [], 'target_fee' => 500, 'expires_at' => '2025-12-31', 'applied_at' => '2025-07-01'];
        $charge = CategoryAdjustment::create($base + ['request_token' => fake()->uuid(), 'status' => 'completed', 'difference' => 100.15,
            'pay_status' => true, 'payfast_paid' => true, 'payfast_pf_payment_id' => 'ADJUST']);
        $this->receipt($user, ['pf_payment_id' => 'ADJUST', 'amount_gross' => 100.15, 'payment_status' => null,
            'custom_str5' => 'CategoryAdjustment', 'custom_int5' => $charge->id, 'event_id' => $event->id, 'player_id' => $player->id]);
        $creditAdjustment = CategoryAdjustment::create($base + ['request_token' => fake()->uuid(), 'status' => 'completed', 'difference' => -20.05]);
        $wallet = $user->wallet()->create();
        $credit = WalletTransaction::create(['wallet_id' => $wallet->id, 'type' => 'credit', 'amount' => 20.05,
            'source_type' => 'category_adjustment', 'source_id' => $creditAdjustment->id]);
        $credit->forceFill(['created_at' => '2025-07-01'])->save();
        $creditAdjustment->update(['wallet_transaction_id' => $credit->id]);
        $unapplied = CategoryAdjustment::create($base + ['request_token' => fake()->uuid(), 'status' => 'reconciliation_required', 'difference' => 900,
            'pay_status' => true, 'payfast_pf_payment_id' => 'LATE-ADJUST']);
        $this->receipt($user, ['pf_payment_id' => 'LATE-ADJUST', 'amount_gross' => 900, 'custom_str5' => 'CategoryAdjustment',
            'custom_int5' => $unapplied->id, 'event_id' => $event->id, 'player_id' => $player->id]);
        $statement = $this->statement($user);
        $this->assertSame(10015, $statement['paid']);
        $this->assertSame(2005, $statement['refunded']);
        $this->assertCount(2, $statement['rows']);
        $this->assertCount(0, $this->statement(User::factory()->create())['rows']);
    }

    public function test_completed_refunds_use_actual_net_and_recorded_dates_while_pending_and_waived_do_not_reduce_totals(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        foreach (['completed', 'pending', 'waived'] as $status) {
            TeamPaymentOrder::create(['user_id' => $user->id, 'event_id' => $event->id, 'refund_status' => $status,
                'refund_gross' => 500, 'refund_net' => 123.45, 'refunded_at' => '2025-08-01']);
            $registration = Registration::create();
            CategoryEventRegistration::create(['category_event_id' => $category->id, 'registration_id' => $registration->id,
                'user_id' => $user->id, 'refund_status' => $status, 'refund_gross' => 500, 'refund_net' => 67.89, 'refunded_at' => '2026-08-01']);
        }
        TeamPaymentOrder::create(['user_id' => $user->id, 'event_id' => $event->id, 'refund_status' => 'completed',
            'refund_net' => 999, 'refunded_at' => '2024-08-01']);
        $statement = $this->statement($user);
        $this->assertSame(19134, $statement['refunded']);
        $this->assertSame(-19134, $statement['net']);
        $this->assertCount(2, $statement['rows']);
    }
}
