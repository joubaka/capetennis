<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\{CategoryEvent, CategoryEventRegistration, ClothingOrder, ClothingRefund, Event, EventPayout, Player, RegionalClothingSettlement, RegistrationManualReceipt, RegistrationOrder, SiteSetting, Team, TeamPaymentOrder, TeamRegion, TrialParticipation, TrialParticipationReceipt, User, Wallet, WalletTransaction};
use App\Support\FinanceMutationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialLedgerSummaryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $payer;
    private Event $event;
    private CategoryEvent $category;
    private Player $player;
    private Wallet $wallet;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payer = User::factory()->create();
        $this->event = Event::factory()->create(['cape_tennis_fee' => 15]);
        $this->category = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->player = Player::factory()->create();
        $this->wallet = Wallet::factory()->forUser($this->payer)->create();
        $this->team = Team::factory()->create(['category_event_id' => $this->category->id]);
    }

    private function individual(float $wallet, float $provider, int $items = 1): RegistrationOrder
    {
        $order = RegistrationOrder::create(['user_id' => $this->payer->id, 'wallet_reserved' => $wallet,
            'wallet_debited' => $wallet > 0, 'payfast_amount_due' => $provider, 'pay_status' => true, 'status' => 'completed']);
        for ($i = 0; $i < $items; $i++) {
            DB::table('registration_order_items')->insert(['order_id' => $order->id, 'category_event_id' => $this->category->id,
                'player_id' => $this->player->id, 'item_price' => round(($wallet + $provider) / $items, 2)]);
        }
        if ($provider > 0) $this->receipt($order->id, null, $provider);
        if ($wallet > 0) WalletTransaction::create(['wallet_id' => $this->wallet->id, 'type' => 'debit', 'amount' => $wallet,
            'source_type' => 'event_registration_wallet_payment', 'source_id' => $order->id, 'meta' => ['event_id' => $this->event->id]]);
        return $order;
    }

    private function teamOrder(float $wallet, float $provider): TeamPaymentOrder
    {
        $order = TeamPaymentOrder::create(['event_id' => $this->event->id, 'team_id' => $this->team->id,
            'player_id' => Player::factory()->create()->id, 'user_id' => $this->payer->id, 'total_amount' => $wallet + $provider,
            'wallet_reserved' => $wallet, 'wallet_debited' => $wallet > 0, 'payfast_amount_due' => $provider, 'pay_status' => 1]);
        if ($provider > 0) $this->receipt($order->id, 'TeamOrder', $provider);
        if ($wallet > 0) WalletTransaction::create(['wallet_id' => $this->wallet->id, 'type' => 'debit', 'amount' => $wallet,
            'source_type' => 'team_registration_wallet_payment', 'source_id' => $order->id, 'meta' => ['event_id' => $this->event->id]]);
        return $order;
    }

    private function receipt(?int $orderId, ?string $type, float $amount, array $overrides = []): string
    {
        $providerId = 'QA-'.Str::uuid();
        DB::table('transactions_pf')->insert(array_replace(['event_id' => $this->event->id,
            'custom_int4' => $this->payer->id, 'custom_int5' => $orderId, 'custom_str5' => $type,
            'transaction_type' => 'Registration', 'amount_gross' => $amount, 'pf_payment_id' => $providerId,
            'is_test' => false, 'created_at' => now(), 'updated_at' => now()], $overrides));
        return $providerId;
    }

    private function assertParity(Event $event): array
    {
        $ledger = app(FinancialLedgerService::class);
        // The detailed route deliberately retains the original order-resolution path.
        $baseline = $ledger->buildForEvent($event);
        $optimized = $ledger->buildPaymentRows($event, (float) $event->cape_tennis_fee, summaryOnly: true);
        $money = fn ($rows) => $rows->map(fn ($row) => array_map(fn ($key) => $row->{$key} ?? null,
            ['type', 'gross', 'fee', 'capeFee', 'net', 'entryCount']))->all();
        $this->assertEquals($money($baseline['paymentRows']), $money($optimized));
        $metadata = fn ($rows) => $rows->where('type', '!=', 'clothing_payment')->map(fn ($row) => [
            $row->source_order_id ?? null, $row->team_order_id ?? null, $row->subtype, $row->method,
            $row->walletUsed, $row->registrationDetails->all(),
        ])->values()->all();
        $this->assertEquals($metadata($baseline['paymentRows']), $metadata($optimized));
        $settlements = RegionalClothingSettlement::where('event_id', $event->id)
            ->selectRaw('kind, SUM(amount) as amount')->groupBy('kind')->pluck('amount', 'kind')->all();
        $this->assertEquals($baseline['totals'], $ledger->buildTotals($optimized, $baseline['refundRows'], $baseline['payoutRows'], $settlements));
        $summary = $ledger->buildFySummaryRow($event);
        foreach (['registration_received', 'registration_net', 'registration_balance', 'clothing_received', 'clothing_net',
            'gross_payments', 'completed_refunds', 'pending_refunds', 'total_paid_out', 'balance', 'clothing_balance', 'combined_paid_out'] as $key) {
            $this->assertEquals($baseline['totals'][$key], $summary[$key], $key);
        }
        $this->assertEquals($baseline['totals']['net_revenue'], $summary['total_income']);
        $this->assertEquals($baseline['paymentRows']->whereIn('type', ['payment', 'admin_entry_fee'])->sum(fn ($row) => $row->entryCount ?? 1), $summary['total_entries']);
        $this->assertSame($baseline['paymentRows']->whereIn('type', ['payment', 'admin_entry_fee', 'clothing_payment'])->isNotEmpty(), $summary['has_transactions']);
        return $summary;
    }

    public function test_summary_matches_detailed_individual_team_wallet_hybrid_manual_admin_and_participation_rows(): void
    {
        $this->individual(0, 123.45);
        $this->individual(50.01, 199.99, 2);
        $this->individual(145.25, 0, 2);
        $this->teamOrder(0, 333.33);
        $this->teamOrder(42.11, 287.89);
        $this->teamOrder(222.22, 0);
        $manual = $this->individual(0, 0, 2);
        RegistrationManualReceipt::create(['order_id' => $manual->id, 'event_id' => $this->event->id,
            'amount' => '240.15', 'method' => 'eft', 'reference' => 'QA-MANUAL', 'verified_by_user_id' => $this->payer->id, 'paid_at' => now()]);
        $participationOrder = $this->teamOrder(0, 321.09);
        TrialParticipation::create(['event_id' => $this->event->id, 'player_id' => $this->player->id,
            'slot_id' => 100, 'order_id' => $participationOrder->id, 'payer_id' => $this->payer->id]);
        $manualParticipationOrder = $this->teamOrder(0, 0);
        TrialParticipationReceipt::create(['participation_id' => 200, 'order_id' => $manualParticipationOrder->id,
            'event_id' => $this->event->id, 'amount' => '71.23', 'method' => 'eft', 'reference' => 'QA-PARTICIPATION',
            'verified_by' => $this->payer->id, 'paid_at' => now()]);
        $this->receipt(null, null, 0, ['pf_payment_id' => null, 'item_name' => 'Admin Entry', 'cape_tennis_fee' => 7.23]);
        $this->receipt(null, null, 0, ['pf_payment_id' => null, 'item_name' => 'Unmarked zero', 'cape_tennis_fee' => 99]);
        $this->assertParity($this->event);
        SiteSetting::set('payfast_fee_percentage', '4.37');
        SiteSetting::set('payfast_fee_flat', '1.11');
        SiteSetting::set('payfast_vat_rate', '15');
        $this->assertParity($this->event);
    }

    public function test_summary_preserves_collision_foreign_payer_mixed_order_and_expired_handoff_rules(): void
    {
        $other = Event::factory()->create(['cape_tennis_fee' => 25]);
        $foreignCategory = CategoryEvent::factory()->create(['event_id' => $other->id]);
        $individual = $this->individual(71.21, 200);
        $team = $this->teamOrder(15.29, 170);
        // Independent ID spaces overlap; both must retain their own wallet amount.
        $this->assertSame($individual->id, $team->id);
        $this->receipt($individual->id, null, 19, ['custom_int4' => User::factory()->create()->id]);
        $this->receipt($individual->id, 'UnknownOrder', 21);
        $mixed = $this->individual(99, 80);
        DB::table('registration_order_items')->insert(['order_id' => $mixed->id, 'category_event_id' => $foreignCategory->id]);
        $missing = $this->individual(13, 60);
        DB::table('registration_order_items')->insert(['order_id' => $missing->id, 'category_event_id' => null]);
        $late = $this->individual(33, 110);
        DB::table('registration_orders')->where('id', $late->id)->update(['status' => 'cancelled', 'pay_status' => false]);
        $providerId = $this->receipt($late->id, null, 111);
        activity('payment')->performedOn($late)->withProperties(['event_id' => $this->event->id, 'payer_id' => $this->payer->id])
            ->log('Expired unresolved PayFast handoff retained for reconciliation');
        activity('payment')->performedOn($late)->withProperties(['pf_payment_id' => $providerId])
            ->log('Expired PayFast charge requires reconciliation');
        $teamLate = $this->teamOrder(35, 115);
        DB::table('team_payment_orders')->where('id', $teamLate->id)->update(['collection_status' => 'reconciliation_required']);
        $this->receipt($team->id, 'TeamOrder', 20, ['event_id' => $other->id]);
        $summary = $this->assertParity($this->event);
        $this->assertParity($other);
        $lateRow = app(FinancialLedgerService::class)->buildPaymentRows($this->event, 15, true)
            ->firstWhere('pf_payment_id', $providerId);
        $this->assertSame('superseded_payfast_receipt', $lateRow->subtype);
        $this->assertSame(0.0, $lateRow->walletUsed);
        $this->assertSame(0, $lateRow->entryCount);
        $this->assertGreaterThan(0, $summary['registration_received']);
    }

    public function test_summary_keeps_refunds_and_settlements_separate_and_reads_fresh_values(): void
    {
        $providerId = $this->receipt(null, null, 500);
        foreach (['completed', 'pending', 'not_refunded'] as $status) {
            CategoryEventRegistration::factory()->create(['category_event_id' => $this->category->id,
                'payment_status_id' => 1, 'pf_transaction_id' => $providerId, 'payment_method' => 'payfast',
                'status' => 'withdrawn', 'withdrawn_at' => now(), 'refund_status' => $status,
                'refund_gross' => 30.17, 'refund_method' => 'bank']);
        }
        $order = ClothingOrder::create(['event_id' => $this->event->id, 'user_id' => $this->payer->id,
            'pay_status' => 1, 'status' => 'completed', 'amount_paid' => null, 'total' => '200.11', 'payfast_fee' => '6.37']);
        FinanceMutationScope::run('refund_state_write', function () use ($order) {
            foreach (['completed', 'pending'] as $status) ClothingRefund::create(['event_id' => $this->event->id,
                'clothing_order_id' => $order->id, 'payer_id' => $this->payer->id, 'requested_by' => $this->payer->id,
                'requested_by_name' => 'Synthetic reviewer', 'request_token' => Str::uuid(), 'request_fingerprint' => str_repeat('a', 64),
                'refund_method' => 'bank', 'refund_status' => $status, 'refund_gross' => '19.33', 'refund_fee' => '1.12',
                'refund_net' => '18.21', 'reason' => 'Synthetic test refund', 'refunded_at' => $status === 'completed' ? now() : null]);
        });
        EventPayout::create(['event_id' => $this->event->id, 'amount' => '40.12', 'description' => 'QA',
            'payment_method' => 'bank_transfer', 'paid_by' => $this->payer->id, 'paid_at' => now()]);
        $region = TeamRegion::create(['region_name' => 'QA Summary Region']);
        $scope = DB::table('event_regions')->insertGetId(['event_id' => $this->event->id, 'region_id' => $region->id, 'ordering' => 1]);
        foreach (['payout' => '100.11', 'recovery' => '5.22', 'offset_in' => '7.13', 'offset_out' => '7.13'] as $kind => $amount) {
            RegionalClothingSettlement::create(['event_id' => $this->event->id, 'event_region_id' => $scope,
                'region_id' => $region->id, 'region_name' => $region->region_name, 'kind' => $kind, 'amount' => $amount,
                'recipient_name' => 'Synthetic recipient', 'occurred_at' => now(), 'reference' => 'QA-SETTLEMENT',
                'evidence' => 'Synthetic evidence', 'recorded_by' => $this->payer->id, 'recorded_by_name' => 'Synthetic actor',
                'request_token' => Str::uuid(), 'request_fingerprint' => str_repeat('b', 64)]);
        }
        $summary = $this->assertParity($this->event);
        $this->assertSame(200.11, $summary['clothing_received']);
        $this->assertSame(175.53, $summary['clothing_net']);
        $this->assertSame(80.64, $summary['clothing_balance']);
        $this->assertSame(49.5, $summary['pending_refunds']);
        $this->assertSame(40.12, $summary['registration_paid_out']);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('regional_clothing_settlements', 4);
        $this->receipt(null, null, 25.01);
        $next = $this->assertParity($this->event);
        $this->assertSame(round($summary['registration_received'] + 25.01, 2), $next['registration_received']);
    }

    private function measured(callable $callback): array
    {
        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $start = hrtime(true);
        try {
            $value = $callback();
            return ['queries' => count(DB::getQueryLog()), 'milliseconds' => round((hrtime(true) - $start) / 1e6, 2), 'value' => $value];
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_summary_query_count_does_not_grow_with_normal_receipts_or_wallet_debits(): void
    {
        $this->individual(12.34, 200.67, 2);
        $this->individual(111.22, 0);
        $this->teamOrder(55.66, 170.17);
        $this->teamOrder(166.77, 0);
        $ledger = app(FinancialLedgerService::class);
        $small = $this->measured(fn () => $ledger->buildFySummaryRow($this->event));
        for ($i = 0; $i < 24; $i++) {
            $this->individual(12.34, 200.67, 2);
            $this->individual(111.22, 0);
            $this->teamOrder(55.66, 170.17);
            $this->teamOrder(166.77, 0);
        }
        $large = $this->measured(fn () => $ledger->buildFySummaryRow($this->event));
        $legacy = $this->measured(function () use ($ledger) {
            $baseline = $ledger->buildForEvent($this->event);
            // Match the former FY builder's extra settlement aggregate as well.
            RegionalClothingSettlement::where('event_id', $this->event->id)
                ->selectRaw('kind, SUM(amount) as amount')->groupBy('kind')->pluck('amount', 'kind');
            return $baseline;
        });
        $this->assertSame($small['queries'], $large['queries']);
        $this->assertLessThan($legacy['queries'] / 3, $large['queries']);
        $this->assertSame($legacy['value']['totals']['registration_net'], $large['value']['registration_net']);
        $this->assertParity($this->event);
        if ($path = getenv('FINANCE_SUMMARY_METRICS_PATH')) {
            file_put_contents($path, json_encode(['small_4_payments' => array_diff_key($small, ['value' => true]),
                'large_100_payments' => array_diff_key($large, ['value' => true]),
                'baseline_summary_100_payments' => array_diff_key($legacy, ['value' => true])], JSON_PRETTY_PRINT));
        }
    }
}
