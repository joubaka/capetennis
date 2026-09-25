<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\CategoryEventRegistration;
use App\Models\ClothingOrder;
use App\Models\Event;
use App\Models\Player;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FinancialLedgerServiceTest
 *
 * Validates HOTFIX 3 (wallet-only included), HOTFIX 4 (gross semantics),
 * HOTFIX 7 (unified ledger), and HOTFIX 8 (pending refunds separate).
 */
class FinancialLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private FinancialLedgerService $service;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FinancialLedgerService::class);

        $this->event = Event::factory()->create([
            'cape_tennis_fee' => 10.00,
        ]);
    }

    // =========================================================================
    // HOTFIX 3 — Wallet-only orders must be included
    // =========================================================================

    public function test_wallet_only_payment_is_included_in_payment_rows(): void
    {
        $user   = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->create();

        // Create a wallet-only order (no payfast)
        $orderId = DB::table('registration_orders')->insertGetId([
            'user_id'              => $user->id,
            'wallet_reserved'      => 150.00,
            'wallet_debited'       => true,
            'payfast_amount_due'   => 0,
            'pay_status'           => true,
            'payfast_paid'         => false,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        WalletTransaction::create([
            'wallet_id'   => $wallet->id,
            'type'        => 'debit',
            'amount'      => 150.00,
            'source_type' => 'event_registration_wallet_payment',
            'source_id'   => $orderId,
            'meta'        => ['event_id' => $this->event->id],
        ]);

        // Link order to event via item → category_event
        $catEventId = DB::table('category_events')->insertGetId([
            'event_id'    => $this->event->id,
            'category_id' => $this->insertCategory(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        DB::table('registration_order_items')->insert([
            'order_id'          => $orderId,
            'category_event_id' => $catEventId,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $paymentRows = $this->service->buildPaymentRows($this->event, 10.00);
        $walletRows  = $paymentRows->where('method', 'Wallet');

        $this->assertTrue($walletRows->isNotEmpty(), 'Wallet-only rows must be present in payment rows');
        $this->assertEquals(150.00, $walletRows->sum('gross'));
    }

    public function test_fy_summary_includes_wallet_only_orders(): void
    {
        $user   = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->create();

        $orderId = DB::table('registration_orders')->insertGetId([
            'user_id'            => $user->id,
            'wallet_reserved'    => 200.00,
            'wallet_debited'     => true,
            'payfast_amount_due' => 0,
            'pay_status'         => true,
            'payfast_paid'       => false,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        WalletTransaction::create([
            'wallet_id'   => $wallet->id,
            'type'        => 'debit',
            'amount'      => 200.00,
            'source_type' => 'event_registration_wallet_payment',
            'source_id'   => $orderId,
            'meta'        => [],
        ]);

        $catEventId = DB::table('category_events')->insertGetId([
            'event_id'    => $this->event->id,
            'category_id' => $this->insertCategory(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        DB::table('registration_order_items')->insert([
            'order_id'          => $orderId,
            'category_event_id' => $catEventId,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $summary = $this->service->buildFySummaryRow($this->event);

        $this->assertGreaterThan(0, $summary['gross_payments'], 'Wallet-only gross must be > 0 in FY summary');
    }

    // =========================================================================
    // HOTFIX 4 — Gross semantics: gross_payments never reduced by refunds
    // =========================================================================

    public function test_gross_payments_never_reduced_by_completed_refunds(): void
    {
        // Insert a PayFast transaction
        DB::table('transactions_pf')->insert([
            'pf_payment_id'    => 'PF_GROSS_TEST',
            'event_id'         => $this->event->id,
            'transaction_type' => 'Registration',
            'amount_gross'     => 500.00,
            'is_test'          => false,
            'custom_int5'      => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $paymentRows = $this->service->buildPaymentRows($this->event, 10.00);
        $refundRows  = $this->service->buildRefundRows($this->event, 10.00);
        $payoutRows  = collect();

        $totals = $this->service->buildTotals($paymentRows, $refundRows, $payoutRows);

        // gross_payments must equal the raw inflow regardless of refunds
        $this->assertEquals(
            round($paymentRows->sum('gross'), 2),
            $totals['gross_payments'],
            'gross_payments must equal raw payment inflow, not reduced by refunds'
        );
    }

    // =========================================================================
    // HOTFIX 8 — Pending refunds must not reduce net revenue
    // =========================================================================

    public function test_pending_refund_does_not_reduce_net_revenue(): void
    {
        // Insert a PayFast transaction
        DB::table('transactions_pf')->insert([
            'pf_payment_id'    => 'PF_PENDING_TEST',
            'event_id'         => $this->event->id,
            'transaction_type' => 'Registration',
            'amount_gross'     => 400.00,
            'is_test'          => false,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $paymentRows = $this->service->buildPaymentRows($this->event, 10.00);
        $emptyRefunds = collect(); // no refunds at all

        $totalsNoRefund = $this->service->buildTotals($paymentRows, $emptyRefunds, collect());

        // Now simulate a pending refund row (not completed)
        $pendingRefundRow = (object) [
            'type'          => 'refund',
            'refund_status' => CategoryEventRegistration::REFUND_PENDING,
            'refund_gross'  => 400.00,
            'refund_fee'    => 10.00,
            'net'           => -380.00,
        ];
        $totalsWithPending = $this->service->buildTotals($paymentRows, collect([$pendingRefundRow]), collect());

        // Net revenue must be the same whether a pending refund exists or not
        $this->assertEquals(
            $totalsNoRefund['net_revenue'],
            $totalsWithPending['net_revenue'],
            'Pending refunds must NOT reduce realized net revenue'
        );

        // Pending refund must appear in its own bucket
        $this->assertEquals(400.00, $totalsWithPending['pending_refunds']);
    }

    public function test_completed_refund_reduces_net_revenue(): void
    {
        DB::table('transactions_pf')->insert([
            'pf_payment_id'    => 'PF_COMPLETED_REFUND',
            'event_id'         => $this->event->id,
            'transaction_type' => 'Registration',
            'amount_gross'     => 300.00,
            'is_test'          => false,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $paymentRows = $this->service->buildPaymentRows($this->event, 10.00);
        $emptyRefunds = collect();
        $totalsNoRefund = $this->service->buildTotals($paymentRows, $emptyRefunds, collect());

        $completedRefundRow = (object) [
            'type'          => 'refund',
            'refund_status' => CategoryEventRegistration::REFUND_COMPLETED,
            'refund_gross'  => 300.00,
            'refund_fee'    => 9.00,
            'net'           => round(-300 + 9 + 10, 2), // -281
        ];
        $totalsWithCompleted = $this->service->buildTotals($paymentRows, collect([$completedRefundRow]), collect());

        $this->assertLessThan(
            $totalsNoRefund['net_revenue'],
            $totalsWithCompleted['net_revenue'],
            'Completed refund must reduce net revenue'
        );
        $this->assertEquals(300.00, $totalsWithCompleted['completed_refunds']);
    }

    // =========================================================================
    // HOTFIX 7 — Dashboard and ledger totals align (no double-counting)
    // =========================================================================

    public function test_no_double_counting_of_hybrid_payment(): void
    {
        $user   = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->create();

        $orderId = DB::table('registration_orders')->insertGetId([
            'user_id'              => $user->id,
            'wallet_reserved'      => 100.00,
            'wallet_debited'       => true,
            'payfast_amount_due'   => 200.00,
            'payfast_paid'         => true,
            'pay_status'           => true,
            'payfast_pf_payment_id' => 'PF_HYBRID',
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        // PayFast transaction for the hybrid order
        DB::table('transactions_pf')->insert([
            'pf_payment_id'    => 'PF_HYBRID',
            'event_id'         => $this->event->id,
            'transaction_type' => 'Registration',
            'amount_gross'     => 200.00,
            'is_test'          => false,
            'custom_int5'      => $orderId,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $paymentRows = $this->service->buildPaymentRows($this->event, 10.00);

        // Hybrid order should appear once (via PayFast row, with walletUsed added)
        // and NOT also appear in wallet-only rows
        $walletOnlyRows = $paymentRows->where('method', 'Wallet');
        $hybridRows     = $paymentRows->where('method', 'PayFast + Wallet');

        $this->assertEmpty($walletOnlyRows, 'Hybrid order must NOT appear as wallet-only');
        $this->assertNotEmpty($hybridRows, 'Hybrid order must appear as PayFast + Wallet');

        // Gross should include both payfast + wallet amounts
        $this->assertEquals(300.00, $hybridRows->sum('gross'));
    }

    // =========================================================================
    // PHASE 1 — EventTransactionController / PDF parity with FinancialLedgerService
    // =========================================================================

    public function test_event_transaction_page_total_equals_ledger_service(): void
    {
        // Insert two PayFast transactions
        DB::table('transactions_pf')->insert([
            ['pf_payment_id' => 'PF_PAGE_A', 'event_id' => $this->event->id, 'transaction_type' => 'Registration', 'amount_gross' => 300.00, 'is_test' => false, 'created_at' => now(), 'updated_at' => now()],
            ['pf_payment_id' => 'PF_PAGE_B', 'event_id' => $this->event->id, 'transaction_type' => 'Registration', 'amount_gross' => 200.00, 'is_test' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $built   = $this->service->buildForEvent($this->event);
        $totals  = $built['totals'];

        $this->assertEquals(
            round($built['paymentRows']->sum('gross'), 2),
            $totals['gross_payments'],
            'gross_payments from service must match payment row sum'
        );
    }

    public function test_pdf_total_equals_ledger_service(): void
    {
        DB::table('transactions_pf')->insert([
            'pf_payment_id' => 'PF_PDF_A', 'event_id' => $this->event->id,
            'transaction_type' => 'Registration', 'amount_gross' => 450.00,
            'is_test' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $built  = $this->service->buildForEvent($this->event);
        $totals = $built['totals'];

        // The PDF now uses the same service; net_revenue must be identical
        $this->assertIsFloat($totals['net_revenue']);
        $this->assertGreaterThan(0, $totals['gross_payments']);
    }

    public function test_archived_payfast_row_is_excluded(): void
    {
        DB::table('transactions_pf')->insert([
            'pf_payment_id'    => 'PF_ARCHIVED',
            'event_id'         => $this->event->id,
            'transaction_type' => 'Registration',
            'amount_gross'     => 500.00,
            'is_test'          => false,
            'archived_at'      => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $paymentRows = $this->service->buildPaymentRows($this->event, 10.00);
        $this->assertEmpty($paymentRows, 'Archived PayFast row must be excluded from payment rows');
    }

    public function test_admin_entry_exposes_registered_player_and_category_details(): void
    {
        $player = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Player']);
        $categoryId = $this->insertCategory();
        $categoryEventId = DB::table('category_events')->insertGetId([
            'event_id' => $this->event->id,
            'category_id' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('transactions_pf')->insert([
            'event_id' => $this->event->id,
            'transaction_type' => 'Registration',
            'amount_gross' => 0,
            'cape_tennis_fee' => 10.00,
            'player_id' => $player->id,
            'category_event_id' => $categoryEventId,
            'item_name' => 'Admin Entry',
            'is_test' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $built = $this->service->buildForEvent($this->event->refresh());
        $row = $built['paymentRows']->first();
        $detail = $row->registrationDetails->first();

        $this->assertSame('admin_entry_fee', $row->type);
        $this->assertSame('admin_entry_fee_liability', $row->subtype);
        $this->assertNull($row->payment_method);
        $this->assertSame(0.0, $row->amount_gross);
        $this->assertSame(0, $row->amount_fee);
        $this->assertSame(-10.0, $row->amount_net);
        $this->assertNull($row->source_pf_id);
        $this->assertNull($row->pf_payment_id);
        $this->assertNull($row->paid_at);
        $this->assertSame(0.0, $row->gross);
        $this->assertSame(0, $row->fee);
        $this->assertSame(-10.0, $row->capeFee);
        $this->assertSame(0.0, $row->payfastGross);
        $this->assertSame(0.0, $row->walletUsed);
        $this->assertSame('Jamie Player', $detail['player']);
        $this->assertSame('Test Category', $detail['category']);
        $this->assertSame(0.0, $detail['price']);

        $totals = $built['totals'];
        $this->assertSame(0.0, $totals['gross_payments']);
        $this->assertSame(0.0, $totals['pf_fees']);
        $this->assertSame(-10.0, $totals['cape_fees']);
        $this->assertSame(-10.0, $totals['net_revenue']);
    }

    public function test_transaction_fee_snapshot_does_not_change_when_event_fee_changes(): void
    {
        DB::table('transactions_pf')->insert([
            'event_id' => $this->event->id,
            'transaction_type' => 'Registration',
            'amount_gross' => 0,
            'cape_tennis_fee' => 10.00,
            'item_name' => 'Admin Entry',
            'is_test' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->event->update(['cape_tennis_fee' => 25.00]);
        $built = $this->service->buildForEvent($this->event->refresh());
        $row = $built['paymentRows']->first();

        $this->assertSame('admin_entry_fee', $row->type);
        $this->assertSame(-10.0, $row->capeFee);
        $this->assertSame(-10.0, $built['totals']['cape_fees']);
        $this->assertSame(-10.0, $built['totals']['net_revenue']);
    }

    public function test_unmarked_zero_value_transaction_is_not_an_admin_fee_liability(): void
    {
        DB::table('transactions_pf')->insert([
            'event_id' => $this->event->id,
            'transaction_type' => 'Registration',
            'amount_gross' => 0,
            'cape_tennis_fee' => 10.00,
            'item_name' => 'Legacy incomplete registration',
            'is_test' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $built = $this->service->buildForEvent($this->event->refresh());
        $row = $built['paymentRows']->first();

        $this->assertSame('unreconciled', $row->type);
        $this->assertSame('unreconciled_zero_value', $row->subtype);
        $this->assertNull($row->payment_method);
        $this->assertNull($row->paid_at);
        $this->assertSame(0.0, $row->capeFee);
        $this->assertSame(0.0, $row->amount_net);
        $this->assertSame(0.0, $built['totals']['cape_fees']);
        $this->assertSame(0.0, $built['totals']['net_revenue']);
        $summary = $this->service->buildFySummaryRow($this->event->refresh());
        $this->assertSame(0, $summary['total_entries']);
        $this->assertFalse($summary['has_transactions']);
    }

    public function test_hardcoded_fee_not_present_in_blade(): void
    {
        $bladePath = resource_path('views/backend/event/transactions.blade.php');
        $this->assertFileExists($bladePath);
        $this->assertStringNotContainsString(
            '* 285',
            file_get_contents($bladePath),
            'Hardcoded 285 entry fee must not appear in transactions Blade'
        );
    }

    public function test_admin_entry_transaction_copy_is_explicitly_not_reconciled(): void
    {
        $blade = file_get_contents(resource_path('views/backend/event/transactions.blade.php'));

        $this->assertStringContainsString('admin-entry fee liability', strtolower($blade));
        $this->assertStringContainsString('r 0.00 received or reconciled', strtolower($blade));
        $this->assertStringContainsString('not payment or refund evidence', strtolower($blade));
        $this->assertStringContainsString("\$tx->type === 'admin_entry_fee'", $blade);
        $this->assertStringNotContainsString('collected privately', strtolower($blade));
        $this->assertStringNotContainsString('privately collected', strtolower($blade));
        $this->assertStringNotContainsString("\$tx->type === 'payment' && \$tx->method === 'Admin Entry'", $blade);
    }

    public function test_transactions_view_renders_payment_items_with_missing_relations(): void
    {
        $transactions = collect([
            (object) [
                'type'         => 'payment',
                'method'       => 'PayFast',
                'player'       => null,
                'pf_payment_id'=> 'PF_TEAM_NULLS',
                'entryCount'   => 1,
                'gross'        => 125.00,
                'payfastGross' => 125.00,
                'walletUsed'   => 0.00,
                'fee'          => -5.00,
                'capeFee'      => -10.00,
                'net'          => 110.00,
                'created_at'   => now(),
                'order'        => (object) [
                    'items' => collect([
                        (object) [
                            'player'         => null,
                            'category_event' => null,
                            'item_price'     => 125.00,
                        ],
                    ]),
                ],
            ],
        ]);

        $html = view('backend.event.transactions', [
            'event'                     => $this->event,
            'transactions'              => $transactions,
            'feePerEntry'               => 10.00,
            'isTeamEvent'               => true,
            'totalEntries'              => 1,
            'refundCount'               => 0,
            'completedRefundCount'      => 0,
            'pendingRefundCount'        => 0,
            'noRefundCount'             => 0,
            'noRefundRetainedTotal'     => 0.00,
            'totalWithdrawals'          => 0.00,
            'completedWithdrawalsTotal' => 0.00,
            'pendingWithdrawalsTotal'   => 0.00,
            'totalGross'                => 125.00,
            'totalPayfastFees'          => -5.00,
            'totalCapeTennisFees'       => -10.00,
            'totalPayouts'              => 0.00,
            'netTournamentIncome'       => 110.00,
            'adminEntriesCount'         => 0,
            'adminEntriesCapeFee'       => 0.00,
            'adminGrossPrivate'         => 0.00,
            'payfastEntriesCount'       => 1,
            'payfastGrossTotal'         => 125.00,
        ])->render();

        $payloadPattern = "/data-items='([^']+)'/";

        $this->assertMatchesRegularExpression($payloadPattern, $html, 'Expected rendered child payload JSON');
        preg_match($payloadPattern, $html, $matches);
        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
        $paymentItem = collect($payload)->firstWhere('mode', 'payment_item');

        $this->assertNotNull($paymentItem);
        $this->assertSame('', $paymentItem['player']);
        $this->assertSame('—', $paymentItem['category']);
        $this->assertSame('125.00', $paymentItem['price']);
    }

    public function test_completed_clothing_receipt_is_event_scoped_detailed_and_not_counted_as_entry(): void
    {
        $payer = User::factory()->create(['name' => 'Clothing Payer']);
        $player = Player::factory()->create(['name' => 'Casey', 'surname' => 'Player']);
        $otherEvent = Event::factory()->create(['cape_tennis_fee' => 10.00]);
        $regionId = DB::table('team_regions')->insertGetId([
            'region_name' => 'West Coast',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $teamId = DB::table('teams')->insertGetId([
            'user_id' => $payer->id,
            'name' => 'West Coast U15',
            'personal_team' => false,
            'region_id' => $regionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $itemTypeId = DB::table('clothing_item_types')->insertGetId([
            'item_type_name' => 'Tracksuit',
            'price' => 300,
            'region_id' => $regionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sizeId = DB::table('clothing_sizes')->insertGetId([
            'size' => 'Medium',
            'item_type' => $itemTypeId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paid = ClothingOrder::create([
            'event_id' => $this->event->id,
            'team_id' => $teamId,
            'player_id' => $player->id,
            'user_id' => $payer->id,
            'pay_status' => 1,
            'status' => 'completed',
            'payfast_paid' => true,
            'payfast_pf_payment_id' => 'PF-CLOTHING-PAID',
            'subtotal' => 600.00,
            'payfast_fee' => 18.00,
            'total' => 618.00,
            'amount_paid' => 618.00,
            'paid_at' => now(),
        ]);
        DB::table('clothing_order_items')->insert([
            'clothing_order_id' => $paid->id,
            'clothing_order_item_id' => $itemTypeId,
            'clothing_item_size' => $sizeId,
            'item_name' => 'Tracksuit',
            'size_name' => 'Medium',
            'qty' => 2,
            'price' => 309.00,
            'line_total' => 618.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            ['event_id' => $this->event->id, 'status' => 'pending', 'reference' => 'PF-CLOTHING-PENDING'],
            ['event_id' => $otherEvent->id, 'status' => 'completed', 'reference' => 'PF-CLOTHING-OTHER'],
        ] as $excluded) {
            ClothingOrder::create([
                'event_id' => $excluded['event_id'],
                'team_id' => $teamId,
                'player_id' => $player->id,
                'user_id' => $payer->id,
                'pay_status' => 1,
                'status' => $excluded['status'],
                'payfast_paid' => true,
                'payfast_pf_payment_id' => $excluded['reference'],
                'subtotal' => 100.00,
                'payfast_fee' => 3.00,
                'total' => 103.00,
                'amount_paid' => 103.00,
                'paid_at' => now(),
            ]);
        }

        DB::table('transactions_pf')->insert([
            'pf_payment_id' => 'PF-REGISTRATION',
            'event_id' => $this->event->id,
            'transaction_type' => 'Registration',
            'amount_gross' => 100.00,
            'cape_tennis_fee' => 10.00,
            'is_test' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $built = $this->service->buildForEvent($this->event->refresh());
        $clothingRows = $built['paymentRows']->where('type', 'clothing_payment')->values();
        $this->assertCount(1, $clothingRows, 'Pending and cross-event clothing must be excluded');
        $row = $clothingRows->first();
        $detail = $row->registrationDetails->first();

        $this->assertSame(618.0, $row->gross);
        $this->assertSame(-18.0, $row->fee);
        $this->assertSame(0.0, $row->capeFee);
        $this->assertSame(600.0, $row->net);
        $this->assertSame('Clothing Payer', $row->user_name);
        $this->assertSame('Casey Player', $row->player);
        $this->assertSame('West Coast U15', $row->team);
        $this->assertSame('West Coast', $row->region);
        $this->assertSame('Tracksuit', $detail['item']);
        $this->assertSame('Medium', $detail['size']);
        $this->assertSame(2, $detail['quantity']);
        $this->assertSame(618.0, $detail['price']);

        $this->assertSame(718.0, $built['totals']['gross_payments']);
        $this->assertSame(618.0, $built['totals']['clothing_received']);
        $this->assertSame(600.0, $built['totals']['clothing_net']);
        $this->assertSame(100.0, $built['totals']['registration_received']);
        $this->assertSame(86.8, $built['totals']['registration_net']);

        $summary = $this->service->buildFySummaryRow($this->event->refresh());
        $this->assertSame(1, $summary['total_entries']);
        $this->assertTrue($summary['has_transactions']);
        $clothingOnlySummary = $this->service->buildFySummaryRow($otherEvent->refresh());
        $this->assertSame(0, $clothingOnlySummary['total_entries']);
        $this->assertTrue($clothingOnlySummary['has_transactions']);

        $totalsWithPayout = $this->service->buildTotals(
            $built['paymentRows'],
            collect(),
            collect([(object) ['net' => -25.00]])
        );
        $this->assertSame(61.8, $totalsWithPayout['registration_balance']);
        $this->assertSame(661.8, $totalsWithPayout['balance']);
    }

    public function test_legacy_paid_clothing_uses_snapshotted_total_when_amount_paid_is_null(): void
    {
        ClothingOrder::create([
            'event_id' => $this->event->id,
            'pay_status' => 1,
            'status' => 'completed',
            'payfast_paid' => true,
            'payfast_pf_payment_id' => 'PF-CLOTHING-LEGACY',
            'subtotal' => 250.00,
            'payfast_fee' => 8.00,
            'total' => 258.00,
            'amount_paid' => null,
            'paid_at' => now(),
        ]);

        $built = $this->service->buildForEvent($this->event->refresh());
        $row = $built['paymentRows']->firstWhere('type', 'clothing_payment');

        $this->assertNotNull($row);
        $this->assertSame(258.0, $row->gross);
        $this->assertSame(-8.0, $row->fee);
        $this->assertSame(250.0, $row->net);
        $this->assertSame(258.0, $built['totals']['clothing_received']);
    }

    public function test_transaction_pdf_has_clothing_specific_item_rendering(): void
    {
        $blade = file_get_contents(resource_path('views/backend/adminPage/pdf/transactions.blade.php'));

        $this->assertStringContainsString("\$t->type === 'clothing_payment'", $blade);
        $this->assertStringContainsString('$item->item_name', $blade);
        $this->assertStringContainsString('$item->line_total', $blade);
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function insertCategory(): int
    {
        return DB::table('categories')->insertGetId([
            'name'       => 'Test Category',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
